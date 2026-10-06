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
// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols
use Sugarcrm\Sugarcrm\modules\Reports\Exporters\ReportExporter;
use Sugarcrm\Sugarcrm\modules\Reports\Exporters\ReportStreamableExporterInterface;

require_once 'include/export_utils.php';

/**
 * @param Report $reporter Report object
 * @param bool $stream Streaming back to the client
 * @return string|void Return file name to caller if not streaming
 */
function template_handle_export(Report &$reporter, bool $stream = true)
{
    ini_set('zlib.output_compression', 'Off');
    $reporter->plain_text_output = true;
    //disable paging so we get all results in one pass
    $reporter->enable_paging = false;

    $exporter = new ReportExporter($reporter);

    global $locale, $sugar_config;

    // Use streaming if exporter supports it
    $exporterInstance = $exporter->getExporterInstance();
    if ($exporterInstance instanceof ReportStreamableExporterInterface) {
        if ($stream) {
            ob_clean();
            header('Pragma: cache');
            header('Content-type: text/plain; charset=' . $locale->getExportCharset());
            header('Content-Disposition: attachment; filename=Reports.csv');
            header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
            header('Last-Modified: ' . TimeDate::httpTime());
            header('Cache-Control: post-check=0, pre-check=0', false);

            $BOM = '';
            if (!empty($sugar_config['export_excel_compatible'])) {
                // Excel compatible mode
            } else {
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                if (
                    $locale->getExportCharset() == 'UTF-8' &&
                    !preg_match('/macintosh|mac os x|mac_powerpc/i', $user_agent)
                ) {
                    $BOM = "\xEF\xBB\xBF";
                }
            }

            if ($BOM) {
                print $BOM;
            }

            foreach ($exporter->getExporterInstance()->exportStream() as $chunk) {
                $transContent = $locale->translateCharset(
                    $chunk,
                    'UTF-8',
                    $locale->getExportCharset(),
                    false,
                    true
                );
                print $transContent;
            }
        } else {
            return writeToCSVFileStreaming(
                $exporter->getExporterInstance()->exportStream(),
                $reporter->name,
                $locale,
                $sugar_config
            );
        }
    } else {
        // Fallback to legacy string-based export
        $content = $exporter->export();

        $transContent = $locale->translateCharset(
            $content,
            'UTF-8',
            $locale->getExportCharset(),
            false,
            true
        );

        if ($stream) {
            ob_clean();
            header('Pragma: cache');
            header('Content-type: text/plain; charset=' . $locale->getExportCharset());
            header('Content-Disposition: attachment; filename=Reports.csv');
            header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
            header('Last-Modified: ' . TimeDate::httpTime());
            header('Cache-Control: post-check=0, pre-check=0', false);
            header('Content-Length: ' . mb_strlen($transContent, '8bit'));
        }
        if (!empty($sugar_config['export_excel_compatible'])) {
            if ($stream) {
                print $transContent;
            } else {
                return writeToCSVFile($transContent, $reporter->name);
            }
        } else {
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            if (
                $locale->getExportCharset() == 'UTF-8' &&
                !preg_match('/macintosh|mac os x|mac_powerpc/i', $user_agent)
            ) {
                $BOM = "\xEF\xBB\xBF";
            } else {
                $BOM = ''; // Mac Excel does not support utf-8
            }
            if ($stream) {
                print $BOM . $transContent;
            } else {
                return writeToCSVFile($BOM . $transContent, $reporter->name);
            }
        }
    }
}

/**
 * Writes content to cache file
 *
 * @param String $content
 * @param String $reportName
 * @return string
 */
function writeToCSVFile(string $content, string $reportName)
{
    // This mimics what pdf does
    create_cache_directory('csv');
    $filenamestamp = '_' . date(translate('LBL_PDF_TIMESTAMP', 'Reports'), time());
    $cr = [' ', "\r", "\n", '/'];
    $filename = str_replace($cr, '_', $reportName . $filenamestamp . '.csv');
    $cachefile = sugar_cached('csv/') . basename($filename);
    $fp = sugar_fopen($cachefile, 'w');
    fwrite($fp, $content);
    fclose($fp);
    return $cachefile;
}

/**
 * Writes streamed content to cache file
 *
 * @param \Generator $generator
 * @param string $reportName
 * @param \Localization $locale
 * @param array $sugar_config
 * @return string
 */
function writeToCSVFileStreaming(\Generator $generator, string $reportName, \Localization $locale, array $sugar_config)
{
    create_cache_directory('csv');
    $filenamestamp = '_' . date(translate('LBL_PDF_TIMESTAMP', 'Reports'), time());
    $cr = [' ', "\r", "\n", '/'];
    $filename = str_replace($cr, '_', $reportName . $filenamestamp . '.csv');
    $cachefile = sugar_cached('csv/') . basename($filename);
    $fp = sugar_fopen($cachefile, 'w');

    $BOM = '';
    if (empty($sugar_config['export_excel_compatible'])) {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (
            $locale->getExportCharset() == 'UTF-8' &&
            !preg_match('/macintosh|mac os x|mac_powerpc/i', $user_agent)
        ) {
            $BOM = "\xEF\xBB\xBF";
        }
    }

    if ($BOM) {
        fwrite($fp, $BOM);
    }

    foreach ($generator as $chunk) {
        $transContent = $locale->translateCharset(
            $chunk,
            'UTF-8',
            $locale->getExportCharset(),
            false,
            true
        );
        fwrite($fp, $transContent);
    }

    fclose($fp);
    return $cachefile;
}
