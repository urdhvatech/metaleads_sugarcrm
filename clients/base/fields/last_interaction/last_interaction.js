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
 * @class View.Fields.Base.LastInteractionField
 * @alias SUGAR.App.view.fields.BaseLastInteractionField
 * @extends View.Fields.Base.DateField
 */
({
    plugins: ['FocusDrawer'],
    extendsFrom: 'DateField',

    /**
     * Used by the FocusDrawer plugin to get the ID of the record this field
     * links to
     *
     * @return {string} the ID of the related record
     */
    getFocusContextModelId: function() {
        return this.model && this.model.get('last_interaction_parent_id') ?
            this.model.get('last_interaction_parent_id') :
            '';
    },

    /**
     * Used by the FocusDrawer plugin to get the name of the module this
     * field links to
     *
     * @return {string} the name of the related module
     */
    getFocusContextModule: function() {
        return this.model && this.model.get('last_interaction_parent_type') ?
            this.model.get('last_interaction_parent_type') :
            '';
    },

    /**
     * Fetches the actual data for the last interaction fields
     *
     * @param {Data.Bean} model
     */
    fetchActualData: function(model) {
        if (!model) {
            return;
        }

        model.fetch({
            fields: [
                'last_interaction_parent_id',
                'last_interaction_parent_type',
                'last_interaction_date',
                'last_interaction_parent_name',
            ],
        });
    },

    /**
     * @inheritdoc
     * Bind listener for unlinks (remove) model from the subpanel's listview collection
     */
    bindDataChange: function() {
        this.listenTo(app.events, 'link:removed', this.fetchActualData);
        this._super('bindDataChange');
    }
})
