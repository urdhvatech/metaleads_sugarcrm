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
require_once 'include/connectors/sources/ext/rest/rest.php';

/**
 * Class to connect Sell and Market
 */
class ext_rest_salesfusion extends ext_rest
{

    /**
     * @var string Market Organization name
     */
    public $orgName;


    /**
     * @var string Market view link
     */
    public $iframeUrl;

    protected array $hiddenFields = [
        'iframe_url' => true,
    ];

    /**
     * Overrides parent __construct to set new variable defaults
     */
    public function __construct()
    {
        parent::__construct();

        $this->_has_testing_enabled = false;
        $this->_enable_in_wizard = false;
        $this->_enable_in_hover = true;

        $this->orgName = $this->getProperty("organization_name");
        $this->iframeUrl = $this->getProperty("iframe_url");

        $this->_enable_in_admin_display = hasMarketLicense();
        $this->_enable_in_admin_properties = hasMarketLicense();
    }

    /**
     * {@inheritDoc}
     */
    public function getItem($args = [], $module = null)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getList($args = [], $module = null)
    {
    }

    /**
     * Restore Market settings
     * @param array $settings
     * @return void
     */
    public function restoreSettings(array $settings): void
    {
        $properties = $this->getProperties();
        $properties['organization_name'] = $settings['org_name'];
        $this->setProperties($properties);
        $this->saveConfig();

        $this->setSourceActiveModules($settings['modules']);
    }

    /**
     * Set active modules for a source, will update display_config.php
     * @param array $modules
     * @param string $sourceName
     * @return void
     */
    protected function setSourceActiveModules(array $modules, string $sourceName = 'ext_rest_salesfusion'): void
    {
        $modules_sources = [];
        require CONNECTOR_DISPLAY_CONFIG_FILE;
        foreach ($modules_sources as $module => &$sources) {
            if (in_array($module, $modules)) {
                $sources[$sourceName] = $sourceName;
            } else {
                unset($sources[$sourceName]);
                if (empty($sources)) {
                    unset($modules_sources[$module]);
                }
            }
        }
        if (!write_array_to_file('modules_sources', $modules_sources, CONNECTOR_DISPLAY_CONFIG_FILE)) {
            $GLOBALS['log']->fatal('Cannot write $modules_sources to ' . CONNECTOR_DISPLAY_CONFIG_FILE);
        }
    }
}
