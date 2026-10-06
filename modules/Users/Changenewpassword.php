<?php

use Sugarcrm\Sugarcrm\CSP\Nonce;
use Sugarcrm\Sugarcrm\Security\Escaper\Escape;
use Sugarcrm\Sugarcrm\Security\InputValidation\InputValidation;
use Sugarcrm\Sugarcrm\Security\ValueObjects\PlatformName;
use Sugarcrm\Sugarcrm\Marketing\MarketingExtrasContent;

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
$nonce = Nonce::create();

global $app_language, $sugar_config;
global $app_strings;
global $current_language;

require_once 'modules/Users/language/en_us.lang.php';
$mod_strings = return_module_language('', 'Users');
$administration_module_strings = return_module_language('', 'Administration');

///////////////////////////////////////////////////////////////////////////////
////	PASSWORD GENERATED LINK CHECK USING
////
//// This script :  - check the link expiration
////			   - send the filled form to authenticate.php after changing the password in the database
$redirect = '1';
$request = InputValidation::getService();
$guid = $request->getValidInputRequest('guid', 'Assert\Guid');
if ($guid) {
    // Change 'deleted = 0' clause to 'COALESCE(deleted, 0) = 0' because by default the values were NULL
    $Q = 'SELECT * FROM users_password_link WHERE id = ' . $GLOBALS['db']->quoted($guid) . " AND COALESCE(deleted, 0) = '0'";
    $result = $GLOBALS['db']->limitQuery($Q, 0, 1, false);
    $row = $GLOBALS['db']->fetchByAssoc($result);
    if (!empty($row)) {
        $pwd_settings = $GLOBALS['sugar_config']['passwordsetting'];
        $expired = '0';
        if ($pwd_settings['linkexpiration']) {
            $delay = $pwd_settings['linkexpirationtime'] * $pwd_settings['linkexpirationtype'];
            $stim = strtotime($row['date_generated']) + date('Z');
            $expiretime = TimeDate::getInstance()->fromTimestamp($stim)->get("+$delay  minutes")->asDb();
            $timenow = TimeDate::getInstance()->nowDb();
            if ($timenow > $expiretime) {
                $expired = '1';
            }
        }

        if (!$expired) {
            // if the form is filled and we want to login
            if (isset($_REQUEST['login']) && $_REQUEST['login'] == '1') {
                if ($row['username'] == $_POST['user_name']) {
                    $usr = new User();
                    $usr_id = $usr->retrieve_user_id($_POST['user_name']);
                    $usr->retrieve($usr_id);
                    $usr->setNewPassword(html_entity_decode($_POST['new_password'], ENT_COMPAT));
                    $query2 = "UPDATE users_password_link SET deleted='1' where id=" . $GLOBALS['db']->quoted($guid);
                    $GLOBALS['db']->query($query2, true, "Error setting link for $usr->user_name: ");
                    $_POST['user_name'] = $_REQUEST['user_name'];
                    $_POST['user_password'] = html_entity_decode($_REQUEST['new_password'], ENT_COMPAT);
                    $_POST['module'] = 'Users';
                    $_POST['action'] = 'Authenticate';
                    $_POST['login_module'] = 'Home';
                    $_POST['login_action'] = 'index';
                    $_POST['Login'] = 'Login';
                    foreach ($_POST as $k => $v) {
                        $_REQUEST[$k] = $v;
                        $_GET[$k] = $v;
                    }
                    unset($_REQUEST['entryPoint']);
                    unset($_GET['entryPoint']);
                    $GLOBALS['app']->execute();
                    die();
                }
            } else {
                $redirect = '0';
            }
        } else {
            $query2 = "UPDATE users_password_link SET deleted='1' where id=" . $GLOBALS['db']->quoted($guid);
            $GLOBALS['db']->query($query2, true, 'Error setting link');
        }
    }
}

if ($redirect != '0') {
    header('location: ' . $sugar_config['site_url']);
    exit();
}

////	PASSWORD GENERATED LINK CHECK USING
///////////////////////////////////////////////////////////////////////////////

$view = new SugarView();
$view->init();
$view->displayHeader();

$sugar_smarty = new Sugar_Smarty();

$theme = new SidecarTheme(PlatformName::base());
$sugar_smarty->assign('css_url', $theme->getCSSURL());

$themeObject = SugarThemeRegistry::current();
$companyLogoURL = $themeObject->getImageURL('company_logo.png', true, true);
$sugar_smarty->assign('COMPANY_LOGO_URL', $companyLogoURL);

$marketingContentService = new MarketingExtrasContent();
$marketingContentUrl = $marketingContentService->getMarketingExtrasContentUrl($current_language);
$sugar_smarty->assign('MARKETING_EXTRAS_URL', Escape::htmlAttr($marketingContentUrl));

$nonce = \Sugarcrm\Sugarcrm\CSP\Nonce::create();
echo "<script nonce='{$nonce}'>function validateAndSubmit(){if (document.getElementById('user_name').value === '') { return };document.getElementById('user_password').value=document.getElementById('new_password').value;document.getElementById('ChangePasswordForm').submit();}</script>";

$pwd_settings = $GLOBALS['sugar_config']['passwordsetting'];
$pwd_regex = str_replace('\\', '\\\\', $pwd_settings['customregex']);
$sugar_smarty->assign('REGEX', $pwd_regex);

$sugar_smarty->assign('MOD', $mod_strings);
$sugar_smarty->assign('IS_ADMIN', '1');
$sugar_smarty->assign('ENTRY_POINT', 'Changenewpassword');
$sugar_smarty->assign('return_action', 'login');
$sugar_smarty->assign('APP', $app_strings);
$sugar_smarty->assign('LBL_INSTRUCTION', $app_strings['NTC_LOGIN_MESSAGE']);
$sugar_smarty->assign('LBL_PASSWORD_RULES_MANAGEMENT', $administration_module_strings['LBL_PASSWORD_RULES_MANAGEMENT']);
$sugar_smarty->assign('PWDSETTINGS', $GLOBALS['sugar_config']['passwordsetting']);
$sugar_smarty->assign('SITE_URL', $GLOBALS['sugar_config']['site_url']);

$rules = "'" . $GLOBALS['sugar_config']['passwordsetting']['minpwdlength']
    . "','" . $GLOBALS['sugar_config']['passwordsetting']['maxpwdlength']
    . "','" . $GLOBALS['sugar_config']['passwordsetting']['customregex'] . "'";

$sugar_smarty->assign('SUBMIT_BUTTON', '<button type="button" class="btn btn-primary" '
    .  'data-onclick-' . $nonce . '="if(!set_password(document.forms[0],newrules(' . $rules . '))) return false; validateAndSubmit();" '
    . 'tabindex="3" id="login_button" name="Login">'. $mod_strings['LBL_LOGIN_BUTTON_LABEL'] .'</button>');

$sugar_smarty->assign('GUID', $guid);
$sugar_smarty->display('modules/Users/Changenewpassword.tpl');
