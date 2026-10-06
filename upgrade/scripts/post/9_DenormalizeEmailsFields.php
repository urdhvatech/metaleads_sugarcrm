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
 * Make Emails fields denormalized to be stored in EmailParticipants
 */
class SugarUpgradeDenormalizeEmailsFields extends UpgradeScript
{
    public $order = 9000;
    public $type = self::UPGRADE_CUSTOM;

    /**
     * @inheritdoc
     */
    public function run()
    {
        if (version_compare($this->from_version, '26.1.0', '>=')) {
            return;
        }

        $this->copyDenormalizedFields();
    }

    /**
     * Copy data between tables
     */
    public function copyDenormalizedFields(): void
    {
        $sql = $this->getCopyDenormalizedFieldsSql();
        $this->log(sprintf('Copy denormalized fields for "%s": %s', $this->db->dbType, $sql));
        $this->db->query($sql);
    }

    /**
     * Get db-specific SQL to copy data between tables
     */
    protected function getCopyDenormalizedFieldsSql(): string
    {
        $fields = [
            'date_sent',
            'team_set_id',
            'state',
            'assigned_user_id',
        ];
        $setSql = implode(', ', array_map(fn($f) => "r.$f = e.$f", $fields));
        $tableSql = 'emails_email_addr_rel r JOIN emails e ON e.id = r.email_id';

        switch ($this->db->dbType) {
            case 'mysql':
                return "UPDATE $tableSql SET $setSql";
            case 'mssql':
                return "UPDATE r SET $setSql FROM $tableSql";
        }

        return // oracle and db2
            "MERGE INTO emails_email_addr_rel r USING emails e ON (r.email_id = e.id) " .
            "WHEN MATCHED THEN UPDATE SET $setSql";
    }
}
