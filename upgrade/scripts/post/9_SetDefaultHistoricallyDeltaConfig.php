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
 * Insert default Historically Delta config
 */
class SugarUpgradeSetDefaultHistoricallyDeltaConfig extends UpgradeScript
{
    public $order = 9601;

    public $type = self::UPGRADE_DB;

    /**
     * Execute upgrade tasks
     *
     * @see UpgradeScript::run()
     */
    public function run()
    {
        $this->log('Updating default Historically Delta config...');

        if (version_compare($this->from_version, '25.2.0', '>=')) {
            return;
        }

        $this->updateHistoricallyDeltaConfig();


        $this->log('Finished default Historically Delta config...');
    }

    /**
     * Checks if Historically Delta config exists and adds it if missing
     *
     * @return void
     */
    protected function updateHistoricallyDeltaConfig()
    {
        $administration = new Administration();
        $currentConfig = $administration->retrieveSettings('delta', false);

        if (empty($currentConfig)) {
            return;
        }

        if (!empty($administration->settings['delta_enabled_modules']) &&
            !empty($administration->settings['delta_modules_data'])) {
            return;
        }

        $this->addDefaultSettings();
    }

    /**
     * Add default settings for Historically Delta
     *
     * @return void
     */
    protected function addDefaultSettings()
    {
        $admin = BeanFactory::newBean('Administration');

        $defaultConfig = $this->getDefaultConfig();

        foreach ($defaultConfig as $name => $value) {
            $admin->saveSetting('delta', $name, $value, 'base');
        }
    }

    /**
     * Get default config for Historically Delta
     *
     * @return array
     */
    protected function getDefaultConfig(): array
    {
        $defaultEnabledModulesConfig = [
            'Opportunities' => [
                'date_closed' => ['enabled' => true],
                'sales_stage' => ['enabled' => true],
                'amount' => ['enabled' => true],
            ],
        ];

        $defaultEnabledModules = json_encode(array_keys($defaultEnabledModulesConfig));
        $defaultEnabledModulesConfig = json_encode($defaultEnabledModulesConfig);

        $defaultConfig = [
            'modules_data' => $defaultEnabledModulesConfig,
            'enabled_modules' => $defaultEnabledModules,
        ];

        return $defaultConfig;
    }
}
