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
use Sugarcrm\Sugarcrm\Cache\Exception;
use Sugarcrm\Sugarcrm\GAI\Constants\GAIConstants;
use Sugarcrm\Sugarcrm\GAI\Traits\Service\DataServiceTrait;
use Sugarcrm\Sugarcrm\GAI\Traits\HelperBulkTrait;
use Sugarcrm\Sugarcrm\GAI\Traits\NotificationTrait;
use Sugarcrm\Sugarcrm\GAI\Exception\EmptySummaryException;

/**
 *
 * Handle the consumption of records from the database queue.
 *
 */
class NotificationScheduler implements RunnableSchedulerJob
{
    use NotificationTrait, HelperBulkTrait, DataServiceTrait;

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
        $notificationsNo = 10;

        try {
            $activeNotifications = $this->getNotificationsFromQueue($notificationsNo);

            if (!$activeNotifications) {
                return $this->job->succeedJob('[GAI]: No active notifications');
            }

            foreach ($activeNotifications as $notification) {
                $gaiNotificationId = $notification['id'];
                $summaryId = $notification['parent_id'];
                $summaryModule = $notification['parent_type'];
                $userId = $notification['notification_user_id'];

                $summary = BeanFactory::retrieveBean($summaryModule, $summaryId);

                if (!($summary instanceof SugarBean)) {
                    $this->deleteNotificationForSummaryFromQueue($gaiNotificationId);

                    continue;
                }

                if ($this->isSummaryBeanIngestSuccess($summary)) {
                    $evalId = $summary->eval_id;
                    try {
                        if ($this->isSummaryReadyOnGaiService($evalId)) {
                            $this->createNotificationForSummary(
                                $userId,
                                $summary->parent_module,
                                $summary->parent_id,
                            );

                            $this->deleteNotificationForSummaryFromQueue($gaiNotificationId);
                        }
                    } catch (\Throwable $error) {
                        $GLOBALS['log']->error("GAI[Notifications][$gaiNotificationId]:" . $error->getMessage());

                        $this->deleteNotificationForSummaryFromQueue($gaiNotificationId);

                        continue;
                    }
                }

                // we have to notify also the other that have visited the record during the summary process
                // about the notification is completed
                if ($this->isSummaryBeanCompleted($summary)) {
                    $this->createNotificationForSummary(
                        $userId,
                        $summary->parent_module,
                        $summary->parent_id,
                    );

                    $this->deleteNotificationForSummaryFromQueue($gaiNotificationId);
                }
            }

            return $this->job->succeedJob('[GAI][Notifications][Scheduler]: Success');
        } catch (\Throwable $e) {
            $GLOBALS['log']->error('[GAI][Notifications][Scheduler]: ' . $e->getMessage());

            return $this->job->failJob('[GAI][Notifications][Scheduler]: ' . $e->getMessage());
        }
    }
}
