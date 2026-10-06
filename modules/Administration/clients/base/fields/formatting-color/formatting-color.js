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
 * @class View.Fields.Base.AdministrationFormattingColorField
 * @alias SUGAR.App.view.fields.BaseAdministrationFormattingColorField
 * @augments View.Fields.Base.BaseField
 */
({
    /**
     * @inheritdoc
     */
    events: {
        'input .colorValue': 'changeItemStyle',
        'change .colorValue': 'changeItemStyle', // override the native change event
        'input .textValue': 'changeItemStyle',
        'change .textValue': 'changeItemStyle', // override the native change event
    },

    /**
     * @inheritdoc
     */
    initialize: function() {
        // eslint-disable-next-line prefer-rest-params
        this._super('initialize', arguments);

        if (this?.view?.itemModel) {
            const dropdownStyle = this.view.itemModel.get('dropdownStyle');

            if (dropdownStyle) {
                switch (this.name) {
                case 'formatting-background-color':
                    this.model.set(this.name, dropdownStyle.backgroundColor);
                    break;
                case 'formatting-icon-color':
                    this.model.set(this.name, dropdownStyle.icon.color);
                    break;
                case 'formatting-text-color':
                    this.model.set(this.name, dropdownStyle.text.color);
                    break;
                }
            }
        }
    },

    /**
     * @inheritdoc
     */
    _render: function() {
        this._super('_render');

        this.formattingTextColor();
        this.formattingBackgroundColor();
        this.formattingIconColor();
    },

    /**
     * Formatting text color field initialization
     */
    formattingTextColor: function() {
        if (this.name === 'formatting-text-color' && this?.view?.itemModel) {
            const dropdownStyle = this.view.itemModel.get('dropdownStyle');
            if (dropdownStyle) {
                const defaultColor = app.utils.isDarkMode() ? '#FFFFFF' : '#000000';
                this._changeColorInColorPicker(dropdownStyle.text.color, defaultColor);
            }
        }
    },

    /**
     * Formatting background color field initialization
     */
    formattingBackgroundColor: function() {
        if (this.name === 'formatting-background-color' && this?.view?.itemModel) {
            const dropdownStyle = this.view.itemModel.get('dropdownStyle');
            if (dropdownStyle) {
                const defaultColor = app.utils.isDarkMode() ? '#000000' : '#FFFFFF';
                this._changeColorInColorPicker(dropdownStyle.backgroundColor, defaultColor);
            }
        }
    },

    /**
     * Formatting icon color field initialization
     */
    formattingIconColor: function() {
        if (this.name === 'formatting-icon-color' && this?.view?.itemModel) {
            const dropdownStyle = this.view.itemModel.get('dropdownStyle');
            if (dropdownStyle) {
                const defaultColor = app.utils.isDarkMode() ? '#FFFFFF' : '#000000';
                this._changeColorInColorPicker(dropdownStyle.icon.color, defaultColor);
            }
        }
    },

    /**
     * Change color to default in the color picker to ensure proper rendering after changing user mode.
     *
     * @param {string} prop
     * @param {string} color
     */
    _changeColorInColorPicker: function(prop, color) {
        if (_.isEmpty(prop)) {
            $(`[data-name=${this.name}] input.colorValue`).val(color);
        }
    },

    /**
     * Normalize HEX: returns uppercase with leading '#', or empty string if invalid
     */
    _normalizeHex: function(val) {
        if (!_.isString(val)) {
            return '';
        }
        const raw = val.trim();
        if (raw === '') {
            return '';
        }
        const hex = raw.replace(/^#/, '');
        const isValid = /^[0-9a-fA-F]{3}$/.test(hex) || /^[0-9a-fA-F]{6}$/.test(hex);
        return isValid ? ('#' + hex.toUpperCase()) : '';
    },

    /**
     * Normalize HEX for color input: expands 3-char to 6-char for HTML5 compatibility
     */
    _normalizeHexForColorInput: function(val) {
        if (!_.isString(val)) {
            return '';
        }
        const raw = val.trim();
        if (raw === '') {
            return '';
        }
        const hex = raw.replace(/^#/, '');

        // Check if it's a valid 3-character hex code
        if (/^[0-9a-fA-F]{3}$/.test(hex)) {
            // Expand 3-char to 6-char: abc -> aabbcc
            const expanded = hex.split('').map((char) => char + char).join('');
            return '#' + expanded.toUpperCase();
        }

        // Check if it's a valid 6-character hex code
        if (/^[0-9a-fA-F]{6}$/.test(hex)) {
            return '#' + hex.toUpperCase();
        }

        return '';
    },

    /**
     * Lets the view know that the field has changed and the style of the corresponding drop-down list item
     *
     * @param event
     */
    changeItemStyle: function(event) {
        // Coerce missing structures on every change
        const dropdownStyle = this.view.itemModel.get('dropdownStyle') || {};
        dropdownStyle.icon = dropdownStyle.icon || {};
        dropdownStyle.text = dropdownStyle.text || {};

        const typed = event.currentTarget.value;
        const normalized = this._normalizeHex(typed);
        const isValid = (normalized !== '') || typed.trim() === '';

        // toggle inline validation UI (class used by field templates/styles)
        const $root = this.$el;
        $root.toggleClass('error', !isValid);
        $root.find('.hex-error').toggleClass('hidden', isValid);

        switch (this.name) {
        case 'formatting-background-color': {
            dropdownStyle.backgroundColor = normalized;
            break;
        }
        case 'formatting-icon-color': {
            dropdownStyle.icon.color = normalized;
            break;
        }
        case 'formatting-text-color': {
            dropdownStyle.text.color = normalized;
            break;
        }
        }

        const colorwayClass = (dropdownStyle.colorway && dropdownStyle.colorway.class) || '';
        if (colorwayClass) {
            $(`.control.${colorwayClass}`)
                .removeClass(colorwayClass)
                .css('border-color', '')
                .find('span').text(app.lang.get('LBL_DROPDOWN_NO_STYLE', this.module));
        }

        dropdownStyle.colorway = {
            class: 'no_style',
            title: app.lang.get('LBL_DROPDOWN_NO_STYLE', this.module),
        };

        if ($(event.currentTarget).hasClass('colorValue')) {
            const v = this._normalizeHex(typed);
            $(`[data-name=${this.name}] input.textValue`).val(v);
        }

        if ($(event.currentTarget).hasClass('textValue')) {
            if (normalized) {
                // Use expanded hex for color input (3-char -> 6-char)
                const colorInputValue = this._normalizeHexForColorInput(typed);
                $(`[data-name=${this.name}] input.colorValue`).val(colorInputValue);
                // Update text input with normalized value
                $(`[data-name=${this.name}] input.textValue`).val(normalized);
            } else {
                // Keep invalid text as-is, don't update color input
                $(`[data-name=${this.name}] input.textValue`).val(typed);
            }
        }

        if (event.type !== 'change') {
            this.context.trigger('formatting-style:changed', this.view.itemModel.cid);
        }

        this.view.itemModel.set('dropdownStyle', dropdownStyle);

        event.stopPropagation();
        event.preventDefault();
    },
})
