<?php

    
if (empty($_REQUEST['method'])) {
    header('HTTP/1.1 400 Bad Request');
    $response = "method is required.";
    $json = getJSONobj();
    echo $json->encode($response);
}


//load license validation config
global $currentModule;
require_once('modules/'.$currentModule.'/license/SugarAILicense.php');

if ($_REQUEST['method'] == 'validate') {
    UT_SM_SugarAILicense::validate();
} elseif ($_REQUEST['method'] == 'change') {
    UT_SM_SugarAILicense::change();
} elseif ($_REQUEST['method'] == 'add') {
    UT_SM_SugarAILicense::add();
} elseif ($_REQUEST['method'] == 'test') {
    //optional param: user_id - test if a specific user has access to the add-on
    //Sugar 6: /index.php?module=SampleLicenseAddon&action=sugaraicontroller&method=test&to_pdf=1
    //Sugar 7: #bwc/index.php?module=SampleLicenseAddon&action=sugaraicontroller&method=test&to_pdf=1
    
    $user_id = null;
    if (!empty($_REQUEST['user_id'])) {
        $user_id = $_REQUEST['user_id'];
    }
    $validate_license = UT_SM_SugarAILicense::isValid($currentModule, $user_id, true);
    if ($validate_license !== true) {
        echo "License did NOT validate.<br/><br/>Reason: ".$validate_license;
        
        
        $validated = UT_SM_SugarAILicense::doValidate($currentModule);
        
        if (is_array($validated['result'])) {
            echo "<br/><br/>Key validation = ".!empty($validated['result']['validated']);
            require('modules/'.$currentModule.'/license/config.php');

            if ($sugarai_config['validate_users'] == true) {
                echo "<br/>User validation = ".!empty($validated['result']['validated_users']);
                echo "<br/>Licensed User Count = ".$validated['result']['licensed_user_count'];
                echo "<br/>Current User Count = ".$validated['result']['user_count'];

                if ($validated['result']['user_count'] > $validated['result']['licensed_user_count']) {
                    echo "<br/><br/>Additional Users Required = ".($validated['result']['user_count'] - $validated['result']['licensed_user_count']);
                }
            }
        }
    } else {
        echo "License validated";
    }
}
