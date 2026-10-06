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
 * @class View.Fields.Base.AdministrationFormattingIconField
 * @alias SUGAR.App.view.fields.BaseAdministrationFormattingIconField
 * @augments View.Fields.Base.EnumField
 */
({
    extendsFrom: 'EnumField',

    /**
     * @inheritdoc
     */
    initialize: function(options) {
        this._super('initialize', [options]);

        this.model.set(this.name, '');
        // Set the initial value from the view's itemModel if available
        if (this.view && this.view.itemModel) {
            const dropdownStyle = this.view.itemModel.get('dropdownStyle');

            if (dropdownStyle && dropdownStyle.icon && dropdownStyle.icon.class) {
                this.model.set(this.name, 'sicon-' + dropdownStyle.icon.class);
            }
        }
    },

    /**
     * @inheritdoc
     */
    bindDataChange: function() {
        this._super('bindDataChange');
        this.listenTo(this.model, 'change:' + this.name, this.changeItemStyle);
    },

    /**
     * Lets the view know that the field has changed and the style of the corresponding drop-down list item
     * needs to be updated
     *
     */
    changeItemStyle: function() {
        let formattingStyle = this.model.get(this.name);
        formattingStyle = formattingStyle.replace('sicon-', '');
        this.view.itemModel.get('dropdownStyle').icon = this.view.itemModel.get('dropdownStyle').icon || {};
        this.view.itemModel.get('dropdownStyle').icon.class = formattingStyle;
        this.context.trigger('formatting-style:changed', this.view.itemModel.cid);
    },

    /**
     * @inheritdoc
     */
    getSelect2Options: function(optionsKeys) {
        optionsKeys.unshift('');
        let select2Options = {};
        select2Options = this._super('getSelect2Options', [optionsKeys]);

        if (_.contains(['icon'], this.def.formatOptions)) {
            select2Options.formatResult = _.bind(this.formatIconsOrColors, this);
            select2Options.formatSelection = _.bind(this.formatIconsOrColors, this);
        }

        return select2Options;
    },

    /**
     * Format options to show the icon or color associated with the value
     * @param opt
     * @returns {string|*|jQuery|HTMLElement}
     */
    formatIconsOrColors: function(opt) {
        // eslint-disable-next-line max-len
        const iconClasses = `h-3.5 inline-block ltr:mr-1.5 rtl:ml-1.5 rounded-sm sicon ${_.escape(opt.id)} color-icon w-3.5`;

        return $(`<span class="flex flex-row items-center"><i class="${iconClasses}"></i>${_.escape(opt.text)}</span>`);
    },

    /**
     * @inheritdoc
     */
    _loadTemplate: function() {
        this.type = 'enum';
        this._super('_loadTemplate');
    },
})
