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
 * @class View.Views.Base.AdministrationPackageBuilderInstalledPackagesSubtabView
 * @alias SUGAR.App.view.views.BaseAdministrationPackageBuilderInstalledPackagesSubtabView
 * @extends View.View
 */
({
    /**
     * Entries to display in the table
     */
    entries: [],

    /**
     * Indicates whether there are entries to display in the table
     */
    isEntries: false,

    /**
     * Headers to display in the table
     */
    headers: [
        'Name',
        'Version',
        'Description',
    ],

    /**
     * Header labels
     */
    headerLabels: [
        'LBL_PACKAGE_BUILDER_NAME',
        'LBL_PACKAGE_BUILDER_VERSION',
        'LBL_PACKAGE_BUILDER_DESCRIPTION',
    ],

    /**
     * @inheritdoc
     */
    initialize: function(options) {
        this._super('initialize', [options]);

        // Sync view customizations
        this.entries = options.installedPackages;
        this.isEntries = !_.isEmpty(this.entries);
    },
});
