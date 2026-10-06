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
 * @class View.Layouts.Base.Users.ListLayout
 * @alias SUGAR.App.view.layouts.BaseUsersListLayout
 * @extends View.Layouts.Base.ListLayout
 */
({
    extendsFrom: 'BaseListLayout',

    initialize: function(options) {
        // currently, the user list layout is only available from the administrative page
        options.layout = options.layout || {};
        options.layout.isAdminPage = true;
        this._super('initialize', [options]);
    },
})
