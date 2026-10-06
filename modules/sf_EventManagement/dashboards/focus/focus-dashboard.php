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

return [
    'name' => 'LBL_SF_EVENT_MANAGEMENT_FOCUS_DRAWER_DASHBOARD',
    'id' => '1ae9f19e-5294-11ec-9832-525400bc1087',
    'metadata' => [
        'components' => [
            [
                'width' => 12,
                'rows' => [
                    [
                        [
                            'view' => [
                                'type' => 'dashablerecord',
                                'module' => 'sf_EventManagement',
                                'tabs' => [
                                    [
                                        'active' => true,
                                        'label' => 'LBL_MODULE_NAME_SINGULAR',
                                        'link' => '',
                                        'module' => 'sf_EventManagement',
                                    ],
                                ],
                            ],
                            'context' => [
                                'module' => 'sf_EventManagement',
                            ],
                            'width' => 10,
                            'height' => 10,
                        ],
                    ],
                ],
            ],
        ],
    ],
];
