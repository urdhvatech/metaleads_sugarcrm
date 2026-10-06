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
 * @class View.Fields.Base.Gai_needed_followupField
 * @alias SUGAR.App.view.fields.Gai_needed_followupField
 * @extends View.Fields.Base.TextField
 */
({
    extendsFrom: 'TextField',

    /**
     * @inheritdoc
     */
    initialize: function(options) {
        this._super('initialize', [options]);

        this._initProperties();
    },

    /**
     * Property initialization
     *
     */
    _initProperties: function() {
        this.neededFollowup = null;
        this.invalidNeededFollowup = null;
    },

    /**
     * @inheritdoc
     */
    _render: function() {
        this._super('_render');
        this.bindClickHandlers();
    },

    /**
     * @inheritdoc
     */
    format: function(value) {
        try {
            const decodedValue = JSON.parse(value);
            this.neededFollowup = decodedValue;

            return decodedValue;
        } catch (e) {
            this.invalidNeededFollowup = value;

            return value;
        }
    },

    /**
     * @inheritdoc
     *
     * Trim whitespace from value.
     */
    unformat: function(value) {
        return value.trim();
    },

    /**
     * Binds click handlers for links and icons inside the field.
     */
    bindClickHandlers: function() {
        this.bindLinkClick('.focus-icon[data-module][data-id]', 'focus-bound', this.openFocusDrawer);
    },

    /**
     * Helper method to bind a click event to a selector once.
     *
     * @param {string} selector
     * @param {string} dataKey - data attribute to track if already bound
     * @param {Function} handler - click handler method
     */
    bindLinkClick: function(selector, dataKey, handler) {
        const self = this;

        this.$(selector).each(function() {
            const $el = $(this);
            $el.off('click');

            if (!$el.data(dataKey)) {
                $el.data(dataKey, true);
                $el.on('click', function(event) {
                    handler.call(self, event);
                });
            }
        });
    },

    /**
     * Opens the focus drawer for a given record.
     */
    openFocusDrawer: function(event) {
        event.preventDefault();
        event.stopPropagation();

        const $target = $(event.currentTarget);
        const module = $target.data('module');
        const objectId = $target.data('id');
        const name = $target.data('name');

        const dataTitle = app.sideDrawer.getDataTitle(
            module,
            'LBL_FOCUS_DRAWER_DASHBOARD',
            name
        );

        const recordContext = {
            layout: 'row-model-data',
            dashboardName: name,
            context: {
                layout: 'focus',
                contentType: 'dashboard',
                module,
                modelId: objectId,
                dataTitle,
                fieldDefs: app.metadata.getModule(module, 'fields').name,
                baseModelId: objectId,
                evtSource: $target
            }
        };

        app.sideDrawer.open(recordContext, null, true);
    },

    /**
     * Loads the template for the field
     */
    _loadTemplate: function() {
        this._super('_loadTemplate');
        this.template = app.template.getField('gai_needed_followup', 'gai_needed_followup', this.module);
    }
})
