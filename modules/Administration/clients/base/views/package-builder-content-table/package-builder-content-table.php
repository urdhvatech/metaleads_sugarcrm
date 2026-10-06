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
$viewdefs['Administration']['base']['view']['package-builder-content-table'] = [
    'panels' => [
        [
            'name' => 'customizations_filter',
            'labels' => true,
            'fields' => [
                [
                    'name' => 'filterInput',
                    'label' => 'LBL_PACKAGE_BUILDER_SEARCHING',
                    'type' => 'text',
                    'allowClear' => false,
                    'openRow' => true,
                    'closeRow' => true,
                    'labelDown' => false,
                    'span' => 4,
                    'css_class' => 'filterSearchInput',
                ],
            ],
        ],
    ],
    'buttons' => [
        [
            'type' => 'button',
            'name' => 'download_package_button',
            'label' => 'LBL_PACKAGE_BUILDER_DOWNLOAD_PACKAGE',
            'css_class' => 'btn-secondary mb-4 !text-gray-800 !border-gray-300 !leading-[1.375rem]
                dark:!bg-gray-800 dark:!border-gray-600 dark:!text-gray-400
                dark:hover:!bg-gray-700 dark:hover:!border-gray-400',
            'events' => [
                'click' => 'button:download_package_button:click',
            ],
        ],
        [
            'type' => 'button',
            'name' => 'add_to_local_packages_button',
            'label' => 'LBL_PACKAGE_BUILDER_ADD_TO_LOCAL_PACKAGES',
            'css_class' => 'btn btn-primary !leading-[1.5rem]',
            'events' => [
                'click' => 'button:add_to_local_packages_button:click',
            ],
        ],
    ],
];
