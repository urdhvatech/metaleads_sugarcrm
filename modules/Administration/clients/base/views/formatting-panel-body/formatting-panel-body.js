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
 * @class View.Views.Base.AdministrationFormattingPanelBodyView
 * @alias SUGAR.App.view.views.BaseAdministrationFormattingPanelBodyView
 * @extends View.View
 */
({
    // Saves the selected dropnown item model
    itemModel: null,

    dropdownCollection: null,

    initialize: function(options) {
        this._super('initialize', [options]);
        this.dropdownCollection = new Backbone.Collection();
    },

    /**
     * @inheritdoc
     */
    bindDataChange: function() {
        this._super('bindDataChange');
        this.listenTo(this.context, 'formatting-panel:state:changed', this.refreshPanelBody);
        this.listenTo(this.context, 'formatting-panel:pagination:fire', this.switchPanelBody);
        this.listenTo(this.context, 'formatting-style:removed', this.render);
    },

    /**
     * Renders the pagination arrows based on the current item model.
     */
    refreshPaginationArrows: function() {
        if (!this.itemModel || !this.dropdownCollection) {
            return;
        }

        const currIndex = _.indexOf(
            this.dropdownCollection.models,
            this.dropdownCollection.get(this.itemModel.cid)
        );

        this.showPagination(currIndex);
    },

    /**
     * Refreshes the panel body with the selected dropdown item model
     *
     * @param {Object} model - The selected dropdown item model
     */
    refreshPanelBody: function(model) {
        if (!model) {
            return;
        }

        this.itemModel = model;
        const optionItems = model.collection || [];
        const visibleItems = optionItems.filter(model => model.get('hidden') !== true);
        this.dropdownCollection.reset(visibleItems);
        let currID = this.itemModel.cid;
        this.context.trigger('highlighted-dropdown-item:changed', currID);
        this.refreshPaginationArrows();
    },

    /**
     * Switches the panel body based on the pagination click event
     *
     * @param {Object} data - Contains the direction of the pagination click event
     */
    switchPanelBody: function(data) {
        let currID = this.itemModel ? this.itemModel.cid : null;

        if (!currID) {
            return;
        }

        let currIndex =  _.indexOf(this.dropdownCollection.models, this.dropdownCollection.get(currID));
        if (this.dropdownCollection.models.length < 2) {
            // We're currently switching previews or we don't have enough models, so ignore any pagination click events
            return;
        }

        if (data.direction === 'left' && (currID === _.first(this.dropdownCollection.models).get('id')) ||
            data.direction === 'right' && (currID === _.last(this.dropdownCollection.models).get('id'))) {

            return;
        }

        // We can increment/decrement
        data.direction === 'left' ? currIndex-- : currIndex++;
        this.refreshPaginationArrows();

        if (currIndex >= 0 && currIndex < this.dropdownCollection.models.length) {
            let nextItemModel = this.dropdownCollection.models[currIndex];
            this.refreshPanelBody(nextItemModel);
            this.context.trigger('formatting-panel:pagination:changed', nextItemModel);
        }
    },

    /**
     * Shows the pagination buttons based on the current index of the selected dropnown item model
     */
    showPagination: function(currIndex) {
        this.layout.previous = currIndex > 0;
        this.layout.next = currIndex < this.dropdownCollection.models.length - 1;
        this.layout.render();
    },

    /**
     * @inheritdoc
     */
    _dispose: function() {
        this.stopListening();
        this.dropdownCollection = null;
        this._super('_dispose');
    }
})
