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

$viewdefs['base']['view']['gai-dashlet'] = [
    'dashlets' => [
        [
            'label' => 'LBL_GAI_DASHLET_TITLE',
            'description' => 'LBL_GAI_DASHLET_DESCRIPTION',
            'config' => [
                'dashletConfig' => [
                    'styleConfig' => [
                        'header' => [
                            'class' => 'gradient-header',
                        ],
                        'container' => [
                            'class' => 'gradient-border custom-toolbar',
                        ],
                        'buttons' => [
                            'class' => 'gradient-buttons',
                        ],
                    ],
                ],
            ],
            'filter' => [
                'view' => ['record'],
                'licenseType' => [
                    'GAI',
                ],
                'module' => [
                    'Opportunities',
                    'Cases',
                    'Accounts',
                ],
            ],
        ],
    ],
    'custom_toolbar' => [
        'buttons' => [
            [
                'type' => 'dashletaction',
                'css_class' => 'dashlet-toggle btn btn-invisible minify',
                'icon' => 'sicon-chevron-up',
                'action' => 'toggleMinify',
                'tooltip' => 'LBL_DASHLET_TOGGLE',
            ],
            [
                'type' => 'dashletaction',
                'css_class' => 'dashlet-info btn btn-invisible',
                'icon' => 'sicon-info-circle-lg',
                'action' => 'showInfoPopup',
                'rel' => 'popover',
            ],
            [
                'dropdown_buttons' => [
                    [
                        'type' => 'dashletaction',
                        'action' => 'editClicked',
                        'label' => 'LBL_DASHLET_CONFIG_EDIT_LABEL',
                    ],
                    [
                        'type' => 'dashletaction',
                        'action' => 'removeClicked',
                        'label' => 'LBL_DASHLET_REMOVE_LABEL',
                        'name' => 'remove_button',
                    ],
                ],
            ],
        ],
    ],
    'panels' => [
        [
            'name' => 'dashlet_settings',
            'columns' => 2,
            'labelsOnTop' => true,
            'placeholders' => true,
            'fields' => [],
        ],
    ],
];
