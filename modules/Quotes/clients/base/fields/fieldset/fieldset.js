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
 * @class View.Fields.Base.Quotes.FieldsetField
 * @alias SUGAR.App.view.fields.BaseQuotesFieldsetField
 * @extends View.Fields.Base.FieldsetField
 */
({
    extendsFrom: 'FieldsetField',

    /**
     * The currency field name to use on the model
     */
    currencyField: 'currency_id',

    /**
     * The base rate field name to use on the model
     */
    baseRateField: 'base_rate',

    /**
     * The closed statuses of Quotes stage
     */
    closedStatuses: ['Closed Accepted', 'Closed Dead', 'Closed Lost'],

    /**
     * @inheritdoc
     */
    initialize: function(options) {
        this._super('initialize', [options]);
        this.listenTo(this.model, `change:${this.currencyField}`, this.updateBaseRate);
    },

    /**
     * Updates the base rate field based on the currency_id field
     */
    updateBaseRate: function() {
        if (this.name === 'conversion_rate_lock' &&
            !_.includes(this.closedStatuses, this.model.get('quote_stage')) &&
            !this.model.get('lock_conversion_rates')
        ) {
            let currencyId = this.model.get(this.currencyField);
            let lockedRates = this.model.get('locked_currency_rates') || {};
            let baseRate = lockedRates[currencyId];
            let currencyRate = baseRate || app.metadata.getCurrency(currencyId).conversion_rate;
            this.model.set(this.baseRateField, currencyRate);
            this._render();
        }
    }
})
