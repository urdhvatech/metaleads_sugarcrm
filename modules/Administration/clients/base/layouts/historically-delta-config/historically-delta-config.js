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
 * Base layout for Config Framework.
 *
 * @class View.Layouts.Base.HistoricallyDeltaConfigLayout
 * @alias SUGAR.App.view.layouts.BaseHistoricallyDeltaConfigLayout
 * @extends SUGAR.App.view.layouts.BaseAdministrationConfigLayout
 */
({
    extendsFrom: 'AdministrationConfigLayout',

    /**
     * @inheritdoc
     */
    _getCategory: function() {
        return 'historically-delta';
    }
})
