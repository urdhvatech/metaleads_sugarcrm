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
 * @class View.Views.Base.HistoricallyDeltaConfigHeaderView
 * @alias SUGAR.App.view.views.BaseHistoricallyDeltaConfigHeaderView
 * @extends View.Views.Base.AdministrationHistoricallyDeltaConfigHeaderView
 */
({
    extendsFrom: 'AdministrationConfigHeaderView',

    /**
     * @inheritdoc
     */
    getTitle: function() {
        let title = '';

        const module = this.context.get('target');

        if (module) {
            title = app.lang.get('LBL_HISTORICALLY_DELTA_SETTINGS_TITLE', 'Administration',
                {moduleSingular: app.lang.getModuleName(module)});
        }

        return title;
    },

    /**
     * @inheritdoc
     */
    _render: function(options) {
        this._super('_render', [options]);

        this.enableButton(true);
    },
})
