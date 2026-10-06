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

namespace Sugarcrm\Sugarcrm\Rector;

use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Sugarcrm\Sugarcrm\DependencyInjection\Container;
use Throwable;

/**
 * Trait preloader utility class
 * Handles preloading of traits from custom directories to prevent module installation issues
 */
final class TraitPreloader
{
    private const MAX_RECURSION_DEPTH = 10;
    private const MAX_TRAIT_COUNT = 100;
    private const MAX_TRAIT_FILE_SIZE = 1048576;
    private const REALPATH_CACHE_LIFETIME = 1800;

    private const TRAIT_ALLOWED_PATHS = [
        'custom/src',
        'custom/include',
        'custom/modules',
        'custom/application',
    ];

    private const CACHE_KEY_REALPATH_CACHE = 'trait_preloader_realpath_cache';

    private static ?array $currentAllowedPaths = null;
    private static ?CacheInterface $cacheInstance = null;
    private static bool $cacheInitialized = false;

    // Simple static cache loaded from external cache at start
    private static array $realpathCache = [];

    /**
     * Get cache instance from SugarCRM DI container
     */
    private static function getCache(): ?CacheInterface
    {
        if (!self::$cacheInitialized) {
            try {
                self::$cacheInstance = Container::getInstance()->get(CacheInterface::class);
            } catch (Throwable $e) {
                self::$cacheInstance = null;
            }
            self::$cacheInitialized = true;
        }

        return self::$cacheInstance;
    }

    /**
     * Preload traits from directories
     * Main entry point for trait preloading
     */
    public static function preloadTraits(?array $customPaths = null): void
    {
        $cache = self::getCache();

        try {
            if ($cache) {
                $cachedTraitPaths = $cache->get(self::CACHE_KEY_REALPATH_CACHE);
                if (is_array($cachedTraitPaths)) {
                    foreach ($cachedTraitPaths as $traitPath) {
                        if (file_exists($traitPath) && is_readable($traitPath)) {
                            include_once $traitPath;
                        }
                    }
                    return;
                }
            }

            $loadedTraits = [];
            $pathsToScan = $customPaths ?? self::TRAIT_ALLOWED_PATHS;

            self::$currentAllowedPaths = self::validateAndNormalizePaths($pathsToScan);

            foreach (self::$currentAllowedPaths as $path) {
                if (is_dir($path)) {
                    self::scanDirectoryForTraits($path, $loadedTraits);
                }
            }

            // Save loaded trait paths to external storage
            self::saveLoadedTraits($loadedTraits);

            if (isset($GLOBALS['log'])) {
                if (!empty($loadedTraits)) {
                    $GLOBALS['log']->debug('Trait preloading: loaded ' . count($loadedTraits) . ' traits');
                } else {
                    $GLOBALS['log']->debug('Trait preloading: no traits found');
                }
            }
        } catch (Throwable $e) {
            if (isset($GLOBALS['log'])) {
                $GLOBALS['log']->error('Trait preloading failed: ' . $e->getMessage());
            }
        } finally {
            self::$currentAllowedPaths = null;
        }
    }

    /**
     * Scan directory for traits recursively
     */
    private static function scanDirectoryForTraits(string $dir, array &$loadedTraits = [], int $depth = 0): void
    {
        if ($depth > self::MAX_RECURSION_DEPTH || count($loadedTraits) > self::MAX_TRAIT_COUNT) {
            return;
        }

        $realDir = self::getCachedRealpath($dir);
        if ($realDir === false || !is_dir($realDir) || !is_readable($realDir)) {
            return;
        }
        // Ensure directory stays within allowed roots (protect against symlink escapes)
        if (!self::isWithinAllowedPaths($realDir)) {
            return;
        }
        $entries = @scandir($realDir, SCANDIR_SORT_NONE);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if (str_contains($entry, '..')) {
                continue;
            }

            $fullPath = $realDir . DIRECTORY_SEPARATOR . $entry;

            if (is_file($fullPath) && !str_ends_with(strtolower($entry), '.php')) {
                continue;
            }

            $realPath = self::getCachedRealpath($fullPath);
            if ($realPath === false) {
                continue;
            }

            // Ensure resolved path stays within allowed roots (protect against symlink escapes)
            if (!self::isWithinAllowedPaths($realPath)) {
                continue;
            }

            if (is_dir($realPath)) {
                self::scanDirectoryForTraits($realPath, $loadedTraits, $depth + 1);
            } elseif (is_file($realPath)) {
                if (filesize($realPath) > self::MAX_TRAIT_FILE_SIZE) {
                    continue;
                }

                $definedTrait = self::extractTraitFromFile($realPath);
                if (!$definedTrait || trait_exists($definedTrait)) {
                    continue;
                }

                include_once $realPath;
                if (trait_exists($definedTrait)) {
                    $loadedTraits[$definedTrait] = $realPath;
                }
            }
        }
    }

    /**
     * Get cached realpath result from cache backend
     */
    private static function getCachedRealpath(string $path): string|false
    {
        if (!isset(self::$realpathCache[$path])) {
            self::$realpathCache[$path] = realpath($path);
        }
        return self::$realpathCache[$path];
    }

    /**
     * Save loaded trait paths to external storage
     */
    private static function saveLoadedTraits(array $loadedTraits): void
    {
        if (self::$cacheInstance && !empty($loadedTraits)) {
            try {
                $traitPaths = array_values($loadedTraits);
                self::$cacheInstance->set(self::CACHE_KEY_REALPATH_CACHE, $traitPaths, self::REALPATH_CACHE_LIFETIME);
            } catch (Throwable $e) {
            }
        }
    }

    /**
     * Validate and normalize paths for security
     */
    private static function validateAndNormalizePaths(array $paths): array
    {
        $validatedPaths = [];
        foreach ($paths as $path) {
            if (!is_string($path) || $path === '') {
                continue;
            }
            if (str_contains($path, '..')) {
                continue;
            }
            $realPath = self::getCachedRealpath($path);
            if ($realPath !== false && is_dir($realPath) && is_readable($realPath)) {
                $validatedPaths[] = $realPath;
            }
        }
        return $validatedPaths;
    }

    /**
     * Ensure a path is within one of the allowed roots
     */
    private static function isWithinAllowedPaths(string $path): bool
    {
        if (self::$currentAllowedPaths) {
            $pathNorm = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            foreach (self::$currentAllowedPaths as $root) {
                $rootNorm = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
                if (str_starts_with($pathNorm, $rootNorm)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Extract trait name from PHP file
     */
    private static function extractTraitFromFile(string $filePath): ?string
    {
        $content = @file_get_contents($filePath, false, null, 0, 8192);
        if ($content === false) {
            return null;
        }

        if (!preg_match('/^\s*<\?(?:php)?/m', $content)) {
            return null;
        }

        $namespace = '';
        // Handle namespace with optional leading backslash and multi-line declarations
        if (preg_match('/^\s*namespace\s+\\\\?([a-zA-Z_][a-zA-Z0-9_\\\\]*)\s*[;{]/m', $content, $matches)) {
            $namespace = ltrim(trim($matches[1]), '\\') . '\\';
        }

        if (preg_match('/^\s*trait\s+([a-zA-Z_][a-zA-Z0-9_]*)/m', $content, $matches)) {
            return $namespace . $matches[1];
        }

        return null;
    }

    /**
     * Reset only session cache (simulate new HTTP request)
     */
    public static function resetSessionCache(): void
    {
        self::$realpathCache = [];
    }

    /**
     * Reset cache (useful for testing)
     */
    public static function resetCache(): void
    {
        $cache = self::getCache();
        if ($cache) {
            try {
                $cache->delete(self::CACHE_KEY_REALPATH_CACHE);
            } catch (Throwable $e) {
            }
        }

        self::$cacheInstance = null;
        self::$cacheInitialized = false;
        self::$realpathCache = [];
    }

    public static function getRealpathCacheSize(): int
    {
        self::getCache();
        $realPathCache = self::$cacheInstance?->get(self::CACHE_KEY_REALPATH_CACHE);
        return is_array($realPathCache) ? count($realPathCache) : 0;
    }
}
