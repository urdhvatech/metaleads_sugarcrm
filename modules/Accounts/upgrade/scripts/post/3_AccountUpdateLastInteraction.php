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

class SugarUpgradeAccountUpdateLastInteraction extends UpgradeScript
{
    /**
     * @var int
     */
    public $order = 2196;

    /**
     * @var int
     */
    public $type = self::UPGRADE_DB;

    /**
     * @return void
     */
    public function run()
    {
        $targetVersion = '25.1.0';

        if (version_compare($this->from_version, $targetVersion, '>=')) {
            return;
        }

        $data = [
            'module' => 'Accounts',
            'related_field' => 'status',
            'related_value' => ['Held'],
            'date_field' => 'date_end',
            'interaction_types' => ['last'],
            'interaction_modules' => ['Calls', 'Meetings'],
        ];

        $adminUser = BeanFactory::newBean('Users')->getSystemUser();

        /** @var SchedulersJob $job */
        $job = BeanFactory::newBean('SchedulersJobs');
        $job->name = 'Last interaction intialization';
        $job->target = 'class::SugarJobInitInteractions';
        $job->retry_count = 0;
        $job->data = json_encode($data);
        $job->job_group = 'upgrade_to_' . $targetVersion;
        $job->assigned_user_id = $adminUser->id;

        $queue = new SugarJobQueue();
        $queue->submitJob($job);
    }
}
