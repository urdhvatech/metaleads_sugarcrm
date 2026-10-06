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

$module_name = 'sf_EventManagement';
$viewdefs[$module_name]['QuickCreate'] = [
    'templateMeta' => [
        'maxColumns' => '2',
        'widths' => [
            [
                'label' => '10',
                'field' => '30',
            ],
            [
                'label' => '10',
                'field' => '30',
            ],
        ],
        'useTabs' => false,
    ],
    'panels' => [
        'default' => [
            [
                'name',
                'assigned_user_name',
            ],
            [
                [
                    'name' => 'event_date',
                    'label' => 'LBL_EVENT_DATE',
                ],
                [
                    'name' => 'duration',
                    'label' => 'LBL_DURATION',
                ],
            ],
            [
                [
                    'name' => 'sf_eventmanagement_contacts_name',
                    'label' => 'LBL_SF_EVENTMANAGEMENT_CONTACTS_FROM_CONTACTS_TITLE',
                ],
                [
                    'name' => 'sf_eventmanagement_leads_name',
                    'label' => 'LBL_SF_EVENTMANAGEMENT_LEADS_FROM_LEADS_TITLE',
                ],
            ],
            [
                [
                    'name' => 'registered',
                    'label' => 'LBL_REGISTERED',
                ],
                [
                    'name' => 'attended',
                    'label' => 'LBL_ATTENDED',
                ],
            ],
            [
                [
                    'name' => 'event_location',
                    'label' => 'LBL_EVENT_LOCATION',
                ],
            ],
            [
                'description',
            ],
        ],
    ],
];
