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

if (empty($_REQUEST['method'])) {
    header('HTTP/1.1 400 Bad Request');
    $response = 'method is required.';
    $json = getJSONobj();
    echo $json->encode($response);
    exit;
}

global $currentModule;

require_once 'modules/' . $currentModule . '/license/OutfittersLicense.php';

if ($_REQUEST['method'] == 'validate') {
    UT_SM_OutfittersLicense::validate();
} elseif ($_REQUEST['method'] == 'change') {
    UT_SM_OutfittersLicense::change();
} elseif ($_REQUEST['method'] == 'add') {
    UT_SM_OutfittersLicense::add();
} elseif ($_REQUEST['method'] == 'test') {
    $user_id = null;
    if (!empty($_REQUEST['user_id'])) {
        $user_id = $_REQUEST['user_id'];
    }
    $validate_license = UT_SM_OutfittersLicense::isValid($currentModule, $user_id, true);

    if ($validate_license !== true) {
        echo 'License did NOT validate.<br/><br/>Reason: ' . $validate_license;

        $validated = UT_SM_OutfittersLicense::doValidate($currentModule);

        if (is_array($validated['result'])) {
            echo '<br/><br/>Key validation = ' . !empty($validated['result']['validated']);
            require 'modules/' . $currentModule . '/license/config.php';

            if ($outfitters_config['validate_users'] == true) {
                echo '<br/>User validation = ' . !empty($validated['result']['validated_users']);
                echo '<br/>Licensed User Count = ' . $validated['result']['licensed_user_count'];
                echo '<br/>Current User Count = ' . $validated['result']['user_count'];

                if ($validated['result']['user_count'] > $validated['result']['licensed_user_count']) {
                    echo '<br/><br/>Additional Users Required = '
                        . ($validated['result']['user_count'] - $validated['result']['licensed_user_count']);
                }
            }
        }
    } else {
        echo 'License validated';
    }
}
