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

class SugarUpgradeUsersTableUpdate extends UpgradeScript
{
    public $order = 9000;
    public $type = self::UPGRADE_DB;
    public function run() : void
    {
        if (version_compare($this->from_version, '14.2.0', '<')) {
            $this->updateUsersTable();
        }
    }

    public function updateUsersTable() : void
    {
        if (isMts()) {
            $this->log('Updating users table for is_group field');
            $this->db->query('UPDATE users SET is_group = 0 WHERE is_group IS NULL');
            $this->db->query('ALTER TABLE users MODIFY COLUMN is_group tinyint(1) DEFAULT 0 NOT NULL');
        }
    }
}
