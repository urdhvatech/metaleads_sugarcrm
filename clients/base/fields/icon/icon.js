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
 * @class View.Fields.Base.IconField
 * @alias SUGAR.App.view.fields.BaseIconField
 * @extends View.Fields.Base.BaseField
 */
({
    /**
     * @inheritdoc
     */
    initialize: function(options) {
        this._super('initialize', [options]);

        this.initializeProperties(options);
    },

    initializeProperties: function(options) {
        this.type = 'icon';
        this.label = options.def.label || '';
        this.iconName = options.def.name || 'icon';
        this.style = options.def.style || '';
        this.tooltipPlacement = options.def.tooltipPlacement || 'top';
    },

    /**
     * @inheritdoc
     */
    _loadTemplate: function() {
        this._super('_loadTemplate');

        this.template = app.template.getField(this.type, 'icon', this.module);
    }
})
