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
 * Assign Record action configuration view
 *
 * @class View.Fields.Base.SentimentPillField
 * @alias SUGAR.App.view.fields.BaseSentimentPillField
 * @extends View.Fields.Base.EnumField
 */
({
    extendsFrom: 'EnumField',

    /**
     * @inheritdoc
     */
    initialize: function(options) {
        this._super('initialize', [options]);

        this._initProperties(options);
    },

    /**
     * Property initialization, nothing to do for this view
     *
     * @param {Object} options
     */
    _initProperties: function(options) {
        this._dateGenerated = null;
    },

    /**
     * Set the date generated property
     */
    setDateGenerated: function() {
        if (this.view && this.view.type === 'subpanel-list') {
            //there seems to be a problem in the default subpanel list view behavior
            //if the height of the subpanel row is larger than default one,
            //the row of subpanel list view is not rendered correctly
            return;
        }

        let dateGenerated = this.model.get('case_ai_date_generated');

        if (!dateGenerated) {
            return;
        }

        dateGenerated = app.date(dateGenerated);

        if (!dateGenerated || !dateGenerated.isValid()) {
            return;
        }

        this._dateGenerated = dateGenerated.formatUser(false);
    },

    /**
     * @inheritdoc
     */
    _render: function() {
        this.setDateGenerated();

        this._super('_render');
    }
});
