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

$viewdefs['sf_WebActivityDetail']['base']['view']['record'] = [
    'panels' => [
        [
            'name' => 'panel_header',
            'label' => 'LBL_RECORD_HEADER',
            'header' => true,
            'fields' => [
                [
                    'name' => 'picture',
                    'type' => 'avatar',
                    'width' => 32,
                    'height' => 32,
                    'size' => 'small',
                    'dismiss_label' => true,
                    'readonly' => true,
                ],
                'name',
                [
                    'name' => 'favorite',
                    'label' => 'LBL_FAVORITE',
                    'type' => 'favorite',
                    'readonly' => true,
                    'dismiss_label' => true,
                ],
                [
                    'name' => 'follow',
                    'label' => 'LBL_FOLLOW',
                    'type' => 'follow',
                    'readonly' => true,
                    'dismiss_label' => true,
                ],
            ],
        ],
        [
            'name' => 'panel_body',
            'label' => 'LBL_RECORD_BODY',
            'columns' => 2,
            'labelsOnTop' => true,
            'placeholders' => true,
            'fields' => [
                [
                    'name' => 'title',
                    'label' => 'LBL_TITLE',
                ],
                [
                    'name' => 'duration',
                    'label' => 'LBL_DURATION',
                ],
                [
                    'name' => 'hostname',
                    'label' => 'LBL_HOSTNAME',
                ],
                [
                    'name' => 'interactiondate',
                    'label' => 'LBL_INTERACTIONDATE',
                ],
                [
                    'name' => 'path',
                    'label' => 'LBL_PATH',
                    'span' => 6,
                ],
                [
                    'span' => 6,
                ],
                [
                    'name' => 'parameters',
                    'label' => 'LBL_PARAMETERS',
                ],
                [
                    'name' => 'sf_webactivitydetail_sf_webactivity_name',
                ],
            ],
        ],
    ],
    'templateMeta' => [
        'useTabs' => false,
        'tabDefs' => [
            'LBL_RECORD_BODY' => [
                'newTab' => false,
                'panelDefault' => 'expanded',
            ],
        ],
    ],
];
