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

$viewdefs['sf_EventManagement']['base']['view']['activity-card-content'] = [
    'panels' => [
        [
            'name' => 'panel_header',
            'css_class' => 'panel-header',
            'fields' => [
                [
                    'name' => 'duration',
                    'type' => 'duration',
                    'fields' => [
                        'event_date',
                    ],
                    'related_fields' => [
                        'duration',
                    ],
                ],
            ],
        ],
        [
            'name' => 'panel_body',
            'css_class' => 'panel-body',
            'fields' => [
                [
                    'name' => 'registered',
                    'type' => 'badge',
                    'warning_level' => 'info',
                    'badge_label' => 'LBL_REGISTERED',
                    'css_class' => 'mb-2',
                ],
                [
                    'name' => 'attended',
                    'type' => 'badge',
                    'warning_level' => 'success',
                    'badge_label' => 'LBL_ATTENDED',
                    'css_class' => 'mb-2',
                ],
                [
                    'name' => 'description',
                    'settings' => [
                        'max_display_chars' => 10000,
                        'collapsed' => false,
                    ],
                ],
            ],
        ],
    ],
];
