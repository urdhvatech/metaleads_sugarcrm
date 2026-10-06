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

/**
 * Set of functions that implement specific conditional logic applied to Forecast Metrics metadata.
 *
 * @core Core/PreProcessor/Modules/Forecasts/ForecastMetrics
 */
const ForecastMetrics = _.extend({
    /**
     * Initializes the config property of the ForecastMetrics object.
     */
    setConfig: function(config) {
        this.config = config;
    },

    /**
     * Get the value of the 'forecast_list' metadata filter
     * (keys [commit_stage][$in]) after applying conditional logic.
     *
     * @return {Array}
     */
    getForecastListFilter: function() {
        let value = {
            commit_stage: {
                $in: this.config['commit_stages_included'] || ['include']
            }
        };

        let output = {
            type: 'assign',
            value: [value]
        };

        return output;
    },

    /**
     * Retrieve Help text for 'included_pipeline' metadata.
     * @return {Array}
     */
    getIncludedPipelineHelpText: function() {
        let forecastRange = this.config['forecast_ranges'];
        let value = (forecastRange === 'show_custom_buckets') ?
            'LBL_INCLUDED_PIPELINE_HELP_CUSTOM_RANGE' : 'LBL_INCLUDED_PIPELINE_HELP';

        return {
            type: 'assign',
            value: value
        }
    },

    /**
     * Retrieve the value of the 'commitStageDom' key after applying conditional logic.
     * @return {Array}
     */
    getCommitStageDom: function() {
        let value;
        let forecastRange = this.config['forecast_ranges'];

        if (forecastRange === 'show_binary') {
            value = 'commit_stage_binary_dom';
        } else if (forecastRange === 'show_buckets') {
            value = 'commit_stage_dom';
        } else {
            value = '';
        }

        return {
            type: 'assign',
            value: value
        }
    },

    /**
     * Get the value of the 'included_pipeline' metadata filter
     * (keys [commit_stage][$in] and [sales_stage][$not_in]) after applying conditional logic.
     *
     * @return {Array}
     */
    getIncludedPipelineFilter: function() {
        let value = {
            commit_stage: {
                $in: this.config['commit_stages_included'] || ['include']
            },
            sales_stage: {
                $not_in: this.getSalesStageNotIn()
            },
        };

        let output = {
            type: 'assign',
            value: [value]
        };

        return output;
    },

    /**
     * Leave or remove metadata 'upside_pipeline' depending on the configuration.
     * @return {Array}
     */
    isUpsidePipeline: function() {
        return {
            type: 'remove',
            value: (this.config['forecast_ranges'] !== 'show_buckets')
        }
    },

    /**
     * Get the value of the 'upside_pipeline' metadata filter
     * (keys [commit_stage][$equals] and [sales_stage][$not_in]) after applying conditional logic.
     *
     * @return {Array}
     */
    getUpsidePipelineFilter: function() {
        let value = {
            commit_stage: {
                $equals: 'upside'
            },
            sales_stage: {
                $not_in: this.getSalesStageNotIn()
            }
        };

        let output = {
            type: 'assign',
            value: [value]
        };

        return output;
    },

    /**
     * Retrieve Help text for 'excluded_pipeline' metadata.
     * @return {Array}
     */
    getExcludedPipelineHelpText: function() {
        let forecastRange = this.config['forecast_ranges'];
        let value = (forecastRange === 'show_custom_buckets') ?
            'LBL_EXCLUDED_PIPELINE_HELP_CUSTOM_RANGE' : 'LBL_EXCLUDED_PIPELINE_HELP';

        return {
            type: 'assign',
            value: value
        }
    },

    /**
     * Get the value of the 'excluded_pipeline' metadata filter
     * (keys [commit_stage][$equals] [$not_in] and [sales_stage][$not_in]) after applying conditional logic.
     *
     * @return {Array}
     */
    getExcludedPipelineFilter: function() {
        let value = {};
        let forecastRange = this.config['forecast_ranges'];

        if (forecastRange === 'show_buckets') {
            value['commit_stage'] = {
                $equals: 'exclude'
            }
        } else {
            value['commit_stage'] = {
                $not_in: this.config['commit_stages_included'] || ['include']
            }
        }

        value['sales_stage'] = {
            $not_in: this.getSalesStageNotIn()
        };

        let output = {
            type: 'assign',
            value: [value]
        };

        return output;
    },

    /**
     * Get the value of the 'won' metadata filter
     * (keys [sales_stage][$in]) after applying conditional logic.
     *
     * @return {Array}
     */
    getWonFilter: function() {
        let value = {
            sales_stage: {
                $in: this.config['sales_stage_won'] || ['Closed Won']
            }
        };

        let output = {
            type: 'assign',
            value: [value]
        };

        return output;
    },

    /**
     * Get the value of the 'lost' metadata filter
     * (keys [sales_stage][$in]) after applying conditional logic.
     *
     * @return {Array}
     */
    getLostFilter: function() {
        let value = {
            sales_stage: {
                $in: this.config['sales_stage_lost'] || ['Closed Lost']
            }
        };

        let output = {
            type: 'assign',
            value: [value]
        };

        return output;
    },

    /**
     * Retrieve the value of the filter key [sales_stage][$not_in] after applying conditional logic.
     * @return {Array}
     */
    getSalesStageNotIn: function() {
        let closedWonSalesStages  = this.config['sales_stage_won']  || ['Closed Won'];
        let closedLostSalesStages = this.config['sales_stage_lost'] || ['Closed Lost'];

        return closedWonSalesStages.concat(closedLostSalesStages);
    },
});

module.exports = ForecastMetrics;
