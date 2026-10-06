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

$viewdefs['sf_webActivity']['base']['view']['record'] = [
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
                    'name' => 'organizationname',
                    'label' => 'LBL_ORGANIZATIONNAME',
                ],
                [
                    'name' => 'emailaddress',
                    'label' => 'LBL_EMAILADDRESS',
                ],
                [
                    'name' => 'isp',
                    'label' => 'LBL_ISP',
                ],
                [
                    'name' => 'duration',
                    'label' => 'LBL_DURATION',
                ],
                [
                    'name' => 'clientip',
                    'label' => 'LBL_CLIENTIP',
                ],
                [
                    'name' => 'city',
                    'label' => 'LBL_CITY',
                ],
                [
                    'name' => 'clienthostname',
                    'label' => 'LBL_CLIENTHOSTNAME',
                ],
                [
                    'name' => 'areacode',
                    'label' => 'LBL_AREACODE',
                ],
                [
                    'name' => 'startdate',
                    'label' => 'LBL_STARTDATE',
                ],
                [
                    'name' => 'countryname',
                    'label' => 'LBL_COUNTRYNAME',
                ],
                [
                    'name' => 'enddate',
                    'label' => 'LBL_ENDDATE',
                ],
                [
                    'name' => 'countrycode',
                    'label' => 'LBL_COUNTRYCODE',
                ],
                [
                    'name' => 'timezone',
                    'label' => 'LBL_TIMEZONE',
                ],
                [
                    'name' => 'dma_code',
                    'label' => 'LBL_DMA_CODE',
                ],
                [
                    'name' => 'browserlanguage',
                    'label' => 'LBL_BROWSERLANGUAGE',
                ],
                [
                    'name' => 'postalcode',
                    'label' => 'LBL_POSTALCODE',
                ],
                [
                    'name' => 'pixeldepth',
                    'label' => 'LBL_PIXELDEPTH',
                ],
                [
                    'name' => 'resolution',
                    'label' => 'LBL_RESOLUTION',
                ],
                [
                    'name' => 'colordepth',
                    'label' => 'LBL_COLORDEPTH',
                ],
                [
                    'name' => 'javaenabled',
                    'label' => 'LBL_JAVAENABLED',
                ],
                [
                    'name' => 'operatingsystem',
                    'label' => 'LBL_OPERATINGSYSTEM',
                    'span' => 6,
                ],
                [
                    'span' => 6,
                ],
                [
                    'name' => 'longitude',
                    'label' => 'LBL_LONGITUDE',
                ],
                [
                    'name' => 'lattitude',
                    'label' => 'LBL_LATITUDE',
                ],
                [
                    'name' => 'sf_webactivity_accounts_name',
                ],
                [
                    'name' => 'sf_webactivity_leads_name',
                ],
                [
                    'name' => 'sf_webactivity_contacts_name',
                    'span' => 12,
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
