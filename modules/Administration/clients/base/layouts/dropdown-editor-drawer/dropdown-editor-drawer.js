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
 * @class View.Layouts.Base.AdministrationDropdownEditorDrawerLayout
 * @alias SUGAR.App.view.layouts.AdministrationBaseDropdownEditorDrawerLayout
 * @extends View.Layout
 */
({
    /**
     * @inheritdoc
     */
    bindDataChange: function() {
        this._super('bindDataChange');
        this.listenTo(this.context, 'button:formatting-button:click', this.openFormattingPanel);
        this.listenTo(this.context, 'formatting-panel:close', this.closeFormattingPanel);
    },

    /**
     * Opens the Formatting Panel.
     */
    openFormattingPanel: function(model) {
        this._toggleFormattingPanel(true, model);
    },

    /**
     * Closes the Formatting Panel.
     */
    closeFormattingPanel: function() {
        this._toggleFormattingPanel(false);
    },

    /**
     * Toggles the Formatting Panel.
     *
     * @param Boolean visible `true` to show the Formatting Panel, `false` otherwise.
     * @private
     */
    _toggleFormattingPanel: function(visible, model = null) {
        this.$('.main-pane').toggleClass('span12', !visible).toggleClass('span8', visible);
        this.$('.side').toggleClass('side-collapsed', !visible);
        // Lets the rest of the dropdown editor components know if the Formatting Pane changed state
        this.context.trigger('formatting-panel:state:changed', model);
    },

    /**
     * @inheritdoc
     */
    _dispose: function() {
        this.stopListening();
        this._super('_dispose');
    }
})
