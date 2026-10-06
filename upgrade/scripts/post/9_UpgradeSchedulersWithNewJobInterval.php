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

/**
 * Update job interval value for function::cleanOldRecordLists
 */
class SugarUpgradeUpgradeSchedulersWithNewJobInterval extends UpgradeScript
{
    public $order = 9999;
    public $type = self::UPGRADE_DB;
    const NEW_VALUE = '0::4::*::*::*';
    const OLD_VALUE = '*::*::*::*::*';
    const JOB = 'function::cleanOldRecordLists';

    public function run()
    {
        if (version_compare($this->from_version, '14.2.0', '>=')) {
            return;
        }

        $schedulers = BeanFactory::newBean('Schedulers');
        $table = $schedulers->getTableName();

        $this->log("Update job_interval from " . self::OLD_VALUE . " to " . self::NEW_VALUE . " value in $table table for " . self::JOB . " job.");

        $this->db->getConnection()->update($table, ['job_interval' => self::NEW_VALUE], ['job' => self::JOB]);
    }
}
