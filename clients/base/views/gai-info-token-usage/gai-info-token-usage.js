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
 * @class View.Views.Base.GaiTokenUsageInfoView
 * @alias SUGAR.App.view.views.BaseGaiTokenUsageInfoView
 * @extends View.View
 */
({
    className: 'h-full',
    /**
     * @inheritdoc
     */
    initialize: function(options) {
        this._super('initialize', [options]);

        this._initProperties(options);
    },

    /**
     * Initialize Properties
     */
    _initProperties: function(options) {
        this.loading = true;

        this.isDarkMode = app.utils.isDarkMode();
        this.tokenUsage = '';

        this.getTokenData();
    },

    /**
     * Convert the input to a number and round it up to the next integer
     *
     * @param {string|number} input
     * @return {string}
     */
    _roundToOneDecimal: function(input) {
        if (input === undefined || input === null) {
            return '';
        }

        let number = parseFloat(input);

        if (isNaN(number)) {
            return '';
        }

        let roundedUpNumber = Math.round(number);

        return roundedUpNumber.toString();
    },

    /**
     * Fetch Inference
     */
    getTokenData: function() {
        const apiCallbacks = {
            success: (response) => {
                if (this.disposed) {
                    return;
                }

                if (!response || response.error === true) {
                    if (this.$el.parent() && this.$el.parent().length > 0) {
                        this.$el.parent().hide();
                    }
                }

                if (response.data) {
                    this.tokenUsage = this._roundToOneDecimal(response.data);
                    this.loading = false;

                    this.render();
                }

            },
            error: (error) => {
                if (this.disposed) {
                    return;
                }

                this.$('.gai-info-token-usage-container').hide();
            },
        };

        const apiUrl = app.api.buildURL('service/intelligence/usage/token', 'read');

        app.api.call('read', apiUrl, null, apiCallbacks);
    },
});
