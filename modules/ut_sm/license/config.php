<?php
/*
$outfitters_config = array(
    'name' => 'Meta Leads', //The matches the id value in your manifest file. This allow the library to lookup addon version from upgrade_history, so you can see what version of addon your customers are using
    'shortname' => 'meta-leads', //The short name of the Add-on. e.g. For the url https://www.sugaroutfitters.com/addons/sugaroutfitters the shortname would be sugaroutfitters
    'public_key' => '5ddf27399f2ff8d30825d09fd1bd795f', //The public key associated with the group
    'api_url' => 'https://store.suitecrm.com/api/v1',
    'validate_users' => false,
    'manage_licensed_users' => false, //Enable the user management tool to determine which users will be licensed to use the add-on. validate_users must be set to true if this is enabled. If the add-on must be licensed for all users then set this to false.
    'validation_frequency' => 'weekly', //default: weekly options: hourly, daily, weekly
    'continue_url' => '#ut_sm/settings', //[optional] Will show a button after license validation that will redirect to this page.
);*/

$sugarai_config = array(
    'name' => 'Meta Leads for SugarAI', //The matches the id value in your manifest file. This allow the library to lookup addon version from upgrade_history, so you can see what version of addon your customers are using
    'shortname' => 'whatsapp-for-sugarcrm', //The short name of the Add-on. e.g. For the url https://marketplace.sugarai.com/addons/sugarai the shortname would be sugarai
    'public_key' => 'fe3545e6a0b163edb9a99f538dbbf3f1', //The public key associated with the group
    'api_url' => 'https://marketplace.sugarai.com/api/v1',
    'validate_users' => false,
    'manage_licensed_users' => false, //Enable the user management tool to determine which users will be licensed to use the add-on. validate_users must be set to true if this is enabled. If the add-on must be licensed for all users then set this to false.
    'validation_frequency' => 'weekly', //default: weekly options: hourly, daily, weekly
    'continue_url' => '#ut_sm/settings', //[optional] Will show a button after license validation that will redirect to this page. Could be used to redirect to a configuration page such as index.php?module=MyCustomModule&action=config
);