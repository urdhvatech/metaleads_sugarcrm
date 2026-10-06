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
 * @class View.Fields.Base.AdministrationFormattingTextEmphasisField
 * @alias SUGAR.App.view.fields.BaseAdministrationFormattingTextEmphasisField
 * @extends View.Fields.Base.BaseField
 */
({
    extendsFrom: 'BaseField',

    /**
     * @inheritdoc
     */
    events: {
        'click .formatting-text-bold': 'boldSetting',
        'click .formatting-text-italic': 'italicSetting',
        'click .formatting-text-underline': 'underlineSetting',
        'click .formatting-text-strikethrough': 'strikethroughSetting',
    },

    /**
     * Lets the view know that the text-bold has changed and the style of the corresponding drop-down list item
     * needs to be updated
     */
    boldSetting: function(e) {
        let control = $(e.currentTarget);
        control.toggleClass('active');
        this.view.itemModel.attributes.dropdownStyle.text =
            this.view.itemModel.attributes.dropdownStyle.text || {};
        this.view.itemModel.attributes.dropdownStyle.text.isBold = control.hasClass('active');
        this.context.trigger('formatting-style:changed', this.view.itemModel.cid);
    },

    /**
     * Lets the view know that the text-italic has changed and the style of the corresponding drop-down list item
     * needs to be updated
     */
    italicSetting: function(e) {
        let control = $(e.currentTarget);
        control.toggleClass('active');
        this.view.itemModel.attributes.dropdownStyle.text =
            this.view.itemModel.attributes.dropdownStyle.text || {};
        this.view.itemModel.attributes.dropdownStyle.text.isItalic = control.hasClass('active');
        this.context.trigger('formatting-style:changed', this.view.itemModel.cid);
    },

    /**
     * Lets the view know that the text-underline has changed and the style of the corresponding drop-down list item
     * needs to be updated
     */
    underlineSetting: function(e) {
        let control = $(e.currentTarget);
        control.toggleClass('active');
        this.view.itemModel.attributes.dropdownStyle.text =
            this.view.itemModel.attributes.dropdownStyle.text || {};
        this.view.itemModel.attributes.dropdownStyle.text.isUnderline = control.hasClass('active');
        this.context.trigger('formatting-style:changed', this.view.itemModel.cid);
    },

    /**
     * Lets the view know that the text-strikethrough has changed and the style of the corresponding drop-down list item
     * needs to be updated
     */
    strikethroughSetting: function(e) {
        let control = $(e.currentTarget);
        control.toggleClass('active');
        this.view.itemModel.attributes.dropdownStyle.text =
            this.view.itemModel.attributes.dropdownStyle.text || {};
        this.view.itemModel.attributes.dropdownStyle.text.isLineThrough = control.hasClass('active');
        this.context.trigger('formatting-style:changed', this.view.itemModel.cid);
    },

    /**
     * Sets the actual styles of the text emphasis buttons based on the current view model's attributes.
     */
    setActualStyles: function() {
        if (this.view && this.view.itemModel) {
            let dropdownStyle = this.view.itemModel.get('dropdownStyle');

            if (dropdownStyle && dropdownStyle.text) {
                let formattingStyle = dropdownStyle.text;
                $('.formatting-text-bold').toggleClass('active', formattingStyle.isBold || false);
                $('.formatting-text-italic').toggleClass('active', formattingStyle.isItalic || false);
                $('.formatting-text-underline').toggleClass('active', formattingStyle.isUnderline || false);
                $('.formatting-text-strikethrough').toggleClass('active', formattingStyle.isLineThrough || false);
            }
        }
    },

    /**
     * @inheritdoc
     */
    _render: function() {
        this._super('_render');

        this.setActualStyles();
    }
})
