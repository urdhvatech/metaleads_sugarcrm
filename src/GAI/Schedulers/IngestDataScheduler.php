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

namespace Sugarcrm\Sugarcrm\GAI\Schedulers;

use SugarBean;
use BeanFactory;
use SchedulersJob;
use RunnableSchedulerJob;
use Sugarcrm\Sugarcrm\GAI\Constants\GAIConstants;
use Sugarcrm\Sugarcrm\GAI\Traits\DataBackgroundBulkTrait;
use Sugarcrm\Sugarcrm\GAI\Traits\SummaryTrait;
use Sugarcrm\Sugarcrm\GAI\Traits\HelperBulkTrait;
use Sugarcrm\Sugarcrm\GAI\Helper;

/**
 *
 * Handle the consumption of records from the database queue.
 *
 */
class IngestDataScheduler implements RunnableSchedulerJob
{
    use DataBackgroundBulkTrait, SummaryTrait;

    /**
     * @var \SchedulersJob
     */
    protected $job;

    /**
     * @param String $engineClassName
     */
    public function __construct()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function setJob(SchedulersJob $job)
    {
        $this->job = $job;
    }

    /**
     * {@inheritdoc}
     */
    public function run($data)
    {
        $data = json_decode($data, true);
        $notify = true;

        // The passed in data is expected to contain the module name and id
        if (empty($data) || !is_array($data)) {
            return $this->job->failJob('GAI Ingest Data Job: Invalid Data parameter');
        }

        if (array_key_exists('module', $data) === false) {
            return $this->job->failJob('GAI Ingest Data Job: Data missing module parameter');
        }

        if (array_key_exists('id', $data) === false) {
            return $this->job->failJob('GAI Ingest Data Job: Data missing id parameter');
        }

        if (array_key_exists('userId', $data) === false) {
            return $this->job->failJob('GAI Ingest Data Job: Data missing userId parameter');
        }

        if (array_key_exists('notify', $data) === true && $data['notify'] === false) {
            $notify = false;
        }

        $module = $data['module'];
        $id = $data['id'];
        $userId = $data['userId'];

        $msg = sprintf('GAI[Scheduler][First][Ingest][%s][%s]', $id, $module);

        try {
            $gaiBulkConfig = Helper::resolveConfigBulk($module);

            $this->backgroundDataIngest($id, $module, $gaiBulkConfig, $userId, $notify);

            return $this->job->succeedJob($msg . '[Success]');
        } catch (\Throwable $e) {
            $retrieveSummaryCriteria = $this->getSummaryCriteriaWithParams([
                ['field' => 'is_translate', 'operator' => 'equals', 'value' => false],
            ]);

            $summaryBean = $this->retrieveSummaryBeanByRelateRecord(
                $id,
                $module,
                $retrieveSummaryCriteria
            );

            if ($summaryBean instanceof SugarBean) {
                $summaryBean->status = GAIConstants::SUMZ_STATUS_ERROR;
                $summaryBean->error_message = json_encode(['errorMessage' => $e->getMessage()]);
                $summaryBean->deleted = 1;
                $summaryBean->save();
            }

            $GLOBALS['log']->error($msg . ': ' . $e->getMessage());

            $falsePositiveErrorStatus = [402, 422];

            if (in_array($e->getCode(), $falsePositiveErrorStatus)) {
                $falseSuccessMessage = $msg . ': ' . ($e->getCode() === 402 ? '[NotEnoughTokens]' : '[NotEnoughData]');
                return $this->job->succeedJob($falseSuccessMessage);
            } else {
                return $this->job->failJob($msg . ': ' . $e->getMessage());
            }
        }
    }
}
