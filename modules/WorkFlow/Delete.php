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
 * Description:
 ********************************************************************************/


global $mod_strings, $current_user;


$focus = BeanFactory::newBean('WorkFlow');

if (!isset($_REQUEST['record'])) {
    sugar_die($mod_strings['ERR_DELETE_RECORD']);
}

$workflow_modules = get_workflow_admin_modules_for_user($current_user);
if (!is_admin($current_user) && empty($workflow_modules)) {
    sugar_die('Unauthorized access to WorkFlow.');
}


$focus->retrieve($_REQUEST['record']);

if ($focus->ACLAccess('Delete')) {
    $focus->mark_deleted($_REQUEST['record']);
} else {
    ACLController::displayNoAccess(true);
    sugar_cleanup(true);
}
//Re-write workflow
$focus->write_workflow();
header('Location: index.php?' . http_build_query([
        'module' => $_REQUEST['return_module'],
        'action' => $_REQUEST['return_action'],
        'record' => $_REQUEST['return_id'],
    ]));
