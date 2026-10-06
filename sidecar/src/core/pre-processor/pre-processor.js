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

const handlers = {
    'Forecasts': {
        'forecast-metrics': require('./modules/Forecasts/forecast-metrics'),
    }
};

/**
 * Conditional logic PreProcessor is used to implements all necessary conditional logic for metadata.
 *
 * @preprocessor Core/PreProcessor/PreProcessor
 */

/**
 * @alias preprocessor:Core/PreProcessor/PreProcessor
 */
const PreProcessor = _.extend({

    /**
     * Processes metadata in order to implement conditional logic for that data
     *
     * @param {Object} meta     - processed metadata
     * @param {string} module   - module name
     * @param {string} view     - view name
     * @param {Object} config   - the config of the module
     *
     * @return {Object} meta    - metadata processed according to the logic
     */
    preProcess: function(meta, module, view, config) {
        if (!meta || !meta[view] || _.isUndefined(handlers[module]) || _.isUndefined(handlers[module][view])) {
            return meta;
        }

        this.preProcessor = handlers[module][view];
        this.preProcessor.setConfig(config || [])

        let items = meta[view];
        this.dataProcessing(items);

        meta[view] = _.filter(items, item => !_.isUndefined(item));
        return meta;
    },

    /**
     * Iterating over a list of data, assigning values returned by build methods
     * @param {Object} metadata
     */
    dataProcessing: function(metadata) {
        _.each(metadata, (meta, index) => {
            if (!meta.conditionalProperties) {
                return;
            }

            _.each(meta.conditionalProperties, prop => {
                let data = this.preProcessor[prop.buildMethod]()
                switch (data.type) {
                    case 'assign':
                        if (prop.target === 'self') {
                            metadata[index] = data.value;
                        } else {
                            meta[prop.target] = data.value;
                        }
                        break;
                    case 'remove':
                        if (prop.target === 'self' && data.value === true) {
                            delete metadata[index];
                        }
                        break;
                }
            }, this);

            // removing no more necessary technological field 'conditionalProperties'
            delete meta.conditionalProperties;
        }, this);
    },
});

module.exports = PreProcessor;
