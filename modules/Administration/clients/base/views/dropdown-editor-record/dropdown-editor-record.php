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
$viewdefs['Administration']['base']['view']['dropdown-editor-record'] = [
    'label' => 'LBL_DROPDOWN_EDITOR',
    'language_field' => [
        [
            'name' => 'language_selection',
            'type' => 'language',
            'label' => 'LBL_LANGUAGE_SELECTOR',
            'options' => 'available_language_dom',
            'template' => 'edit',
            'required' => true,
        ],
    ],
    'comparison_language_field' => [
        [
            'name' => 'comparison_language_selection',
            'type' => 'language',
            'label' => 'LBL_DROPDOWN_LANG_COMPARISON',
            'options' => 'available_language_dom',
            'template' => 'edit',
            'required' => true,
        ],
    ],
    'roles_field' => [
        [
            'name' => 'role_selection',
            'label' => 'LBL_DROPDOWN_ROLE',
            'type' => 'enum',
            // Setting this empty because it will be filled from the server response
            'options' => 'available_roles_dom',
            'default' => '0',
            'template' => 'edit',
            'required' => true,
        ],
    ],
    'buttons' => [
        [
            'name' => 'add_new_dropdown_btn',
            'type' => 'button',
            'label' => 'LBL_DROPDOWN_BTN_ADD_NEW',
            'event' => 'button:add_new_dropdown_item:click',
        ],
        [
            'name' => 'sort_btn',
            'type' => 'button',
            'label' => 'LBL_SORT',
        ],
    ],
    'panels' => [
        [
            'id' => 'dropdown-editor-header',
            'name' => 'panel_header',
            'label' => 'LBL_PANEL_1',
            'fields' => [
                [
                    'name' => 'dropdown_role',
                    'label' => 'LBL_DROPDOWN_ROLE',
                    'type' => 'bool',
                    'template' => 'edit',
                ],
                [
                    'name' => 'dropdown_key',
                    'label' => 'LBL_DROPDOWN_ITEM_NAME',
                    'type' => 'text',
                    'template' => 'detail',
                ],
                [
                    'name' => 'dropdown_label',
                    'label' => 'LBL_DROPDOWN_DISPLAY_LABEL',
                    'type' => 'text',
                    'template' => 'edit',
                ],
                [
                    'name' => 'dropdown_label_comparison',
                    'label' => 'LBL_DROPDOWN_LANG_COMPARISON',
                    'type' => 'text',
                ],
                [
                    'name' => 'dropdown_classification',
                    'label' => 'LBL_DROPDOWN_CLASSIFICATION',
                    'type' => 'enum',
                    'options' => [],
                    'template' => 'edit',
                ],
            ],
            'buttons' => [
                [
                    'name' => 'dropdown_format',
                    'type' => 'button',
                    'tooltip' => 'LBL_DROPDOWN_FORMATTING',
                    'icon' => 'sicon-studio',
                    'events' => [
                        'click' => 'button:formatting-button:click',
                    ],
                ],
                [
                    'name' => 'dropdown_delete',
                    'type' => 'button',
                    'tooltip' => 'LBL_DELETE',
                    'icon' => 'sicon-trash',
                ],
            ],
        ],
    ],
];
