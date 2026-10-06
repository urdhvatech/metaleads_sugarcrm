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
 * SuiteCRM scheduled task: refresh Facebook long-lived tokens and re-sync page leadgen subscriptions.
 * Also reconcile Meta leads.
 */
if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}
$job_strings[] = 'utSmRefreshFacebookTokens';
$job_strings[] = 'utSmReconcileMetaLeads';
/*
* Refresh Facebook long-lived tokens and re-sync page leadgen subscriptions.
*/
function utSmRefreshFacebookTokens()
{
    require_once 'modules/ut_sm/services/LicenseService.php';
    if (!UTSMLicenseService::isValid()) {
        $GLOBALS['log']->warn('utSmRefreshFacebookTokens skipped because license is not valid');
        return true;
    }

    require_once 'modules/ut_sm/services/TokenService.php';
    $service = new UTSMTokenService();
    $result = $service->refreshIfNeeded(false);
        if (!$result['ok']) {
            $GLOBALS['log']->fatal(
                'utSmRefreshFacebookTokens failed: ' . (!empty($result['error']) ? $result['error'] : 'unknown')
            );
            return false;
        }

        if (!empty($result['skipped'])) {
            $GLOBALS['log']->info('utSmRefreshFacebookTokens: token still valid, skipped exchange');
            return true;
        }

        $count = isset($result['count']) ? (int) $result['count'] : 0;
        $GLOBALS['log']->info('utSmRefreshFacebookTokens: refreshed tokens and synced ' . $count . ' page(s)');
        return true;
}

/*
* Reconcile missed/failed Meta leads.
*/
function utSmReconcileMetaLeads()
{
    require_once 'modules/ut_sm/services/LicenseService.php';
    if (!UTSMLicenseService::isValid()) {
        $GLOBALS['log']->warn('utSmReconcileMetaLeads skipped because license is not valid');
        return true;
    }

    require_once 'modules/ut_sm/services/ReconciliationService.php';
    $service = new UTSMReconciliationService();
    $result = $service->run();

    if (!$result['ok']) {
        $GLOBALS['log']->fatal(
            'utSmReconcileMetaLeads failed: ' . (!empty($result['error']) ? $result['error'] : 'unknown')
        );
        return false;
    }

    $stats = isset($result['stats']) ? $result['stats'] : array();
    $GLOBALS['log']->info(
        'utSmReconcileMetaLeads: checked=' . (isset($stats['checked']) ? $stats['checked'] : 0)
        . ' imported=' . (isset($stats['imported']) ? $stats['imported'] : 0)
        . ' duplicate=' . (isset($stats['duplicate']) ? $stats['duplicate'] : 0)
        . ' failed=' . (isset($stats['failed']) ? $stats['failed'] : 0)
    );
    return true;
}

