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
 * @class View.Fields.Base.Quotes.EditablelistbuttonField
 * @alias SUGAR.App.view.fields.BaseQuotesEditablelistbuttonField
 * @extends View.Fields.EditablelistbuttonField
 */
({
    extendsFrom: 'EditablelistbuttonField',

    /**
     * @inheritdoc
     */
    initialize: function(options) {
        this.plugins = _.union(this.plugins || [], ['QuotesConversionRateLocking']);
        this._super('initialize', [options]);
    },

    /**
     * @inheritdoc
     */
    _save: function() {
        this.checkConversionRateLock(() => {
            this._super('_save');
        });
    }
})
