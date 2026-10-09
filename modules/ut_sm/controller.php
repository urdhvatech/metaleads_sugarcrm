<?php
/**
 * This file is part of the "Meta Leads" package.
 *
 * @package Meta Leads
 * @author Urdhva Tech <contact@urdhva-tech.com>
 * @link https://www.urdhva-tech.com
 * @copyright Urdhva Tech
 * @license As specified in the License Agreement supplied with this package.
 */
/**
 * SuiteCRM controller for the ut_sm Meta Lead Ads module.
 */
if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once 'include/MVC/Controller/SugarController.php';

class UT_SMController extends SugarController
{
    public function preProcess()
    {
        parent::preProcess();

        $action = strtolower((string) $this->action);
        $licenseExemptActions = array('license', 'outfitterscontroller');
        $licenseGatedActions = array('settings', 'index', 'listview', 'account_settings');

        if (in_array($action, $licenseExemptActions, true)) {
            return;
        }

        if (!in_array($action, $licenseGatedActions, true)) {
            return;
        }

        require_once 'modules/ut_sm/services/LicenseService.php';
        UTSMLicenseService::redirectToLicenseIfInvalid();
    }

    /**
     * Main OAuth / Connected Accounts settings.
     * URL: index.php?module=ut_sm&action=settings
     * View: modules/ut_sm/views/view.settings.php
     */
    public function action_settings()
    {
        $this->redirectToSidecar('ut_sm/settings');
    }
    public function action_index()
    {
        $this->redirectToSidecar('ut_sm/settings');
    }
    public function action_listview()
    {
        $this->redirectToSidecar('ut_sm/settings');
    }

    /**
     * Per-account Configure screen (assignment, forms, field mapping).
     * Separate from main settings because it needs a connected-account record.
     * URL: index.php?module=ut_sm&action=account_settings&record=...
     * View: modules/ut_sm/views/view.account_settings.php
     */
    public function action_account_settings()
    {
        $record = isset($_REQUEST['record']) ? trim((string) $_REQUEST['record']) : '';
        $route = 'ut_sm/settings';
        if ($record !== '') {
            $route = 'ut_sm/account/' . rawurlencode($record);
        }
        $this->redirectToSidecar($route);
    }

    /*public function action_license()
    {
        $this->redirectToSidecar('bwc/index.php?module=ut_sm&action=license');
    }*/

    public function action_license()
    {
        $this->view = 'license';
    }

    /**
     * @param string $route
     */
    protected function redirectToSidecar($route)
    {
        require_once 'modules/ut_sm/services/LicenseService.php';
        UTSMLicenseService::redirectToSidecar($route);
    }
}
