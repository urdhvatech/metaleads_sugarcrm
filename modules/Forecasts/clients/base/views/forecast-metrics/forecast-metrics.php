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

$viewdefs['Forecasts']['base']['view']['forecast-metrics']['forecast-metrics'] = [
    [
        'name' => 'forecast_list',
        'label' => 'LBL_FORECAST',
        'helpText' => 'LBL_FORECAST_HELP',
        'type' => 'forecast-metric',
        'sumFields' => 'forecasted_likely',
        'isDefaultFilter' => true,
        'conditionalProperties' => [
            [
                'target' => 'filter',
                'buildMethod' => 'getForecastListFilter',
            ],
        ],
    ],
    [
        'name' => 'included_pipeline',
        'label' => 'LBL_INCLUDED_PIPELINE',
        'commitStageDomOption' => 'include',
        'type' => 'forecast-metric',
        'sumFields' => 'forecasted_likely',
        'conditionalProperties' => [
            [
                'target' => 'helpText',
                'buildMethod' => 'getIncludedPipelineHelpText',
            ],
            [
                'target' => 'commitStageDom',
                'buildMethod' => 'getCommitStageDom',
            ],
            [
                'target' => 'filter',
                'buildMethod' => 'getIncludedPipelineFilter',
            ],
        ],
    ],
    [
        'name' => 'upside_pipeline',
        'label' => 'LBL_UPSIDE_PIPELINE',
        'helpText' => 'LBL_UPSIDE_PIPELINE_HELP',
        'commitStageDomOption' => 'upside',
        'type' => 'forecast-metric',
        'sumFields' => 'amount',
        'conditionalProperties' => [
            [
                'target' => 'self',
                'buildMethod' => 'isUpsidePipeline',
            ],
            [
                'target' => 'commitStageDom',
                'buildMethod' => 'getCommitStageDom',
            ],
            [
                'target' => 'filter',
                'buildMethod' => 'getUpsidePipelineFilter',
            ],
        ],
    ],
    [
        'name' => 'excluded_pipeline',
        'label' => 'LBL_EXCLUDED_PIPELINE',
        'commitStageDomOption' => 'exclude',
        'type' => 'forecast-metric',
        'sumFields' => 'amount',
        'conditionalProperties' => [
            [
                'target' => 'helpText',
                'buildMethod' => 'getExcludedPipelineHelpText',
            ],
            [
                'target' => 'commitStageDom',
                'buildMethod' => 'getCommitStageDom',
            ],
            [
                'target' => 'filter',
                'buildMethod' => 'getExcludedPipelineFilter',
            ],
        ],
    ],
    [
        'name' => 'won',
        'label' => 'LBL_WON',
        'helpText' => 'LBL_WON_HELP',
        'type' => 'forecast-metric',
        'sumFields' => 'amount',
        'conditionalProperties' => [
            [
                'target' => 'filter',
                'buildMethod' => 'getWonFilter',
            ],
        ],
    ],
    [
        'name' => 'lost',
        'label' => 'LBL_LOST',
        'helpText' => 'LBL_LOST_HELP',
        'type' => 'forecast-metric',
        'sumFields' => 'lost',
        'conditionalProperties' => [
            [
                'target' => 'filter',
                'buildMethod' => 'getLostFilter',
            ],
        ],
    ],
    [
        'name' => 'all',
        'label' => 'LBL_ALL',
        'helpText' => 'LBL_ALL_HELP',
        'type' => 'forecast-metric',
        'sumFields' => [
            'amount',
            'lost',
        ],
        'filter' => [],
    ],
];
