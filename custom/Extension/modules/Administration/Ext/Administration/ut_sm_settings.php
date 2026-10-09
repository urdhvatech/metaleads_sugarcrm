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

$admin_option_defs = array();
$admin_option_defs['Administration']['ut_sm_settings'] = array(
    'Administration',
    'icon' => 'sicon-settings',
    'LBL_UT_SM_SETTINGS_TITLE',
    'LBL_UT_SM_SETTINGS_DESC',
    '#ut_sm/settings',
);
/*$admin_option_defs['Administration']['ut_sm_license'] = array(
    'Administration',
    'icon' => 'sicon-lock',
    'LBL_UT_SM_LICENSE',
    'LBL_UT_SM_DESC',
    '#ut_sm/license',
);
*/
$admin_option_defs['Administration']['ut_whatsapp_license']= array('UrdhvaTech','LBL_UT_SM_LICENSE','LBL_UT_SM_DESC','javascript:void(parent.SUGAR.App.router.navigate("#bwc/index.php?module=ut_sm&action=license", {trigger: true}));');

$admin_group_header[] = array(
    'LBL_UT_SM_SETTINGS_TITLE',
    '',
    false,
    $admin_option_defs,
    'LBL_UT_SM_SETTINGS_DESC',
);
