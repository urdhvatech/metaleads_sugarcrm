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
 * @class View.Views.Base.Home.AboutHeaderpaneView
 * @alias SUGAR.App.view.views.BaseHomeAboutHeaderpaneView
 * @extends View.Views.Base.HeaderpaneView
 */
({
    extendsFrom: 'HeaderpaneView',

    /**
     * @override
     *
     * Formats the title with the current server info.
     */
    _formatTitle: function(title) {
        var serverInfo = app.metadata.getServerInfo();
        var aboutVars = {
            product_name: serverInfo.product_name,
            version: serverInfo.version,
            custom_version: serverInfo.custom_version,
            build: serverInfo.build,
        };
        return app.lang.get(title, this.module, aboutVars);
    }
})
