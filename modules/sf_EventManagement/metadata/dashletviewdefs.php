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

global $current_user;

$dashletData['sf_EventManagementDashlet']['searchFields'] = [
    'date_entered' => ['default' => ''],
    'date_modified' => ['default' => ''],
    'team_id' => ['default' => ''],
    'assigned_user_id' => [
        'type' => 'assigned_user_name',
        'default' => $current_user->name,
    ],
];

$dashletData['sf_EventManagementDashlet']['columns'] = [
    'name' => [
        'width' => '40',
        'label' => 'LBL_LIST_NAME',
        'link' => true,
        'default' => true,
    ],
    'date_entered' => [
        'width' => '15',
        'label' => 'LBL_DATE_ENTERED',
        'default' => true,
    ],
    'date_modified' => [
        'width' => '15',
        'label' => 'LBL_DATE_MODIFIED',
    ],
    'created_by' => [
        'width' => '8',
        'label' => 'LBL_CREATED',
    ],
    'assigned_user_name' => [
        'width' => '8',
        'label' => 'LBL_LIST_ASSIGNED_USER',
    ],
    'team_name' => [
        'width' => '15',
        'label' => 'LBL_LIST_TEAM',
    ],
];
