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
 * @class View.Views.Base.Quotes.MassupdateView
 * @alias SUGAR.App.view.views.BaseQuotesMassupdateView
 * @extends View.Views.Base.MassupdateView
 */
({
    extendsFrom: 'MassupdateView',

    /**
     * @inheritdoc
     */
    initialize: function(options) {
        this.plugins = _.union(this.plugins || [], ['QuotesConversionRateLocking']);
        this._super('initialize', [options]);
    },

    /**
     * @inheritdoc
     *
     * Extends parent function to add confirmation regarding conversion rate locking
     */
    handleValidationSuccess: function(massUpdate, validate) {
        let attributes = validate.attributes || this.getAttributes();
        if (_.has(attributes, 'lock_conversion_rates')) {
            let label = attributes.lock_conversion_rates ? 'LBL_QUOTES_CONVERSION_LOCK_MASSUPDATE_CONFIRM' :
                'LBL_QUOTES_CONVERSION_UNLOCK_MASSUPDATE_CONFIRM';
            let message = app.lang.get(label, 'Quotes', {
                lockConversionRatesName: app.lang.get(this._getLockFieldLabel(), 'Quotes')
            });
            app.alert.show('quote_confirm_lock_change', {
                level: 'confirmation',
                messages: message,
                onConfirm: () => {
                    this._super('handleValidationSuccess', [massUpdate, validate]);
                }
            });
        } else {
            this._super('handleValidationSuccess', [massUpdate, validate]);
        }
    }
})
