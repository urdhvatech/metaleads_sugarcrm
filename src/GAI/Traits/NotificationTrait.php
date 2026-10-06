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

use BeanFactory;
use Doctrine\DBAL\Exception;
use SugarBean;
use SugarQuery;
use SugarQueryException;
use Sugarcrm\Sugarcrm\GAI\Constants\GAIConstants;

trait NotificationTrait
{
    /**
     * Create a notification for the user that the AI Account Summary is generated and ready to be viewed.
     *
     * @param string $userId
     * @param string $recordModule
     * @param string $recordId
     * @param string $severity
     *
     * @return void
     */
    public function createNotificationForSummary(
        string $userId,
        string $recordModule,
        string $recordId,
        string $severity = 'success'
    ): void {
        $targetRecord = BeanFactory::retrieveBean($recordModule, $recordId);

        if (!($targetRecord instanceof SugarBean)) {
            throw new \Exception("[Create][NotificationFor]-> [$recordModule][$recordId] not found");
        }

        $recordName = $targetRecord->name;

        $url = sprintf(
            '<a href="#%s/%s">%s</a>',
            $recordModule,
            $recordId,
            $recordName
        );

        $notification = BeanFactory::newBean('Notifications');
        $notification->name = string_format(translate('LBL_GAI_NOTIFICATION_TITLE'), [$recordName]);
        $notification->description = string_format(translate('LBL_GAI_NOTIFICATION_DESCRIPTION'), [$url]);
        $notification->severity = $severity;
        $notification->parent_name = $recordName;
        $notification->parent_type = $recordModule;
        $notification->parent_id = $recordId;
        $notification->assigned_user_id = $userId;
        $notification->save();
    }

    /**
     * Get the last N active notifications
     *
     * @param int $limit
     * @return array|false
     * @throws SugarQueryException
     */
    public function getNotificationsFromQueue(int $limit = 10)
    {
        $gaiQueue = BeanFactory::newBean(GAIConstants::GAI_QUEUE_NOTIFICAITON_NAME);

        $sq = new SugarQuery();
        $sq->select(['id', 'parent_id', 'parent_type', 'notification_user_id']);
        $sq->from($gaiQueue);
        $sq->where()->equals('deleted', 0);
        $sq->orderBy('date_entered', 'DESC');
        $sq->limit($limit);

        $result = $sq->execute();

        if (!$result || count($result) < 1) {
            return false;
        }

        return $result;
    }

    /**
     * Queue a notification for the user that the AI Account Summary is generated and ready to be viewed.
     *
     * @param string $summaryId
     * @param string $userId
     * @param string $severity
     *
     * @return void|string
     */
    public function queueForNotifyUser(string $summaryId, string $userId, string $severity = 'success')
    {
        if ($this->isNotificationAlreadyQueued($summaryId, GAIConstants::GAI_MODULE_NAME, $userId)) {
            return;
        }

        $notificationQueue = BeanFactory::newBean(GAIConstants::GAI_QUEUE_NOTIFICAITON_NAME);
        $notificationQueue->parent_id = $summaryId;
        $notificationQueue->parent_type = GAIConstants::GAI_MODULE_NAME;
        $notificationQueue->severity = $severity;
        $notificationQueue->notification_user_id = $userId;

        $notificationId = $notificationQueue->save();

        return $notificationId;
    }

    /**
     * Mark a bean as deleted for NotificationQueueGai module
     *
     * @param string $id
     * @return void
     */
    public function deleteNotificationForSummaryFromQueue(string $id): void
    {
        $bean = BeanFactory::retrieveBean(GAIConstants::GAI_QUEUE_NOTIFICAITON_NAME, $id);

        if (!$bean) {
            return;
        }

        $bean->deleted = 1;
        $bean->processed = true;

        $bean->save();
    }

    /**
     * Check if the notification is already queued for the recordId, recordModule and userId.
     *
     * @param string $parentId
     * @param string $parentModule
     * @param string $userId
     *
     * @return bool
     *
     * @throws SugarQueryException
     * @throws Exception
     */
    public function isNotificationAlreadyQueued(string $parentId, string $parentModule, string $userId): bool
    {
        $notificationQueue = BeanFactory::newBean(GAIConstants::GAI_QUEUE_NOTIFICAITON_NAME);
        $query = new SugarQuery();

        $query->select('id');
        $query->from($notificationQueue);

        $query->where()
            ->equals('parent_id', $parentId)
            ->equals('parent_type', $parentModule)
            ->equals('notification_user_id', $userId)
            ->equals('deleted', 0);

        $result = $query->execute();

        if (!$result || safeCount($result) < 1) {
            return false;
        }

        return true;
    }
}
