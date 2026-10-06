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
 * Update classification metadata for sales_stage_dom dropdown
 */
class SugarUpgradeAdministrationUpdateSalesStageDropdownMetadata extends UpgradeScript
{
    public $order = 9501;
    public $type = self::UPGRADE_CUSTOM;

    /**
     * Dropdown name
     *
     * @var string
     */
    protected $dropdownName = 'sales_stage_dom';

    /**
     * Classification type
     *
     * @var string
     */
    protected $classificationType = 'sales_stage_category';

    /**
     * @return void
     */
    public function run()
    {
        $this->log('Updating classification metadata for sales_stage_dom dropdown ...');

        if (version_compare($this->from_version, '12.0.0', '>=') &&
            version_compare($this->from_version, '25.2.0', '<')) {
            $this->setCustomDropdownClassifications();
        }

        $this->log('Console icon metadata update complete!');
    }

    /**
     * Updates classifications for sales_stage_dom dropdown by custom data
     *
     * @return void
     */
    protected function setCustomDropdownClassifications()
    {
        // Intialize sales stage dropdown data whith custom data
        $dropdownData = $this->getDropdownOptionsData();

        // Intialize sales stage classification based on custom data stored in the database
        $forecastSettings = Forecast::getSettings();
        $closedWonStages = $forecastSettings['sales_stage_won'];
        $closedLostStages = $forecastSettings['sales_stage_lost'];

        // Intialize new dropdown classifier
        // and get classifications for the sales_stage_dom dropdown from metadata
        $classifier = DropdownClassifier::getInstance();
        $classifications = $classifier->getClassificationsForDropdown($this->dropdownName);

        foreach ($dropdownData as $key => $value) {
            if (in_array($key, $closedWonStages)) {
                $classifications[$this->classificationType]['classifications'][$key] = 'Closed Won';
                continue;
            }

            if (in_array($key, $closedLostStages)) {
                $classifications[$this->classificationType]['classifications'][$key] = 'Closed Lost';
                continue;
            }

            $classifications[$this->classificationType]['classifications'][$key] = 'Open';
        }

        // Save the updated classifications back to the metadata with the new classifications
        $classifier->saveClassifications($this->dropdownName, $classifications);
    }

    /**
     * Get dropdown options data from the language file
     *
     * @return array|null
     */
    protected function getDropdownOptionsData(): ?array
    {
        global $locale;

        $appData = return_app_list_strings_language($locale->getAuthenticatedUserLanguage()) ?? null;
        $appData = array_diff_key($appData, DropDownBrowser::$restrictedDropdowns);

        return $appData[$this->dropdownName] ?? null;
    }
}
