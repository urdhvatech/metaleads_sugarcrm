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
 * @class View.Fields.Base.AdministrationFormattingStyleField
 * @alias SUGAR.App.view.fields.BaseAdministrationFormattingStyleField
 * @extends View.Fields.Base.BaseField
 */
({
    /**
     * Modes for using a predefined color palette
     */
    modes: ['light', 'medium', 'dark'],

    /**
     * @inheritdoc
     */
    events: {
        'click .control': 'foldingStylePicker',
        'click .style-picker .box': 'pickStyle',
        'click .prev span': 'prevMode',
        'click .next span': 'nextMode',
    },

    /**
     * @inheritdoc
     */
    _loadTemplate: function() {
        this.keywordColors = {
            fRow: this.def.colors.slice(0, 7),
            sRow: this.def.colors.slice(7, 14),
        };

        let dropdownStyle = (this.view.itemModel) ? this.view.itemModel.get('dropdownStyle') : {};
        this.colorway = dropdownStyle.colorway || {
            title: app.lang.get('LBL_DROPDOWN_NO_STYLE', this.module),
        };

        this.template = app.template.getField(this.type, this.type, this.module);
    },

    /**
     * Folding (show/hide) Style Picker color sets.
     * @param {Event} e
     */
    foldingStylePicker: function(e) {
        let control = $(e.currentTarget);
        control.closest('.formatting-style').toggleClass('expanded');
    },

    /**
     * Pick some style, i.e. a predefined pair of colors: text color (icon) and background color.
     * @param {Event} e
     */
    pickStyle: function(e) {
        const dropdownStyle = this.view.itemModel.get('dropdownStyle');
        const $box = $(e.currentTarget);
        let color = $box.css('color');
        let backgroundColor = $box.css('background-color');

        if ($box.hasClass('no-style')) {
            this.context.trigger('formatting-style:reset_style', dropdownStyle);
            return;
        }

        color = this._rgbToHex(color).toUpperCase();
        backgroundColor = this._rgbToHex(backgroundColor).toUpperCase();

        let styleTitle = 'LBL_DROPDOWN_COLORWAY_' + $box.attr('data-mode') + '_' + $box.attr('data-name');
        styleTitle = app.lang.get(styleTitle.toUpperCase(), this.module);

        const colorwayClass = $box.data('mode') + '-' + $box.data('name');

        const $parent = $box.closest('.formatting-style');
        $('.control', $parent)
            .removeClass(dropdownStyle.colorway.class)
            .addClass(colorwayClass)
            .css('border-color', color)
            .find('span').text(styleTitle);

        dropdownStyle.text.color = color;
        dropdownStyle.icon.color = color;
        dropdownStyle.backgroundColor = backgroundColor;
        dropdownStyle.colorway = {
            title: styleTitle,
            class: colorwayClass
        };
        $box.closest('.formatting-style').removeClass('expanded');

        $('[data-name=formatting-text-color] input.colorValue').val(color);
        $('[data-name=formatting-text-color] input.textValue').val(color);

        $('[data-name=formatting-background-color] input.colorValue').val(backgroundColor);
        $('[data-name=formatting-background-color] input.textValue').val(backgroundColor);

        $('[data-name=formatting-icon-color] input.colorValue').val(color);
        $('[data-name=formatting-icon-color] input.textValue').val(color);

        this.context.trigger('formatting-style:changed', this.view.itemModel.cid);
    },

    /**
     * Move through the color palette - one block back or prev.
     */
    prevMode: function() {
        let curMode = $('.style-picker').attr('data-mode');
        let curIndex = this.modes.indexOf(curMode);
        let prevIndex = Math.max(--curIndex, 0);
        $('.style-picker').attr('data-mode', this.modes[prevIndex]);
    },

    /**
     * Move through the color palette - one block forward or next.
     */
    nextMode: function() {
        let curMode = $('.style-picker').attr('data-mode');
        let curIndex = this.modes.indexOf(curMode);
        let nextIndex = Math.min(++curIndex, this.modes.length - 1);
        $('.style-picker').attr('data-mode', this.modes[nextIndex]);
    },

    /**
     * Return hex from rgb
     *
     * @param {string} value - Color like rgb(106, 33, 166)
     * @return {string} color - Color in the hex format
     */
    _rgbToHex: function(value) {
        if (!value.toLowerCase().includes(['rgb'])) {
            return;
        }

        let color = value.match(/\d+/g);
        color = _.map(color, function(color) {
            return parseInt(color);
        });

        return '#' + this._componentToHex(color[0]) +
            this._componentToHex(color[1]) +
            this._componentToHex(color[2]);
    },

    /**
     * Convert rgb color to hex
     *
     * @param {int} comp
     * @return {string} value - in the hex format
     */
    _componentToHex: function(comp) {
        let hex = comp.toString(16);
        return hex.length == 1 ? '0' + hex : hex;
    },
})
