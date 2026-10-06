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
 * @class View.Fields.Base.Gai_summaryField
 * @alias SUGAR.App.view.fields.Gai_summaryField
 * @extends View.Fields.Base.TextField
 */
({
    extendsFrom: 'TextField',

    /**
     * @inheritdoc
     */
    initialize: function(options) {
        this._super('initialize', [options]);

        this._initProperties();
    },

    /**
     * Property initialization
     *
     */
    _initProperties: function() {
        this.summary = null;
        this.invalidSummary = null;
    },

    /**
     * @inheritdoc
     */
    format: function(value) {
        try {
            const decodedValue = JSON.parse(value);
            this.summary = decodedValue;

            return decodedValue;
        } catch (e) {
            this.invalidSummary = value;

            return value;
        }
    },
    /**
     * @inheritdoc
     *
     * Trim whitespace from value.
     */
    unformat: function(value) {
        return value.trim();
    },

    _loadTemplate: function() {
        this.template = app.template.getField('gai_summary', 'gai_summary', this.module);
    }
})
