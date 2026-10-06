<?php

/*
 * Your installation or use of this SugarCRM file is subject to the applicable
 * terms available at
 * http://support.sugarcrm.com/Resources/Master_Subscription_Agreements/.
 * If you do not agree to all of the applicable terms or do not have the
 * authority to bind the entity as an authorized representative, then do not
 * install or use this SugarCRM file.
 *
 * Copyright (C) SugarCRM Inc. All rights reserved.
 */

namespace Sugarcrm\Sugarcrm\Util;

use Closure;
use LoggerManager;
use ReflectionClass;
use SugarAutoLoader;
use Throwable;

/**
 * Detects and handles OPcache corruption issues
 *
 * This class provides comprehensive detection and handling of OPcache corruption
 * errors that can occur in high-load environments.
 */
class OpcacheCorruptionDetector
{
    protected Closure $logFunction;

    public function __construct(callable $logHandler = null)
    {
        $this->logFunction = $logHandler ?? function (string $message) {
            error_log($message);
        };
    }

    /**
     * Main entry point for detecting and handling opcache corruption
     *
     * @param array $err Error from error_get_last()
     * @return void
     */
    public function detectAndHandle(array $err): void
    {
        // 1. Check if this looks like opcache corruption
        $details = $this->getOpcacheCorruptionError($err);
        if ($details === null) {
            return;
        }

        // 2. Extract details about the corruption
        $corruptionDetails = $this->extractErrorDetails($err, $details);

        // 3. Check if the real file exists, readable and contain the expected symbol. If not - skip.
        if (!$this->fileContainsExpectedSymbol($corruptionDetails)) {
            return;
        }

        // 4. Collect opcache statistics
        $opcacheStats = $this->getOpcacheStatistics($corruptionDetails['corruptedFile'] ?? null);

        // 5. Log comprehensive error information
        $this->logOpcacheCorruption(
            $corruptionDetails,
            $opcacheStats
        );

        // 6. Attempt selective file invalidation (safer than full reset)
        if ($corruptionDetails['corruptedFile']) {
            $this->attemptSelectiveInvalidation($corruptionDetails['corruptedFile']);
        }
    }

    /**
     * Checks if the resolved file contains the expected symbol
     *
     * @param array $corruptionDetails
     * @return bool
     */
    protected function fileContainsExpectedSymbol(array $corruptionDetails): bool
    {
        $filePath = $corruptionDetails['corruptedFile'];

        if (!$filePath || !file_exists($filePath) || !is_readable($filePath)) {
            // not sure what file is corrupted, assume it is NOT opcache corruption
            return false;
        }

        $fileContents = file_get_contents($filePath);

        if ($fileContents === false) {
            // Unable to read file, assume it is NOT opcache corruption
            return false;
        }

        $classWithoutNamespace = function (string $className) {
            $parts = explode('\\', $className);
            return end($parts);
        };

        $expectedSymbol = match ($corruptionDetails['type'] ?? null) {
            'method' => sprintf('function %s(', $corruptionDetails['method']),
            'class' => sprintf('class %s', $classWithoutNamespace($corruptionDetails['class'])),
            'interface' => sprintf('interface %s', $classWithoutNamespace($corruptionDetails['interface'])),
            'trait' => sprintf('trait %s', $classWithoutNamespace($corruptionDetails['trait'])),
            default => null,
        };

        if ($expectedSymbol === null) {
            return true;
        }

        return stripos($fileContents, $expectedSymbol) !== false;
    }

    /**
     * Determines if error signature indicates opcache corruption and returns details
     *
     * @param array $err Error from error_get_last()
     * @return array|null Returns matched details or null if not opcache corruption
     */
    protected function getOpcacheCorruptionError(array $err): ?array
    {
        if (empty($err['message'])) {
            return null;
        }

        $message = $err['message'];

        // Signature patterns for opcache corruption => expected missing symbols in the target file
        $corruptionSignatures = [
            // Missing method (primary signature from OPS-28993)
            '/Call to undefined method ([a-zA-Z0-9\\\\]+)::([a-zA-Z0-9_]+)\(\)/'
                => fn($m1, $m2) => ['class' => $m1, 'method' => $m2, 'type' => 'method'],

            // Missing class
            '/Class [\'"]?([a-zA-Z0-9\\\\]+)[\'"]? not found/' => fn($m1)
                => ['class' => $m1, 'type' => 'class'],
            '/Uncaught Error: Class [\'"]?([a-zA-Z0-9\\\\]+)[\'"]? not found/'
                => fn($m1) => ['class' => $m1, 'type' => 'class'],

            // Interface/trait issues
            '/Interface [\'"]?([a-zA-Z0-9\\\\]+)[\'"]? not found/' => fn($m1)
                => ['interface' => $m1, 'type' => 'interface'],
            '/Trait [\'"]?([a-zA-Z0-9\\\\]+)[\'"]? not found/' => fn($m1) => ['trait' => $m1, 'type' => 'trait'],

            // Opcache internal errors
            '/Segmentation fault in opcache/' => fn() => ['type' => 'segfault'],
            '/opcache.*corrupt/' => fn() => ['type' => 'corruption' ],
        ];

        foreach ($corruptionSignatures as $pattern => $extractor) {
            if (preg_match($pattern, $message, $matches)) {
                return $extractor(...array_slice($matches, 1));
            }
        }

        return null;
    }

    /**
     * Extracts detailed information about the corruption
     *
     * @param array $err Error from error_get_last()
     * @return array
     */
    protected function extractErrorDetails(array $err, array $errorDetails): array
    {
        $details = [
            'errorMessage' => $err['message'] ?? '',
            'errorFile' => $err['file'] ?? '',
            'errorLine' => $err['line'] ?? 0,
            'errorType' => $err['type'] ?? 0,
            'corruptedFile' => null,
            'timestamp' => time(),
            'pid' => getmypid(),
            'requestUri' => $_SERVER['REQUEST_URI'] ?? 'cli',
            'method' => null,
            'class' => null,
        ];

        $extractedDetails = match ($errorDetails['type'] ?? '') {
            'method', 'class' => [
                'corruptedFile' => $this->resolveClassFilePath($errorDetails['class']),
            ],
            'interface' => [
                'corruptedFile' => $this->resolveClassFilePath($errorDetails['interface']),
            ],
            'trait' => [
                'corruptedFile' => $this->resolveClassFilePath($errorDetails['trait']),
            ],
            default => [],
        };

        return array_merge($details, $extractedDetails, $errorDetails);
    }

    /**
     * Resolves class name to file path
     *
     * @param string $className Fully qualified class name
     * @return string|null File path or null if cannot resolve
     */
    protected function resolveClassFilePath(string $className): ?string
    {
        // Try Reflection to get file path where class was loaded from
        try {
            if (class_exists($className, false)) {
                $reflection = new ReflectionClass($className);
                $file = $reflection->getFileName();
                if ($file && file_exists($file)) {
                    return $file;
                }
            }
        } catch (Throwable $t) {
            $this->errorLog(
                'Attempt to resolve path using ReflectionClass failed: '
                    . $t->getMessage() . PHP_EOL . $t->getTraceAsString()
            );
        }

        // Try SugarAutoLoader
        try {
            if (class_exists(SugarAutoLoader::class, false)) {
                SugarAutoLoader::autoload($className);
                if (class_exists($className, false)) {
                    $reflection = new ReflectionClass($className);
                    $file = $reflection->getFileName();
                    if ($file && file_exists($file)) {
                        return $file;
                    }
                }
            }
        } catch (Throwable $t) {
            $this->errorLog(
                'Attempt to resolve path using SugarAutoLoader failed: '
                    . $t->getMessage() . PHP_EOL . $t->getTraceAsString()
            );
        }

        try {
            $file = \ComposerAutoloaderInitSugar::getLoader()->findFile($className);
            if ($file && file_exists($file)) {
                return $file;
            }

            // check the case if file is in opcache, but autoloaders couldn't find it
            $opcache = $this->getOpcacheStatus();
            $fileName = basename(str_replace('\\', '/', $className)) . '.php';
            if ($opcache && isset($opcache['scripts'])) {
                foreach ($opcache['scripts'] as $script => $info) {
                    if (str_ends_with($script, $fileName) && ($info['memory_consumption'] < 700)) {
                        return $script;
                    }
                }
            }
        } catch (Throwable $t) {
            $this->errorLog(
                'Attempt to resolve path using ComposerAutoloaderInitSugar failed: '
                    . $t->getMessage() . PHP_EOL . $t->getTraceAsString()
            );
        }

        return null;
    }

    /**
     * Collects comprehensive opcache statistics
     *
     * @return array
     */
    protected function getOpcacheStatistics(?string $file): array
    {
        try {
            $status = $this->getOpcacheStatus();

            if ($status === null) {
                return ['error' => 'opcache_get_status is not available'];
            }

            return [
                'enabled' => $status['opcache_enabled'] ?? false,
                'memory_usage' => $status['memory_usage'] ?? [],
                'statistics' => $status['opcache_statistics'] ?? [],
                'restart_pending' => $status['restart_pending'] ?? false,
                'restart_in_progress' => $status['restart_in_progress'] ?? false,
                'script_memory_consumption' => $file ? $this->getMemoryConsumption($status, $file) : null,
            ];
        } catch (Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    protected function getMemoryConsumption(array $opCache, string $corruptedFile): ?int
    {
        $mc = $opCache['scripts'][$corruptedFile]['memory_consumption'] ?? null;
        return $mc !== null ? intval($mc) : null;
    }

    protected function getOpcacheStatus(): ?array
    {
        if (!function_exists('opcache_get_status')) {
            return null;
        }

        $status = opcache_get_status();

        return $status === false ? null : $status;
    }

    /**
     * Logs comprehensive opcache corruption information
     *
     * @param array $details Corruption details
     * @param array $opcacheStats Opcache statistics
     * @return void
     */
    protected function logOpcacheCorruption(array $details, array $opcacheStats): void
    {
        $logMessage = sprintf(
            "[OPCACHE] CORRUPTION DETECTED: %s in %s:%d | SymbolType: %s | Symbol: %s | Method: %s | File: %s |"
            . " PID: %d | Memory: %s/%s (%.2f%% wasted) | Stats: %s",
            $details['errorMessage'],
            $details['errorFile'],
            $details['errorLine'],
            $details['type'] ?? 'unknown',
            $details['class'] ?? $details['interface'] ?? $details['trait'] ?? 'unknown',
            $details['method'] ?? 'unknown',
            $details['corruptedFile'] ?? 'unresolved',
            $details['pid'],
            $opcacheStats['memory_usage']['used_memory'] ?? 'unknown',
            $opcacheStats['memory_usage']['free_memory'] ?? 'unknown',
            $opcacheStats['memory_usage']['current_wasted_percentage'] ?? 0,
            print_r($opcacheStats, true)
        );

        // Try to use SugarCRM logger if available
        try {
            if (class_exists(LoggerManager::class, false)) {
                $logger = LoggerManager::getLogger();
                $logger->fatal($logMessage);
            }
        } catch (Throwable $e) {
            // Logger itself might be corrupted, fall back to $this->errorLog
        }

        // Always log to PHP error log as backup
        $this->errorLog($logMessage);
    }

    /**
     * Attempts to invalidate specific corrupted file from opcache
     * This is safer than full opcache_reset() under load
     *
     * @param string $filePath Path to corrupted file
     */
    protected function attemptSelectiveInvalidation(string $filePath): void
    {
        if (!function_exists('opcache_invalidate')) {
            return;
        }

        try {
            // Force invalidation (true = force)
            $result = opcache_invalidate($filePath, true);

            if ($result) {
                $this->errorLog("[OPCACHE] Successfully invalidated corrupted file: $filePath");
            } else {
                $this->errorLog("[OPCACHE] Failed to invalidate corrupted file: $filePath");
            }
        } catch (Throwable $e) {
            $this->errorLog("[OPCACHE] Exception during invalidation: " . $e->getMessage());
        }
    }

    private function errorLog(string $message): void
    {
        $logFunction = $this->logFunction;
        $logFunction($message);
    }
}
