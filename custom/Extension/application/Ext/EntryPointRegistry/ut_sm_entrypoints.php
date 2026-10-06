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

$entry_point_registry['ut_sm_inbound'] = array(
    'file' => 'modules/ut_sm/webhook_receiver.php',
    'auth' => false
);
$entry_point_registry['ut_sm_oauth_handler'] = array(
    'file' => 'modules/ut_sm/oauth_handler.php',
    'auth' => true
);