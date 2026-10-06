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
$viewdefs['Administration']['base']['view']['package-builder-configuration-tab'] = [
    'buttons' => [
        [
            'type' => 'button',
            'name' => 'fetch_customizations_button',
            'label' => 'LBL_PACKAGE_BUILDER_TAB_CONFIG_FETCH_C_BUTTON',
            'tooltip' => 'LBL_PACKAGE_BUILDER_TAB_CONFIG_FETCH_C_BUTTON_DESC',
            'css_class' => 'btn-primary fetchButton',
            'events' => [
                'click' => 'button:fetch_customizations_button:click',
            ],
        ],
    ],
    'panels' => [
        [
            'name' => 'panel_configuration',
            'labels' => true,
            'fields' => [
                [
                    'name' => 'categories',
                    'label' => 'LBL_PACKAGE_BUILDER_TAB_CONFIG_CATEGORIES',
                    'help' => 'LBL_PACKAGE_BUILDER_TAB_CONFIG_CATEGORIES_HELP',
                    'type' => 'enum',
                    'isMultiSelect' => true,
                    'options' => [
                        'acl' => 'Roles',
                        'advanced_workflows' => 'Process Definitions',
                        'dashboards' => 'Dashboards',
                        'dropdowns' => 'Dropdowns',
                        'fields' => 'Fields',
                        'language' => 'Language',
                        'layouts' => 'Layouts',
                        'miscellaneous' => 'Miscellaneous',
                        'relationships' => 'Relationships',
                        'reports' => 'Reports',
                        'workflows' => 'Workflows',
                    ],
                    'allowClear' => true,
                    'required' => true,
                    'openRow' => true,
                    'closeRow' => true,
                    'span' => 6,
                ],
            ],
        ],
    ],
];
