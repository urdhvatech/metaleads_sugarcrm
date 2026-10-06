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

$module_name = 'sf_webActivity';
$viewdefs[$module_name]['DetailView'] = [
    'templateMeta' => [
        'form' => [
            'buttons' => [
                'EDIT',
                'DUPLICATE',
                'DELETE',
                'FIND_DUPLICATES',
            ],
        ],
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
        'syncDetailEditViews' => true,
    ],
    'panels' => [
        'default' => [
            [
                [
                    'name' => 'organizationname',
                    'label' => 'LBL_ORGANIZATIONNAME',
                ],
                [
                    'name' => 'emailaddress',
                    'label' => 'LBL_EMAILADDRESS',
                ],
            ],
            [
                [
                    'name' => 'isp',
                    'label' => 'LBL_ISP',
                ],
                [
                    'name' => 'duration',
                    'label' => 'LBL_DURATION',
                ],
            ],
            [
                [
                    'name' => 'clientip',
                    'label' => 'LBL_CLIENTIP',
                ],
                [
                    'name' => 'city',
                    'label' => 'LBL_CITY',
                ],
            ],
            [
                [
                    'name' => 'clienthostname',
                    'label' => 'LBL_CLIENTHOSTNAME',
                ],
                [
                    'name' => 'areacode',
                    'label' => 'LBL_AREACODE',
                ],
            ],
            [
                [
                    'name' => 'startdate',
                    'label' => 'LBL_STARTDATE',
                ],
                [
                    'name' => 'countrycode',
                    'label' => 'LBL_COUNTRYCODE',
                ],
            ],
            [
                [
                    'name' => 'enddate',
                    'label' => 'LBL_ENDDATE',
                ],
                [
                    'name' => 'countryname',
                    'label' => 'LBL_COUNTRYNAME',
                ],
            ],
            [
                [
                    'name' => 'timezone',
                    'label' => 'LBL_TIMEZONE',
                ],
                [
                    'name' => 'dma_code',
                    'label' => 'LBL_DMA_CODE',
                ],
            ],
            [
                [
                    'name' => 'browserlanguage',
                    'label' => 'LBL_BROWSERLANGUAGE',
                ],
                [
                    'name' => 'postalcode',
                    'label' => 'LBL_POSTALCODE',
                ],
            ],
            [
                [
                    'name' => 'pixeldepth',
                    'label' => 'LBL_PIXELDEPTH',
                ],
                [
                    'name' => 'resolution',
                    'label' => 'LBL_RESOLUTION',
                ],
            ],
            [
                [
                    'name' => 'colordepth',
                    'label' => 'LBL_COLORDEPTH',
                ],
                [
                    'name' => 'javaenabled',
                    'label' => 'LBL_JAVAENABLED',
                ],
            ],
            [
                [
                    'name' => 'operatingsystem',
                    'label' => 'LBL_OPERATINGSYSTEM',
                ],
            ],
            [
                [
                    'name' => 'longitude',
                    'label' => 'LBL_LONGITUDE',
                ],
                [
                    'name' => 'latitude',
                    'label' => 'LBL_LATITUDE',
                ],
            ],
            [
                [
                    'name' => 'sf_webactivity_accounts_name',
                ],
                [
                    'name' => 'sf_webactivity_leads_name',
                ],
            ],
            [
                [
                    'name' => 'sf_webactivity_contacts_name',
                ],
            ],
        ],
        'lbl_editview_panel1' => [
            [
                [
                    'name' => 'useragent',
                    'label' => 'LBL_USERAGENT',
                ],
                '',
            ],
            [
                [
                    'name' => 'touchpoint',
                    'label' => 'LBL_TOUCHPOINT',
                ],
            ],
            [
                [
                    'name' => 'referrerkeywords',
                    'studio' => 'visible',
                    'label' => 'LBL_REFERRERKEYWORDS',
                ],
            ],
            [
                [
                    'name' => 'referrerquery',
                    'studio' => 'visible',
                    'label' => 'LBL_REFERRERQUERY',
                ],
            ],
            [
                [
                    'name' => 'referrerreferrer',
                    'studio' => 'visible',
                    'label' => 'LBL_REFERRERREFERRER',
                ],
            ],
            [
                [
                    'name' => 'referrerdomain',
                    'label' => 'LBL_REFERRERDOMAIN',
                ],
            ],
        ],
    ],
];
