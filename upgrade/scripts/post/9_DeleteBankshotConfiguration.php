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
 * Delete any configuration related to bankshot.
 */
class SugarUpgradeDeleteBankshotConfiguration extends UpgradeDBScript
{
    public $order = 9999;
    public $version = '25.1.0';

    /**
     * Deletes all rows from the config table where the category is 'sugar_connect'.
     */
    public function run()
    {
        if (version_compare($this->from_version, $this->version, '<')) {
            $sql = "DELETE FROM config WHERE category='sugar_connect'";
            $this->executeUpdate($sql);
        }
    }
}
