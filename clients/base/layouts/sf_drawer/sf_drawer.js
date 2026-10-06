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
({
    className: 'sf_drawer',
    finalUrl: null,

    events: {
        'click #closeButton': 'closeDrawer',
    },

    /**
     * @inheritdoc
     */
    initialize: function(options) {
        this.model = options.context.get('model') || {};
        this._buildUrl();
        this._super('initialize', [options]);
    },

    /**
     * Build Market view url and set it to finalUrl
     * @private
     */
    _buildUrl: function() {
        app.api.call('read', app.api.buildURL('connector/salesfusion'), null, {
            success: data => {
                this.finalUrl = data.iframeUrl;
            }
        }, {
            async: false
        });

        this.finalUrl = this.finalUrl.replace('{recordId}', this.model.id);
        this.finalUrl = this.finalUrl.replace('{crmType}', this.model.get('_module'));
    },

    /**
     * Close Market view drawer
     */
    closeDrawer: function() {
        app.drawer.close();
    },
})
