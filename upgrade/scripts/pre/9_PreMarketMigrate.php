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

use Sugarcrm\Sugarcrm\PackageManager\PackageManager;

class SugarUpgradePreMarketMigrate extends UpgradeScript
{
    public $order = 2099;

    public $upgradeHistory;

    public $packageManager;

    public function run()
    {
        $this->packageManager = new PackageManager();
        $this->upgradeHistory = $this->retrieveUpgradeHistoryByIdName('ext_rest_salesfusion');

        if (!$this->shouldRun()) {
            return;
        }

        try {
            $this->saveMarketSettings();
            if ($this->upgradeHistory && $this->upgradeHistory->status === 'installed') {
                try {
                    // Turn off packageScan to avoid calling
                    // SweetTranslator::patch() while uninstalling sugar market module -
                    // While upgrading to 25.2 version it calls ParserFactory::create() outdated method
                    // BR-12763
                    $packageScanConfig = $GLOBALS['sugar_config']['moduleInstaller']['packageScan'] ?? null;

                    if ($packageScanConfig) {
                        SugarConfig::getInstance()->clearCache('moduleInstaller.packageScan');
                        $GLOBALS['sugar_config']['moduleInstaller']['packageScan'] = false;
                    }

                    // unisntalling pre-2.1 package will delete custom connector files
                    $shouldBackup = version_compare($this->upgradeHistory->version, '2.1', '<');
                    $backupDir = 'cache/upgrades/connector_backup';
                    if ($shouldBackup) {
                        $this->saveConnectorFiles($backupDir);
                    }
                    $this->uninstallPackage();
                    if ($shouldBackup) {
                        $this->restoreConnectorFiles($backupDir);
                    }
                    $this->log('Sugar Market package: deleting custom packages');
                    $this->packageManager->deletePackage($this->upgradeHistory);
                } catch (Exception $e) {
                    $this->log('Sugar Market uninstall package error: ' . $e->getMessage());
                    $this->deleteUpgradeHistory($this->upgradeHistory);
                } finally {
                    if (isset($packageScanConfig) && $packageScanConfig) {
                        $GLOBALS['sugar_config']['moduleInstaller']['packageScan'] = $packageScanConfig;
                        SugarConfig::getInstance()->clearCache('moduleInstaller.packageScan');
                    }
                }
            }
            $this->deleteFiles();
            $this->deleteCustomFiles();
            $this->renameTables();
        } catch (Exception $e) {
            $this->log('Sugar Market pre-upgrade error: ' . $e->getMessage());
        }
    }

    /**
     * Delete upgrade history record
     * @param SugarBean $upgradeHistory
     * @return void
     */
    protected function deleteUpgradeHistory(SugarBean $upgradeHistory): void
    {
        try {
            $this->log('Sugar Market package delete upgrade history: ' . $upgradeHistory->id);
            $qb = DBManagerFactory::getConnection()->createQueryBuilder();
            $qb->update('upgrade_history')
                ->set('status', $qb->expr()->literal('staged'))
                ->set('deleted', $qb->expr()->literal('1'))
                ->where($qb->expr()->eq('id', $qb->expr()->literal($upgradeHistory->id)));
            $qb->executeQuery();
            $this->log('Sugar Market package upgrade history deleted');
        } catch (Exception $e) {
            $this->log('Sugar Market package delete upgrade history error: ' . $e->getMessage());
        }
    }

    /**
     * Retrieve upgrade history by id_name
     * @param string $idName
     * @return SugarBean|null
     * @throws SugarQueryException
     */
    protected function retrieveUpgradeHistoryByIdName(string $idName): ?SugarBean
    {
        $upgradeHistory = new UpgradeHistory();
        $query = new SugarQuery();
        $query->from($upgradeHistory);
        $query->where()->equals('id_name', $idName)->queryAnd()->equals('status', 'installed');
        $query->limit(1);
        $result = $upgradeHistory->fetchFromQuery($query);
        if (!empty($result)) {
            return array_shift($result);
        }
        return null;
    }

    /**
     * Determines if this upgrader should run
     *
     * @return bool true if the upgrader should run
     */
    protected function shouldRun()
    {
        return ($this->upgradeHistory instanceof SugarBean ||
                $this->db->tableExists('sf_dialogs_contacts_c') ||
                file_exists('custom/clients/base/api/SalesFusionApi.php')) &&
            version_compare($this->from_version, '25.1.0', '<');
    }

    /**
     * Save connector files to a temporary location
     * @param string $dir Backup dir
     * @return void
     */
    protected function saveConnectorFiles(string $dir): void
    {
        $this->log('Backup connector files');
        $connectorDir = 'custom/modules/Connectors/connectors';

        foreach (['sources', 'formatters'] as $source) {
            $sourceDir = "$connectorDir/$source";
            if (is_dir($sourceDir)) {
                $this->log('Backup connector dir: ' . $sourceDir);
                $destDir = "$dir/$source";
                if (mkdir_recursive($destDir, true)) {
                    if (!copy_recursive($sourceDir, $destDir)) {
                        $this->log('Failed to backup connector dir: ' . $sourceDir);
                    }
                } else {
                    $this->log('Failed to create directory: ' . $destDir);
                }
            }
        }
    }

    /**
     * Restore connector files from a temporary location
     * @param string $dir Backup dir
     * @return void
     */
    protected function restoreConnectorFiles(string $dir): void
    {
        $this->log('Restore connector files');
        $connectorDir = 'custom/modules/Connectors/connectors';

        foreach (['sources', 'formatters'] as $source) {
            $backupDir = "$dir/$source";
            if (is_dir($backupDir)) {
                $this->log('Restore connector dir: ' . $backupDir);
                if (copy_recursive($backupDir, "$connectorDir/$source")) {
                    $this->log('Delete backup directory: ' . $backupDir);
                    rmdir_recursive($backupDir);
                } else {
                    $this->log('Failed to restore from backup directory: ' . $backupDir);
                }
            }
            // remove market directory
            $marketDir = "$connectorDir/$source/ext/rest/salesfusion";
            if (is_dir($marketDir)) {
                $this->log('Delete Market connector dir: ' . $marketDir);
                rmdir_recursive($marketDir);
            }
        }

        // remove backup directory if empty
        if (is_dir($dir) && count(glob($dir . '/*')) === 0) {
            $this->log('Delete backup directory: ' . $dir);
            rmdir_recursive($dir);
        }
    }

    /**
     * Save Market settings to the temporary file, will be used in PostMarketMigrate script
     * @return void
     */
    protected function saveMarketSettings(): void
    {
        $settingsFile = 'cache/upgrades/settings.bak';
        $source = SourceFactory::getSource('ext_rest_salesfusion');
        $settings = [
            'org_name' => $source ? $source->getProperty('organization_name') : '',
            'modules' => $source ? array_keys($source->getMapping()['beans']) : [],
        ];
        $data = '<?php $settings = ' . var_export($settings, true) . ';';
        file_put_contents($settingsFile, $data);
        $this->log('Saved Market settings');
    }

    /**
     * Uninstall the package
     * @return void
     */
    protected function uninstallPackage(): void
    {
        $this->log('Sugar Market package: uninstalling custom packages');
        $removeTables = false;
        $this->packageManager->uninstallPackage($this->upgradeHistory, $removeTables);
    }

    /**
     * List of files from the Market package which need to be deleted
     */
    private $deleteFiles = [
        'custom/themes/default/images/icon_Sf_Dialogs_32.png',
        'custom/themes/default/images/icon_Sf_EventManagement_32.png',
        'custom/themes/default/images/icon_Sf_webActivity_32.png',
        'custom/themes/default/images/icon_Sf_WebActivityDetail_32.png',
        'custom/clients/base/api/SalesFusionApi.php',
        'custom/modules/Connectors/connectors/sources/ext/rest/salesfusion/salesfusion.php',
        'custom/Extension/application/Ext/Include/ext_rest_salesfusion.php',
        'custom/Extension/modules/Contacts/Ext/clients/base/views/record/recContactAddButtons.php',
        'custom/Extension/modules/Leads/Ext/clients/base/views/record/recLeadsAddButtons.php',
        'custom/metadata/sf_webactivity_accountsMetaData.php',
        'custom/metadata/sf_webactivity_leadsMetaData.php',
        'custom/metadata/sf_webactivity_contactsMetaData.php',
        'custom/metadata/sf_webactivitydetail_sf_webactivityMetaData.php',
        'custom/metadata/sf_dialogs_contactsMetaData.php',
        'custom/metadata/sf_dialogs_leadsMetaData.php',
        'custom/metadata/sf_eventmanagement_contactsMetaData.php',
        'custom/metadata/sf_eventmanagement_leadsMetaData.php',
        'modules/sf_Dialogs/sf_Dialogs.php',
        'modules/sf_Dialogs/sf_Dialogs_sugar.php',
        'modules/sf_EventManagement/sf_EventManagement_sugar.php',
        'modules/sf_webActivity/sf_webActivity_sugar.php',
        'modules/sf_WebActivityDetail/sf_WebActivityDetail_sugar.php',
    ];

    /**
     * List of custom files from the Market package which need to be deleted
     */
    private $deleteCustomFiles = [
        'modules/sf_Dialogs/sf_Dialogs.php',
        'modules/sf_EventManagement/sf_EventManagement.php',
        'modules/sf_webActivity/sf_webActivity.php',
        'modules/sf_WebActivityDetail/sf_WebActivityDetail.php',
        'modules/sf_Dialogs/clients/base/filters/basic/basic.php',
        'modules/sf_Dialogs/clients/base/filters/default/default.php',
        'modules/sf_Dialogs/clients/base/layouts/detail/detail.php',
        'modules/sf_Dialogs/clients/base/layouts/edit/edit.php',
        'modules/sf_Dialogs/clients/base/menus/header/header.php',
        'modules/sf_Dialogs/clients/base/views/activity-card-detail/activity-card-detail.php',
        'modules/sf_Dialogs/clients/base/views/list/list.php',
        'modules/sf_Dialogs/clients/base/views/massupdate/massupdate.php',
        'modules/sf_Dialogs/clients/base/views/record/record.php',
        'modules/sf_Dialogs/clients/base/views/subpanel-list/subpanel-list.php',
        'modules/sf_Dialogs/clients/mobile/layouts/detail/detail.php',
        'modules/sf_Dialogs/clients/mobile/layouts/edit/edit.php',
        'modules/sf_Dialogs/clients/mobile/layouts/list/list.php',
        'modules/sf_Dialogs/clients/mobile/views/detail/detail.php',
        'modules/sf_Dialogs/clients/mobile/views/edit/edit.php',
        'modules/sf_Dialogs/clients/mobile/views/list/list.php',
        'modules/sf_Dialogs/clients/mobile/views/search/search.php',
        'modules/sf_Dialogs/dashboards/focus/focus-dashboard.php',
        'modules/sf_Dialogs/language/bg_BG.lang.php',
        'modules/sf_Dialogs/language/ca_ES.lang.php',
        'modules/sf_Dialogs/language/cs_CZ.lang.php',
        'modules/sf_Dialogs/language/da_DK.lang.php',
        'modules/sf_Dialogs/language/de_DE.lang.php',
        'modules/sf_Dialogs/language/en_UK.lang.php',
        'modules/sf_Dialogs/language/en_us.lang.php',
        'modules/sf_Dialogs/language/es_ES.lang.php',
        'modules/sf_Dialogs/language/et_EE.lang.php',
        'modules/sf_Dialogs/language/fr_FR.lang.php',
        'modules/sf_Dialogs/language/he_IL.lang.php',
        'modules/sf_Dialogs/language/hu_HU.lang.php',
        'modules/sf_Dialogs/language/it_it.lang.php',
        'modules/sf_Dialogs/language/ja_JP.lang.php',
        'modules/sf_Dialogs/language/lt_LT.lang.php',
        'modules/sf_Dialogs/language/lv_LV.lang.php',
        'modules/sf_Dialogs/language/nb_NO.lang.php',
        'modules/sf_Dialogs/language/nl_NL.lang.php',
        'modules/sf_Dialogs/language/pl_PL.lang.php',
        'modules/sf_Dialogs/language/pt_BR.lang.php',
        'modules/sf_Dialogs/language/pt_PT.lang.php',
        'modules/sf_Dialogs/language/ro_RO.lang.php',
        'modules/sf_Dialogs/language/ru_RU.lang.php',
        'modules/sf_Dialogs/language/sr_RS.lang.php',
        'modules/sf_Dialogs/language/sv_SE.lang.php',
        'modules/sf_Dialogs/language/tr_TR.lang.php',
        'modules/sf_Dialogs/language/zh_CN.lang.php',
        'modules/sf_Dialogs/metadata/subpanels/default.php',
        'modules/sf_Dialogs/metadata/SearchFields.php',
        'modules/sf_Dialogs/metadata/dashletviewdefs.php',
        'modules/sf_Dialogs/metadata/detailviewdefs.php',
        'modules/sf_Dialogs/metadata/editviewdefs.php',
        'modules/sf_Dialogs/metadata/listviewdefs.php',
        'modules/sf_Dialogs/metadata/metafiles.php',
        'modules/sf_Dialogs/metadata/popupdefs.php',
        'modules/sf_Dialogs/metadata/quickcreatedefs.php',
        'modules/sf_Dialogs/metadata/searchdefs.php',
        'modules/sf_Dialogs/metadata/studio.php',
        'modules/sf_Dialogs/vardefs.php',
        'modules/sf_EventManagement/clients/base/filters/basic/basic.php',
        'modules/sf_EventManagement/clients/base/filters/default/default.php',
        'modules/sf_EventManagement/clients/base/layouts/detail/detail.php',
        'modules/sf_EventManagement/clients/base/layouts/edit/edit.php',
        'modules/sf_EventManagement/clients/base/menus/header/header.php',
        'modules/sf_EventManagement/clients/base/views/activity-card-detail/activity-card-detail.php',
        'modules/sf_EventManagement/clients/base/views/list/list.php',
        'modules/sf_EventManagement/clients/base/views/massupdate/massupdate.php',
        'modules/sf_EventManagement/clients/base/views/record/record.php',
        'modules/sf_EventManagement/clients/base/views/subpanel-list/subpanel-list.php',
        'modules/sf_EventManagement/clients/mobile/layouts/detail/detail.php',
        'modules/sf_EventManagement/clients/mobile/layouts/edit/edit.php',
        'modules/sf_EventManagement/clients/mobile/layouts/list/list.php',
        'modules/sf_EventManagement/clients/mobile/views/detail/detail.php',
        'modules/sf_EventManagement/clients/mobile/views/edit/edit.php',
        'modules/sf_EventManagement/clients/mobile/views/list/list.php',
        'modules/sf_EventManagement/clients/mobile/views/search/search.php',
        'modules/sf_EventManagement/dashboards/focus/focus-dashboard.php',
        'modules/sf_EventManagement/language/bg_BG.lang.php',
        'modules/sf_EventManagement/language/ca_ES.lang.php',
        'modules/sf_EventManagement/language/cs_CZ.lang.php',
        'modules/sf_EventManagement/language/da_DK.lang.php',
        'modules/sf_EventManagement/language/de_DE.lang.php',
        'modules/sf_EventManagement/language/en_UK.lang.php',
        'modules/sf_EventManagement/language/en_us.lang.php',
        'modules/sf_EventManagement/language/es_ES.lang.php',
        'modules/sf_EventManagement/language/et_EE.lang.php',
        'modules/sf_EventManagement/language/fr_FR.lang.php',
        'modules/sf_EventManagement/language/he_IL.lang.php',
        'modules/sf_EventManagement/language/hu_HU.lang.php',
        'modules/sf_EventManagement/language/it_it.lang.php',
        'modules/sf_EventManagement/language/ja_JP.lang.php',
        'modules/sf_EventManagement/language/lt_LT.lang.php',
        'modules/sf_EventManagement/language/lv_LV.lang.php',
        'modules/sf_EventManagement/language/nb_NO.lang.php',
        'modules/sf_EventManagement/language/nl_NL.lang.php',
        'modules/sf_EventManagement/language/pl_PL.lang.php',
        'modules/sf_EventManagement/language/pt_BR.lang.php',
        'modules/sf_EventManagement/language/pt_PT.lang.php',
        'modules/sf_EventManagement/language/ro_RO.lang.php',
        'modules/sf_EventManagement/language/ru_RU.lang.php',
        'modules/sf_EventManagement/language/sr_RS.lang.php',
        'modules/sf_EventManagement/language/sv_SE.lang.php',
        'modules/sf_EventManagement/language/tr_TR.lang.php',
        'modules/sf_EventManagement/language/zh_CN.lang.php',
        'modules/sf_EventManagement/metadata/subpanels/default.php',
        'modules/sf_EventManagement/metadata/SearchFields.php',
        'modules/sf_EventManagement/metadata/dashletviewdefs.php',
        'modules/sf_EventManagement/metadata/detailviewdefs.php',
        'modules/sf_EventManagement/metadata/editviewdefs.php',
        'modules/sf_EventManagement/metadata/listviewdefs.php',
        'modules/sf_EventManagement/metadata/metafiles.php',
        'modules/sf_EventManagement/metadata/popupdefs.php',
        'modules/sf_EventManagement/metadata/quickcreatedefs.php',
        'modules/sf_EventManagement/metadata/searchdefs.php',
        'modules/sf_EventManagement/metadata/studio.php',
        'modules/sf_EventManagement/vardefs.php',
        'modules/sf_WebActivityDetail/clients/base/filters/basic/basic.php',
        'modules/sf_WebActivityDetail/clients/base/filters/default/default.php',
        'modules/sf_WebActivityDetail/clients/base/layouts/detail/detail.php',
        'modules/sf_WebActivityDetail/clients/base/layouts/edit/edit.php',
        'modules/sf_WebActivityDetail/clients/base/menus/header/header.php',
        'modules/sf_WebActivityDetail/clients/base/views/list/list.php',
        'modules/sf_WebActivityDetail/clients/base/views/massupdate/massupdate.php',
        'modules/sf_WebActivityDetail/clients/base/views/record/record.php',
        'modules/sf_WebActivityDetail/clients/base/views/subpanel-list/subpanel-list.php',
        'modules/sf_WebActivityDetail/clients/mobile/layouts/detail/detail.php',
        'modules/sf_WebActivityDetail/clients/mobile/layouts/edit/edit.php',
        'modules/sf_WebActivityDetail/clients/mobile/layouts/list/list.php',
        'modules/sf_WebActivityDetail/clients/mobile/views/detail/detail.php',
        'modules/sf_WebActivityDetail/clients/mobile/views/edit/edit.php',
        'modules/sf_WebActivityDetail/clients/mobile/views/list/list.php',
        'modules/sf_WebActivityDetail/clients/mobile/views/search/search.php',
        'modules/sf_WebActivityDetail/dashboards/focus/focus-dashboard.php',
        'modules/sf_WebActivityDetail/language/bg_BG.lang.php',
        'modules/sf_WebActivityDetail/language/ca_ES.lang.php',
        'modules/sf_WebActivityDetail/language/cs_CZ.lang.php',
        'modules/sf_WebActivityDetail/language/da_DK.lang.php',
        'modules/sf_WebActivityDetail/language/de_DE.lang.php',
        'modules/sf_WebActivityDetail/language/en_UK.lang.php',
        'modules/sf_WebActivityDetail/language/en_us.lang.php',
        'modules/sf_WebActivityDetail/language/es_ES.lang.php',
        'modules/sf_WebActivityDetail/language/et_EE.lang.php',
        'modules/sf_WebActivityDetail/language/fr_FR.lang.php',
        'modules/sf_WebActivityDetail/language/he_IL.lang.php',
        'modules/sf_WebActivityDetail/language/hu_HU.lang.php',
        'modules/sf_WebActivityDetail/language/it_it.lang.php',
        'modules/sf_WebActivityDetail/language/ja_JP.lang.php',
        'modules/sf_WebActivityDetail/language/lt_LT.lang.php',
        'modules/sf_WebActivityDetail/language/lv_LV.lang.php',
        'modules/sf_WebActivityDetail/language/nb_NO.lang.php',
        'modules/sf_WebActivityDetail/language/nl_NL.lang.php',
        'modules/sf_WebActivityDetail/language/pl_PL.lang.php',
        'modules/sf_WebActivityDetail/language/pt_BR.lang.php',
        'modules/sf_WebActivityDetail/language/pt_PT.lang.php',
        'modules/sf_WebActivityDetail/language/ro_RO.lang.php',
        'modules/sf_WebActivityDetail/language/ru_RU.lang.php',
        'modules/sf_WebActivityDetail/language/sr_RS.lang.php',
        'modules/sf_WebActivityDetail/language/sv_SE.lang.php',
        'modules/sf_WebActivityDetail/language/tr_TR.lang.php',
        'modules/sf_WebActivityDetail/language/zh_CN.lang.php',
        'modules/sf_WebActivityDetail/metadata/subpanels/default.php',
        'modules/sf_WebActivityDetail/metadata/SearchFields.php',
        'modules/sf_WebActivityDetail/metadata/dashletviewdefs.php',
        'modules/sf_WebActivityDetail/metadata/detailviewdefs.php',
        'modules/sf_WebActivityDetail/metadata/editviewdefs.php',
        'modules/sf_WebActivityDetail/metadata/listviewdefs.php',
        'modules/sf_WebActivityDetail/metadata/metafiles.php',
        'modules/sf_WebActivityDetail/metadata/popupdefs.php',
        'modules/sf_WebActivityDetail/metadata/quickcreatedefs.php',
        'modules/sf_WebActivityDetail/metadata/searchdefs.php',
        'modules/sf_WebActivityDetail/metadata/studio.php',
        'modules/sf_WebActivityDetail/vardefs.php',
        'modules/sf_webActivity/clients/base/filters/basic/basic.php',
        'modules/sf_webActivity/clients/base/filters/default/default.php',
        'modules/sf_webActivity/clients/base/layouts/detail/detail.php',
        'modules/sf_webActivity/clients/base/layouts/edit/edit.php',
        'modules/sf_webActivity/clients/base/menus/header/header.php',
        'modules/sf_webActivity/clients/base/views/activity-card-detail/activity-card-detail.php',
        'modules/sf_webActivity/clients/base/views/list/list.php',
        'modules/sf_webActivity/clients/base/views/massupdate/massupdate.php',
        'modules/sf_webActivity/clients/base/views/record/record.php',
        'modules/sf_webActivity/clients/base/views/subpanel-list/subpanel-list.php',
        'modules/sf_webActivity/clients/mobile/layouts/detail/detail.php',
        'modules/sf_webActivity/clients/mobile/layouts/edit/edit.php',
        'modules/sf_webActivity/clients/mobile/layouts/list/list.php',
        'modules/sf_webActivity/clients/mobile/views/detail/detail.php',
        'modules/sf_webActivity/clients/mobile/views/edit/edit.php',
        'modules/sf_webActivity/clients/mobile/views/list/list.php',
        'modules/sf_webActivity/clients/mobile/views/search/search.php',
        'modules/sf_webActivity/dashboards/focus/focus-dashboard.php',
        'modules/sf_webActivity/language/bg_BG.lang.php',
        'modules/sf_webActivity/language/ca_ES.lang.php',
        'modules/sf_webActivity/language/cs_CZ.lang.php',
        'modules/sf_webActivity/language/da_DK.lang.php',
        'modules/sf_webActivity/language/de_DE.lang.php',
        'modules/sf_webActivity/language/en_UK.lang.php',
        'modules/sf_webActivity/language/en_us.lang.php',
        'modules/sf_webActivity/language/es_ES.lang.php',
        'modules/sf_webActivity/language/et_EE.lang.php',
        'modules/sf_webActivity/language/fr_FR.lang.php',
        'modules/sf_webActivity/language/he_IL.lang.php',
        'modules/sf_webActivity/language/hu_HU.lang.php',
        'modules/sf_webActivity/language/it_it.lang.php',
        'modules/sf_webActivity/language/ja_JP.lang.php',
        'modules/sf_webActivity/language/lt_LT.lang.php',
        'modules/sf_webActivity/language/lv_LV.lang.php',
        'modules/sf_webActivity/language/nb_NO.lang.php',
        'modules/sf_webActivity/language/nl_NL.lang.php',
        'modules/sf_webActivity/language/pl_PL.lang.php',
        'modules/sf_webActivity/language/pt_BR.lang.php',
        'modules/sf_webActivity/language/pt_PT.lang.php',
        'modules/sf_webActivity/language/ro_RO.lang.php',
        'modules/sf_webActivity/language/ru_RU.lang.php',
        'modules/sf_webActivity/language/sr_RS.lang.php',
        'modules/sf_webActivity/language/sv_SE.lang.php',
        'modules/sf_webActivity/language/tr_TR.lang.php',
        'modules/sf_webActivity/language/zh_CN.lang.php',
        'modules/sf_webActivity/metadata/subpanels/default.php',
        'modules/sf_webActivity/metadata/SearchFields.php',
        'modules/sf_webActivity/metadata/dashletviewdefs.php',
        'modules/sf_webActivity/metadata/detailviewdefs.php',
        'modules/sf_webActivity/metadata/editviewdefs.php',
        'modules/sf_webActivity/metadata/listviewdefs.php',
        'modules/sf_webActivity/metadata/metafiles.php',
        'modules/sf_webActivity/metadata/popupdefs.php',
        'modules/sf_webActivity/metadata/quickcreatedefs.php',
        'modules/sf_webActivity/metadata/searchdefs.php',
        'modules/sf_webActivity/metadata/studio.php',
        'modules/sf_webActivity/vardefs.php',
        'custom/clients/base/api/SalesFusionApi.php',
        'custom/modules/Connectors/connectors/sources/ext/rest/salesfusion/salesfusion.php',
        'custom/Extension/application/Ext/Include/ext_rest_salesfusion.php',
        'custom/Extension/modules/Contacts/Ext/clients/base/views/record/recContactAddButtons.php',
        'custom/Extension/modules/Leads/Ext/clients/base/views/record/recLeadsAddButtons.php',
        'custom/metadata/sf_webactivity_accountsMetaData.php',
        'custom/metadata/sf_webactivity_leadsMetaData.php',
        'custom/metadata/sf_webactivity_contactsMetaData.php',
        'custom/metadata/sf_webactivitydetail_sf_webactivityMetaData.php',
        'custom/metadata/sf_dialogs_contactsMetaData.php',
        'custom/metadata/sf_dialogs_leadsMetaData.php',
        'custom/metadata/sf_eventmanagement_contactsMetaData.php',
        'custom/metadata/sf_eventmanagement_leadsMetaData.php',
    ];

    /**
     * Delete Market package files
     * @return void
     */
    protected function deleteFiles(): void
    {
        $this->log('Sugar Market package: start deleting files');
        foreach ($this->deleteFiles as $toDeleteFile) {
            if (file_exists($toDeleteFile)) {
                if (unlink($toDeleteFile)) {
                    $this->log('Deleted File: ' . $toDeleteFile);
                } else {
                    $this->log('NOT Deleted File: ' . $toDeleteFile);
                }
            }
        }
        $this->log('Sugar Market package: completed deleting files');
    }

    /**
     * Delete Market package custom files
     * @return void
     */
    protected function deleteCustomFiles(): void
    {
        if (!defined('SHADOW_INSTANCE_DIR')) {
            return;
        }
        $this->log('Sugar Market package: start deleting custom files');
        foreach ($this->deleteCustomFiles as $toDeleteFile) {
            $file = realpath($toDeleteFile);
            if (is_writable($file) && $this->isCustomerFile($file)) {
                if (unlink($file)) {
                    $this->log('Deleted custom File: ' . $toDeleteFile);
                } else {
                    $this->log('NOT Deleted custom File: ' . $toDeleteFile);
                }
            }
        }
        $this->log('Sugar Market package: completed deleting custom files');
    }

    /**
     * Check if a file is from shadow
     * @param string $file
     * @return bool
     */
    private function isCustomerFile(string $file): bool
    {
        return str_starts_with($file, (string)SHADOW_INSTANCE_DIR);
    }

    /**
     * Rename custom tables
     * @return void
     */
    protected function renameTables(): void
    {
        $sfTables = [
            'sf_dialogs_contacts_c' => 'sf_dialogs_contacts',
            'sf_dialogs_leads_c' => 'sf_dialogs_leads',
            'sf_eventmanagement_contacts_c' => 'sf_eventmanagement_contacts',
            'sf_eventmanagement_leads_c' => 'sf_eventmanagement_leads',
            'sf_webactivitydetail_sf_webactivity_c' => 'sf_webactivitydetail_sf_webactivity',
            'sf_webactivity_accounts_c' => 'sf_webactivity_accounts',
            'sf_webactivity_contacts_c' => 'sf_webactivity_contacts',
            'sf_webactivity_leads_c' => 'sf_webactivity_leads',
        ];

        foreach ($sfTables as $oldName => $newName) {
            $this->log("Rename table $oldName to $newName");
            if ($this->db->tableExists($newName)) {
                // Remove existing table created from metadata
                $this->db->dropTableName($newName);
            }
            if ($this->db->dbType === 'mssql') {
                // This upgrade script is executed for MSSQL and MySQL only
                $this->db->query("EXEC sp_rename '$oldName', '$newName'");
            } else {
                $this->db->query("ALTER TABLE $oldName RENAME $newName");
            }
        }
    }
}
