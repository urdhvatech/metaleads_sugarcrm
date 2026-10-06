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

use Sugarcrm\Sugarcrm\Entitlements\SubscriptionManager;
use Sugarcrm\Sugarcrm\Entitlements\Subscription;

/**
 * Upgrade script for Market license type.
 */
class SugarUpgradeSetupUsersMarketLicenseType extends UpgradeScript
{
    public $order = 9100;
    public $type = self::UPGRADE_CUSTOM;

    /**
     * {@inheritDoc}
     */
    public function run()
    {
        if (version_compare($this->from_version, '25.1.0', '>=')) {
            return;
        }

        // check if having MARKET license type
        if (!SubscriptionManager::instance()->hasSubscription(Subscription::SUGAR_MARKET_KEY)) {
            return;
        }

        try {
            $adminUser = BeanFactory::newBean('Users')->getSystemUser();
            $adminUser->updateUsersMarketLicenseType();
        } catch (Exception $e) {
            $this->log('Failed to update Market license type, Error: ' . $e->getMessage());
        }
    }
}
