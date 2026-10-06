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

$admin_option_defs=array();
$admin_option_defs['Administration']['ut_sm_settings']= array('UrdhvaTech','LBL_UT_SM_SETTINGS_ICON','LBL_UT_SM_SETTINGS_TITLE','./index.php?module=ut_sm&action=settings','system-settings');
$admin_option_defs['Administration']['ut_sm_license']= array('UrdhvaTech','LBL_UT_SM_LICENSE','LBL_UT_SM_DESC','./index.php?module=ut_sm&action=license','oauth-keys');
$admin_group_header[]= array('LBL_UT_SM_SETTINGS_TITLE','',false,$admin_option_defs, 'LBL_UT_SM_SETTINGS_DESC');