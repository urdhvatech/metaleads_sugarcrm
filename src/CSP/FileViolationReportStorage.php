<?php

declare(strict_types=1);
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

namespace Sugarcrm\Sugarcrm\CSP;

class FileViolationReportStorage implements ViolationReportStorage
{
    private string $filePath;
    private array $uniqueFieldsMap = [
        'script-src-elem' => [
            'blocked-uri',
            'script-sample',
        ],
        'script-src' => [
            'script-sample',
            'source-file',
        ],
        'script-src-attr' => [
            'blocked-uri',
            'script-sample',
            'source-file',
        ],
        'default' => [
            'blocked-uri',
            'violated-directive',
        ],
    ];

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    public function save(array $report): bool
    {
        $report = $this->sanitize($report);
        $reports = $this->loadReports();

        foreach ($reports as $existingReport) {
            if ($this->isDuplicate($existingReport, $report)) {
                return false;
            }
        }

        $reports[] = $report;
        $this->saveReports($reports);
        return true;
    }

    private function saveReports(array $reports): void
    {
        $file = fopen($this->filePath, 'c+');
        if (!$file) {
            throw new \RuntimeException('Unable to open file for writing');
        }

        try {
            if (!flock($file, LOCK_EX)) {
                throw new \RuntimeException('Unable to lock file for writing');
            }

            // clear the file
            ftruncate($file, 0);
            rewind($file);

            fwrite($file, json_encode($reports, JSON_PRETTY_PRINT));
        } finally {
            // release the lock
            flock($file, LOCK_UN);
            fclose($file);
        }
    }

    private function loadReports(): array
    {
        if (!file_exists($this->filePath)) {
            return [];
        }

        $content = file_get_contents($this->filePath);
        return $content ? json_decode($content, true) : [];
    }

    /**
     * @param array $existingReport
     * @param array $newReport
     * @return bool
     */
    private function isDuplicate(array $existingReport, array $newReport): bool
    {
        $violatedDirective = $newReport['violated-directive'];

        $uniqueFields = $this->uniqueFieldsMap[$violatedDirective] ?? $this->uniqueFieldsMap['default'];
        foreach ($uniqueFields as $field) {
            if (($existingReport[$field] ?? null) !== ($newReport[$field] ?? null)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array $report
     * @return array
     */
    private function sanitize(array $report): array
    {
        $sanitizedReport = [];
        if (empty($report['violated-directive'])) {
            throw new \DomainException('Missing violated-directive');
        }
        foreach ($report as $key => $value) {
            if (!is_string($key) || !preg_match('/^[a-z-]+$/i', $key)) {
                throw new \DomainException('Invalid report key');
            }
            $sanitizedReport[$key] = bin2hex((string) $value);
        }
        return $sanitizedReport;
    }
}
