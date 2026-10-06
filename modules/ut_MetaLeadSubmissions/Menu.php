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

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

global $mod_strings, $app_strings, $sugar_config;

if (ACLController::checkAccess('ut_MetaLeadSubmissions', 'list', true)) {
    $module_menu[] = array(
        'index.php?module=ut_MetaLeadSubmissions&action=index',
        $mod_strings['LNK_LIST'],
        'List',
        'ut_MetaLeadSubmissions',
    );
}
