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

use Rector\Console\ConsoleApplication;
use Rector\DependencyInjection\RectorContainerFactory;
use Rector\ValueObject\Bootstrap\BootstrapConfigs;
use RectorPrefix202503\Symfony\Component\Console\Input\ArrayInput;
use SugarAutoLoader;

class RectorScanner
{
    private const RECTOR_SCAN_EXCEPTION_LABEL = 'rector scan thrown:';

    public function scan(array $filesToScan): string
    {
        try {
            if (!in_array('phar', stream_get_wrappers())) {
                // Phar stream wrapper is not registered, so let's restore it to its default state
                stream_wrapper_restore('phar');
                $restored = true;
            }

            // prevent unwanted class_map.php updates in SugarAutoLoader::autoload called from Rector call stack
            SugarAutoLoader::$enableClassMapUpdates = false;

            $bootstrapConfigs = new BootstrapConfigs(__DIR__ . '/config.php', []);
            $container = (new RectorContainerFactory())->createFromBootstrapConfigs($bootstrapConfigs);

            $application = $container->get(ConsoleApplication::class);
            $application->setAutoExit(false);

            $arrayInput = new ArrayInput([
                'command' => 'process',
                '--dry-run' => true,
                'source' => $filesToScan,
                '--no-progress-bar' => true,
                '--xdebug' => true,
                '--clear-cache' => true,
                '--output-format' => 'json',
            ]);
            rmdir_recursive(sugar_cached('rector/cache'));
            $arrayInput->setInteractive(false);

            SugarAutoLoader::$classMap = [];
            write_array_to_file('class_map', SugarAutoLoader::$classMap, sugar_cached(SugarAutoLoader::CLASS_CACHE_FILE));
            require_once 'src/Rector/rector_preload.php';

            ob_start();
            $exitCode = $application->run($arrayInput, null);
            $bufferedOutput = ob_get_clean();

            // set SugarAutoLoader back to the initial state
            SugarAutoLoader::$enableClassMapUpdates = true;
            SugarAutoLoader::$classMap = [];
            write_array_to_file('class_map', SugarAutoLoader::$classMap, sugar_cached(SugarAutoLoader::CLASS_CACHE_FILE));
            SugarAutoLoader::$classMapDirty = false;

            $result = json_decode($bufferedOutput, true);
            $chunks = [];
            if (empty($result['errors']) && empty($result['file_diffs'])) {
                return '';
            }

            if (safeCount($result['errors'] ?? []) > 0) {
                $chunks[] = "# Errors occurred while processing some files:\n";
                foreach ($result['errors'] as $error) {
                    $chunks[] = sprintf("%s:%s:%s\n", $this->pathInPackage($error['file']), (string)$error['line'], $error['message']);
                }
            }

            if (safeCount($result['file_diffs'] ?? []) > 0) {
                $diffChunks = [];
                foreach ($result['file_diffs'] as $fileDiff) {
                    if (safeCount($fileDiff['applied_rectors'] ?? []) === 0) {
                        continue;
                    }
                    $chunk = $fileDiff['diff'];
                    $fileName = $this->pathInPackage($fileDiff['file']);
                    $chunk = str_replace('--- Original', '--- ' . $fileName, $chunk);
                    $chunk = str_replace('+++ New', '+++ ' . $fileName, $chunk);
                    $diffChunks[$fileName] = $chunk;
                }
                if (count($diffChunks) > 0) {
                    $chunks = array_merge($chunks, ["# Suggested changes:\n"], $diffChunks);
                }
            }
            return implode('', $chunks);
        } catch (\Throwable $e) {
            \LoggerManager::getLogger()->fatal(self::RECTOR_SCAN_EXCEPTION_LABEL . ' ' . $e->getMessage() . PHP_EOL . $e->getTraceAsString());
            return self::RECTOR_SCAN_EXCEPTION_LABEL . ' ' . $e->getMessage();
        } finally {
            if (isset($restored) && $restored === true) {
                stream_wrapper_unregister('phar');
            }
            $this->recoverShadow();
        }
    }

    /**
     * @return null|[message => string, url => string] Description of issue happened and the link to full report download
     */
    public function createIssueFromReportChunks(array $reportChunk): ?array
    {
        $collectedContent = '';
        foreach ($reportChunk as $chunkLabel => $report) {
            if (str_starts_with($report, self::RECTOR_SCAN_EXCEPTION_LABEL)) {
                return [
                    'message' => 'ML_PHP_COMPATIBILITY_SCAN_FAILED',
                    'url' => null,
                ];
            } elseif ($report !== '') {
                $collectedContent .= $chunkLabel . PHP_EOL . $report . PHP_EOL;
            }
        }
        if ($collectedContent !== '') {
            $reportLink = $this->createReportDownload($collectedContent);
            \LoggerManager::getLogger()->fatal("PHP compatibility report: $reportLink");
            return [
                'message' => 'ML_PHP_COMPATIBILITY_SCAN_INCOMPATIBLE_CODE',
                'url' => $reportLink,
            ];
        }

        return null;
    }

    /**
     * Store report content in a file and provide a link to download it
     */
    private function createReportDownload(string $content): ?string
    {
        $guid = \Sugarcrm\Sugarcrm\Util\Uuid::uuid1();
        $time = date('Ymd-His');
        $zipPath = sugar_cached("diagnostic/{$guid}/diagnostic-rector-{$time}.zip");
        sugar_mkdir(dirname($zipPath), null, true);

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return null;
        }
        if (!$zip->addFromString("rector-{$time}.txt", $content)) {
            return null;
        }
        $zip->close();

        $siteUrl = rtrim(\SugarConfig::getInstance()->get('site_url', ''), '/');
        $url = "{$siteUrl}/index.php?module=Administration&action=DiagnosticDownload&guid={$guid}&time=-rector-{$time}&to_pdf=1";
        return $url;
    }

    /**
     * Convert file path inside temp folder to the path inside package
     */
    protected function pathInPackage(string $file): string
    {
        return (string)preg_replace('#^.+?/mlp_temp/.+?/#', '', $file);
    }

    protected function recoverShadow(): void
    {
        if (!isMts()) {
            return;
        }
        $config = shadow_get_config();
        if (!is_array($config)) {
            return;
        }
        shadow($config['template'], $config['instance'], $config['instance_only'], true);
    }
}
