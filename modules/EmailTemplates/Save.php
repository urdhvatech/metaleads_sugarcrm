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
/*********************************************************************************
 * Description:  Saves an Account record and then redirects the browser to the
 * defined return URL.
 * Portions created by SugarCRM are Copyright (C) SugarCRM, Inc.
 * All Rights Reserved.
 * Contributor(s): ______________________________________..
 ********************************************************************************/

$focus = BeanFactory::newBean('EmailTemplates');
require_once 'include/formbase.php';
$focus = populateFromPost('', $focus);

global $current_user;
$workflow_modules = get_workflow_admin_modules_for_user($current_user);

$baseModule = $_REQUEST['base_module'] ?? '';
if (!empty($_REQUEST['type']) && $_REQUEST['type'] === 'workflow' && empty($workflow_modules[$baseModule])) {
    sugar_die('Unauthorized access');
}

if (isset($_REQUEST['record'])) {
    $checkFocus = BeanFactory::getBean('EmailTemplates', $_REQUEST['record']);
    $baseModule = $checkFocus->base_module;
    if (!empty($baseModule) && empty($workflow_modules[$baseModule])) {
        sugar_die('Unauthorized access');
    }
}
$form = new EmailTemplateFormBase();
sugar_cache_clear('select_array:' . $focus->object_name . 'namebase_module=\'' . $focus->base_module . '\'name');
if (isset($_REQUEST['inpopupwindow']) and $_REQUEST['inpopupwindow'] == true) {
    $focus = $form->handleSave('', false, false); //do not redirect.
    $nonce = \Sugarcrm\Sugarcrm\CSP\Nonce::create();
    $body1 = "
		<script type='text/javascript' nonce='{$nonce}'>
			function refreshTemplates() {
				window.opener.refresh_email_template_list('$focus->id','$focus->name')
				window.close();
			}

			refreshTemplates();
		</script>";
    echo $body1;
} else {
    $form->handleSave('', true, false);
}
