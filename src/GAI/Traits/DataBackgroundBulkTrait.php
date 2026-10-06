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

namespace Sugarcrm\Sugarcrm\GAI\Traits;

use SugarBean;
use BeanFactory;
use SugarConfig;
use Doctrine\DBAL\Exception;
use UnexpectedValueException;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Container\ContainerExceptionInterface;
use SugarJobQueue;
use SugarAutoLoader;
use Sugarcrm\Sugarcrm\GAI\Constants\GAIConstants;
use Sugarcrm\Sugarcrm\GAI\Traits\HelperBulkTrait;
use Sugarcrm\Sugarcrm\GAI\Traits\SummaryTrait;
use Sugarcrm\Sugarcrm\GAI\Traits\Service\DataServiceBulkTrait;
use Sugarcrm\Sugarcrm\GAI\Traits\DataStructure\DataStructureBulkTrait;
use Sugarcrm\Sugarcrm\GAI\Traits\NotificationTrait;
use Sugarcrm\Sugarcrm\GAI\Schedulers\IngestDataScheduler;

trait DataBackgroundBulkTrait
{
    use HelperBulkTrait, SummaryTrait, DataServiceBulkTrait, DataStructureBulkTrait, NotificationTrait;

    /**
     * Ingest the data in the background.
     *
     * @param string $parentId The ID of the parent record.
     * @param string $parentModule The module name of the parent record.
     * @param string $userId - this willbe executed on a job, we keep track the user that start the summary
     * @param bool $notify If a notification will be created for the user about the summary process
     *
     * @return void
     */
    protected function backgroundDataIngest(
        string $parentId,
        string $parentModule,
        array $gaiBulkConfig,
        string $userId = '',
        bool $notify = false
    ) {
        $summaryBean = null;
        $changesDetected = false;

        try {
            $retrieveSummaryCriteria = $this->getSummaryCriteriaWithParams([
                ['field' => 'is_translate', 'operator' => 'equals', 'value' => false],
                ['field' => 'status', 'operator' => 'equals', 'value' => GAIConstants::SUMZ_STATUS_READY_FOR_INGEST],
            ]);

            $summaryBean = $this->retrieveSummaryBeanByRelateRecord(
                $parentId,
                $parentModule,
                $retrieveSummaryCriteria
            );

            if (!$summaryBean) {
                return;
            }

            //prevent other schedulers to process the same record
            $summaryBean->status = GAIConstants::SUMZ_STATUS_PENDING;
            $summaryBean->processed = true;
            $summaryBean->save();

            $this->ingestData(
                $parentId,
                $parentModule,
                $parentId,
                $gaiBulkConfig,
                true,
                $changesDetected,
                null,
                null
            );
        } catch (\Exception $e) {
            $GLOBALS['log']->error("GAI[Scheduler][First][IngestData][$parentId]". $e->getMessage());

            if ($summaryBean instanceof SugarBean) {
                $summaryBean->status = GAIConstants::SUMZ_STATUS_ERROR;
                $summaryBean->error_message = json_encode(['errorMessage' => $e->getMessage(), 'recordId' => $parentId]);
                $summaryBean->deleted = 1;
                $summaryBean->save();
            }

            throw $e;
        }

        try {
            $translate = false;
            $language = $summaryBean->language;
            $this->confirmIngestDataSent($summaryBean, $parentId, $parentModule, $language, $translate);
        } catch (\Exception $e) {
            $GLOBALS['log']->error("GAI[Scheduler][First][ConfirmIngestDataSent][$parentId]". $e->getMessage());

            if ($summaryBean instanceof SugarBean) {
                $summaryBean->status = GAIConstants::SUMZ_STATUS_ERROR;
                $summaryBean->error_message = json_encode(['errorMessage' => $e->getMessage(), 'recordId' => $parentId]);
                $summaryBean->deleted = 1;
                $summaryBean->save();
            }

            throw $e;
        }

        if ($notify && $userId) {
            $this->queueForNotifyUser($summaryBean->id, $userId);
        }
    }

    /**
     * Create an anonymous scheduler job to ingest the data.
     *
     * @param string $id
     * @param string $module
     * @param string $userId
     * @param bool $notify
     * @return void
     *
     * @throws Exception
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function createIngestScheduler(string $id, string $module, string $userId, bool $notify = true)
    {
        $jobClass = SugarAutoLoader::customClass(IngestDataScheduler::class);
        $jobExec = "class::\\{$jobClass}";

        $data = [
            'module' => $module,
            'id' => $id,
            'userId' => $userId,
            'notify' => $notify,
        ];

        $job = BeanFactory::newBean('SchedulersJobs');
        $job->name = sprintf('GAI Ingest Data [%s][%s]', $module, $id);
        $job->target = $jobExec;
        $job->data = json_encode($data);
        $job->job_delay = 60;
        $job->assigned_user_id = $userId;

        $queue = new SugarJobQueue();
        $queue->submitJob($job);
    }

    /**
     * Get the condition to exclude emails linked to multiple records in the same module.
     * Emails associated with more than one record in the given module will be excluded.
     *
     * @param string $moduleName The module name to check for duplicate emails.
     * @return string The SQL condition to exclude records with duplicate emails.
     * @throws UnexpectedValueException If the database type is not supported.
     */
    public function getDuplicateEmailCondition(string $moduleName): string
    {
        $dbType = SugarConfig::getInstance()->get('dbconfig')['db_type'];

        // MySQL Optimization: Using `JOIN` instead of `EXISTS`
        // `JOIN` is faster than a nested `EXISTS` subquery in MySQL.
        // `LIMIT 1` ensures early exit after finding the first match.
        // Avoids `GROUP BY COUNT()` which forces full table scans.
        if ($dbType === 'mysql') {
            return " AND NOT EXISTS (
                        SELECT 1
                        FROM emails_beans ebx
                        JOIN emails_beans ebx2
                            ON ebx.email_id = ebx2.email_id
                            AND ebx.bean_module = '$moduleName'
                            AND ebx2.bean_module = '$moduleName'
                            AND ebx.bean_id <> ebx2.bean_id
                        WHERE ebx.email_id = emails.id
                            AND ebx.deleted = 0
                            AND ebx2.deleted = 0
                        LIMIT 1
                    ) ";
        } elseif ($dbType === 'ibm_db2') {
            return "AND NOT EXISTS (
                        SELECT 1
                        FROM (
                            SELECT email_id
                            FROM emails_beans
                            WHERE bean_module = '$moduleName'
                                AND deleted = 0
                            GROUP BY email_id
                            HAVING COUNT(DISTINCT bean_id) > 1
                        ) AS subquery
                        WHERE subquery.email_id = emails.id
                    ) ";
        } elseif ($dbType === 'oci8') {
            return " AND NOT EXISTS (
                        SELECT 1
                        FROM (
                            SELECT email_id
                            FROM emails_beans
                            WHERE bean_module = '$moduleName'
                                AND deleted = 0
                            GROUP BY email_id
                            HAVING COUNT(DISTINCT bean_id) > 1
                        ) WHERE email_id = emails.id
                        AND ROWNUM = 1
                    ) ";
        } elseif ($dbType === 'mssql') {
            return " AND NOT EXISTS (
                        SELECT TOP 1 1
                        FROM (
                            SELECT email_id
                            FROM emails_beans
                            WHERE bean_module = '$moduleName'
                                AND deleted = 0
                            GROUP BY email_id
                            HAVING COUNT(DISTINCT bean_id) > 1
                        ) AS subquery
                        WHERE subquery.email_id = emails.id
                    ) ";
        } else {
            throw new UnexpectedValueException(
                sprintf('The provided DB type "%s" is not supported', $dbType)
            );
        }
    }
}
