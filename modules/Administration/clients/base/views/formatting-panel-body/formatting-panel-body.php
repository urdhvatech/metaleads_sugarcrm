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

// under developing ...
$viewdefs['Administration']['base']['view']['formatting-panel-body'] = [
    'name' => 'formatting-body',
    'label' => 'LBL_DROPDOWN_FORMAT_OPTIONS',
    'panels' => [
        [
            'name' => 'formatting-panel-body-1',
            'inline' => false,
            'fields' => [
                [
                    'name' => 'formatting-style',
                    'label' => 'LBL_DROPDOWN_FORMATTING_STYLE',
                    'type' => 'formatting-style',
                    'colors' => [
                        'purple', 'pink', 'fuschia', 'red', 'orange', 'amber', 'yellow',
                        'lime', 'emerald', 'teal', 'blue', 'cyan', 'indigo', 'gray',
                    ],
                ],
                [
                    'name' => 'formatting-text-emphasis',
                    'label' => 'LBL_DROPDOWN_FORMATTING_TEXT_EMPHASIS',
                    'type' => 'formatting-text-emphasis',
                ],
            ],
        ],
        [
            'name' => 'formatting-panel-body-2',
            'inline' => true,
            'fields' => [
                [
                    'name' => 'formatting-text-color',
                    'label' => 'LBL_DROPDOWN_FORMATTING_TEXT_COLOR',
                    'type' => 'formatting-color',
                    'template' => 'edit',
                ],
                [
                    'name' => 'formatting-background-color',
                    'label' => 'LBL_DROPDOWN_FORMATTING_BACKGROUND_COLOR',
                    'type' => 'formatting-color',
                    'template' => 'edit',
                ],
            ],
        ],
        [
            'name' => 'formatting-panel-body-3',
            'inline' => true,
            'fields' => [
                [
                    'name' => 'formatting-icon',
                    'label' => 'LBL_DROPDOWN_FORMATTING_ICON',
                    'type' => 'formatting-icon',
                    'options' => 'module_icons_dom',
                    'template' => 'edit',
                    'formatOptions' => 'icon',
                ],
                [
                    'name' => 'formatting-icon-color',
                    'label' => 'LBL_DROPDOWN_FORMATTING_ICON_COLOR',
                    'type' => 'formatting-color',
                    'template' => 'edit',
                ],
            ],
        ],
    ],
];
