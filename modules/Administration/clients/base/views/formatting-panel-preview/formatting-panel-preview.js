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
 * @class View.Views.Base.AdministrationFormattingPanelPreviewView
 * @alias SUGAR.App.view.views.BaseAdministrationFormattingPanelPreviewView
 * @extends View.Views
 */
({
    // Saves the selected dropnown item model
    itemModel: null,

    // We need to store these changes here because the model may not change in real time
    valuesChangedDisplayLabels: [],

    /**
     * @inheritdoc
     */
    bindDataChange: function() {
        this._super('bindDataChange');
        this.listenTo(this.context, 'formatting-panel:state:changed', this.refreshPanelPreview);
        this.listenTo(this.context, 'formatting-panel:dropdown_label:changed', this.changeDropdownLabel);
        this.listenTo(this.context, 'formatting-panel:pagination:changed', this.refreshPanelPreview);
        this.listenTo(this.context,'formatting-style:changed', this.render);
        this.listenTo(this.context,'formatting-style:removed', this.render);
    },

    /**
     * Refreshes the panel preview with the selected dropdown item model
     *
     * @param {Object} model - The selected dropdown item model
     */
    refreshPanelPreview: function(model) {
        this.itemModel = model;

        if (model && this.valuesChangedDisplayLabels[model.cid]) {
            this.itemModel.set('dropdown_label', this.valuesChangedDisplayLabels[model.cid]);
        }

        this.render();
    },

    /**
     * Processing the field change event and displaying its value in the Formatting panel
     *
     * @param {string} rowId
     * @param {string} value
     */
    changeDropdownLabel: function(rowId, value) {
        this.valuesChangedDisplayLabels[rowId] = value;

        if (!this.itemModel || this.itemModel.cid !== rowId) {
            return;
        }

        this.itemModel.set('dropdown_label', value);
        this.render();
    }
})
