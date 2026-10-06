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
 * @class View.Fields.Base.Gai_engaged_contactsField
 * @alias SUGAR.App.view.fields.Gai_engaged_contactsField
 * @extends View.Fields.Base.TextField
 */
({
    extendsFrom: 'Gai_needed_followupField',

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
        this.engagedContacts = null;
        this.invalidEngagedContacts = null;
    },

    /**
     * @inheritdoc
     */
    format: function(value) {
        try {
            const decodedValue = JSON.parse(value);
            this.engagedContacts = decodedValue;

            return decodedValue;
        } catch (e) {
            this.invalidEngagedContacts = value;
            return value;
        }
    },

    /**
     * Loads the template for the field.
     */
    _loadTemplate: function() {
        this._super('_loadTemplate');
        this.template = app.template.getField('gai_engaged_contacts', 'gai_engaged_contacts', this.module);
    }
})
