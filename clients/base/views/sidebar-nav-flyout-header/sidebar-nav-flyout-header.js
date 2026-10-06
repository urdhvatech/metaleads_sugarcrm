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
 * @class View.Views.Base.SidebarNavFlyoutHeaderView
 * @alias SUGAR.App.view.views.BaseSidebarNavFlyoutHeaderView
 * @extends View.View
 */
({
    className: 'sidebar-nav-flyout-header bg-[--foreground-base] flex flex-row h-6 items-center' +
        ' justify-between relative text-base font-semibold py-3 pl-3 pr-2 rounded-md',

    /**
     * @inheritdoc
     */
    initialize: function(options) {
        this._super('initialize', [options]);
        this.title = options.def.title || '';
    }
})
