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
 * @class View.Views.Base.AdministrationLanguageField
 * @alias SUGAR.App.view.fields.BaseAdministrationUrlField
 * @extends View.Fields.Base.LanguageField
 */
({
    extendsFrom: 'LanguageField',

    /**
     * @inheritdoc
     */
    _filterOptions: function(options) {
        let newOptions = this._super('_filterOptions', [options]);

        if (this.def.name === 'comparison_language_selection') {
            newOptions = this._adjustComparisonLanguageList();
        }

        return newOptions;
    },

    /**
     * Adjusts comparison language list to remove the selected language.
     */
    _adjustComparisonLanguageList: function() {
        let lang = this.model.get('language_selection');
        let oldIems = this.items;
        // Convert obj to a key/value array
        const oldItemsAsArray = Object.entries(oldIems);
        // Filter out the all languages except selected language
        const newItemsAsArray = oldItemsAsArray.filter(([key, value]) => key !== lang);
        // Convert the key/value array back to an object:
        const newIems = Object.fromEntries(newItemsAsArray);

        return newIems;
    }
})
