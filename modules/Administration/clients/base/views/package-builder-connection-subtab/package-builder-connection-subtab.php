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
$viewdefs['Administration']['base']['view']['package-builder-connection-subtab'] = [
    'buttons' => [
        [
            'type' => 'button',
            'name' => 'test_connection_button',
            'label' => 'LBL_PACKAGE_BUILDER_TAB_PACKAGES_CONNECT_BUTTON',
            'css_class' => 'btn-primary',
            'events' => [
                'click' => 'button:test_connection_button:click',
            ],
        ],
    ],
    'panels'  => [
        [
            'name' => 'panel_connection',
            'labels' => true,
            'fields' => [
                [
                    'name' => 'instance_url',
                    'label' => 'LBL_PACKAGE_BUILDER_TAB_PACKAGES_URL',
                    'type' => 'url',
                    'allowClear' => false,
                    'required' => true,
                    'openRow' => true,
                    'closeRow' => true,
                    'span' => 4,
                ],
                [
                    'name' => 'username',
                    'label' => 'LBL_PACKAGE_BUILDER_TAB_PACKAGES_USERNAME',
                    'type' => 'text',
                    'allowClear' => false,
                    'required' => true,
                    'openRow' => true,
                    'closeRow' => true,
                    'span' => 2,
                ],
                [
                    'name' => 'password',
                    'label' => 'LBL_PACKAGE_BUILDER_TAB_PACKAGES_PASSWORD',
                    'type' => 'password',
                    'allowClear' => false,
                    'required' => true,
                    'openRow' => true,
                    'closeRow' => true,
                    'span' => 2,
                ],
            ],
        ],
    ],
];
