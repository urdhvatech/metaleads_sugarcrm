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
 * @class View.Views.Base.AdministrationFormattingPanelRemoveView
 * @alias SUGAR.App.view.views.BaseAdministrationFormattingPanelRemoveView
 * @extends View.Views
 */
({
    // Saves the selected dropnown item model
    itemModel: null,

    /**
     * Store a dropdown style template
     */
    dropdownStyleTemplate: {
        backgroundColor: '',
        icon: {
            class: '',
            color: '',
        },
        text: {
            color: '',
            isBold: false,
            isItalic: false,
            isLineThrough: false,
            isUnderline: false,
        },
        colorway: {
            title: '',
            class: 'no_style',
        },
    },

    events: {
        'click .remove-formatting': 'confirmRemovingFormatting',
    },

    /*
     * Confirms the removing formatting
     */
    confirmRemovingFormatting: function() {
        app.alert.show('dropdown_editor_remove_formatting', {
            level: 'confirmation',
            messages: app.lang.get('LBL_DROPDOWN_REMOVE_FORMATTING_CONFIRM', this.module),
            autoClose: false,
            onConfirm: () => {
                this.removeFormatting();
            },
        });
    },

    /**
     * @inheritdoc
     */
    bindDataChange: function() {
        this._super('bindDataChange');
        this.listenTo(this.context, 'formatting-panel:state:changed', this.cacheModelData);
        this.listenTo(this.context, 'formatting-style:reset_style', this.resetStyle);
        this.listenTo(this.context, 'formatting-panel:pagination:changed', this.cacheModelData);
    },

    /**
     * Remove formatting of the dropdown item
     */
    removeFormatting: function() {
        let dropdownStyle = JSON.parse(JSON.stringify(this.dropdownStyleTemplate));
        dropdownStyle = this._templateRemoveFormatting(dropdownStyle);
        const currentStyle = (this.itemModel && this.itemModel.get('dropdownStyle')) || {};
        dropdownStyle.prevBgColor = currentStyle.prevBgColor || '';

        this._updateItemModel(dropdownStyle);
    },

    /**
     * Reset the style of the dropdown item to the default style
     *
     * @param {Object} styleModel - The selected dropdown item style
     */
    resetStyle: function(styleModel) {
        let dropdownStyle = JSON.parse(JSON.stringify(this.dropdownStyleTemplate));
        dropdownStyle.text = styleModel.text;
        dropdownStyle.text.color = '';
        dropdownStyle = this._templateRemoveFormatting(dropdownStyle);
        dropdownStyle.icon.class = styleModel.icon.class;
        dropdownStyle.prevBgColor = styleModel.prevBgColor;

        this._updateItemModel(dropdownStyle);
    },

    /**
     * Refreshes the panel body with the selected dropdown item model
     * @param {Object} model - The selected dropdown item model
     */
    cacheModelData: function(model) {
        this.itemModel = model;
    },

    /**
     * Create the default dropdown item style for the remove formatting
     *
     * @param {Object} dropdownStyle - The selected dropdown item style
     * @return {Object} dropdownStyle - The default dropdown item style
     * @private
     */
    _templateRemoveFormatting: function(dropdownStyle) {
        dropdownStyle.colorway = {
            class: 'no_style',
            title: app.lang.get('LBL_DROPDOWN_NO_STYLE', this.module),
        };

        return dropdownStyle;
    },

    /**
     * Update the style of the dropdown item with the new style
     *
     * @param {Object} dropdownStyle - The default dropdown item style
     * @private
     */
    _updateItemModel: function(dropdownStyle) {
        this.itemModel.set('dropdownStyle', dropdownStyle);

        this.context.trigger('formatting-style:removed', this.itemModel.cid);
    }
})
