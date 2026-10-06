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
 * @class View.Views.Base.AdministrationPackageBuilderPackagesTabView
 * @alias SUGAR.App.view.views.BaseAdministrationPackageBuilderPackagesTabView
 * @extends View.View
 */
({
    /**
     * Package subtabs
     */
    packagesSubtabs: {},

    /**
     * Package subtabs views
     */
    packagesSubtabsView: {},

    /**
     * Connection info for the remote instance
     */
    connectionInfo: {},

    /**
     * Active subtab
     */
    activeSubtab: 'installed_packages',

    /**
     * @inheritdoc
     */
    initialize: function(options) {
        this._super('initialize', [options]);

        this.setDefaultValues();
    },

    /**
     * Set default values for the subtabs
     */
    setDefaultValues: function() {
        this.packagesSubtabs = {
            'installed_packages': {
                'value': 'installed_packages',
                'label': 'LBL_PACKAGE_BUILDER_TAB_PACKAGES_I_PACKAGES_T',
                'active': true,
                'disabled': false
            },
            'connection': {
                'value': 'connection',
                'label': 'LBL_PACKAGE_BUILDER_TAB_PACKAGES_CONNECTION_T',
                'active': false,
                'disabled': false
            },

            'remote_packages': {
                'value': 'remote_packages',
                'label': 'LBL_PACKAGE_BUILDER_TAB_PACKAGES_REMOTE_PACKAGES_T',
                'active': false,
                'disabled': true
            },
        };
    },

    /**
     * @inheritdoc
     */
    render: function() {
        // Handle the right active tab rendering
        this.buildTabs();

        // Call super
        this._super('render');
        this.initEvents();
        this.showPackagesSubtabContent();
    },

    /**
     * Build tabs
     */
    buildTabs: function() {
        _.each(this.packagesSubtabs, function(subtab) {
            subtab.active = (subtab.value === this.activeSubtab);
        }.bind(this));
    },

    /**
     * Add event listeners
     */
    initEvents: function() {
        // Tabs events
        let subtabs = this.$el.find('.packagesSubtab');
        _.each(subtabs, function(subtab) {
            subtab.addEventListener('click', this.packagesSubtabChanged.bind(this));
        }.bind(this));
    },

    /**
     * Change the active subtab
     * @param {Object} $el
     */
    packagesSubtabChanged: function($el) {
        this.activeSubtab = $el.target.getAttribute('name');
        this.activeSubtabChanged();

        if (this.activeSubtab === 'remote_packages') {
            this.$el.find('.' + this.activeSubtab + 'Dot').hide();
            this.$el.find('.' + this.activeSubtab + '_tab').removeClass('start_animation');
            this.packagesSubtabs.remote_packages.disabled = false;
        }

        this.showPackagesSubtabContent();
    },

    /**
     * Change the active subtab
     */
    activeSubtabChanged: function() {
        let oldActiveSubtab = this.$el.find('.package-subtab-list .tab.active')[0];
        let newActiveSubtab = this.$el.find('.package-subtab-list .' + this.activeSubtab + '_tab')[0];

        if (_.isUndefined(oldActiveSubtab) === false &&
            _.isUndefined(newActiveSubtab) === false &&
            oldActiveSubtab !== newActiveSubtab) {
            oldActiveSubtab.classList.remove('active'); // Remove previous active tab class
            newActiveSubtab.classList.add('active'); // Add active class to the current active tab
        }
    },

    /**
     * Show the subtab content
     */
    showPackagesSubtabContent: function() {
        let tabContent = this.$el.find('.subtab-content');

        if (this.activeSubtab === 'remote_packages') {
            this.syncConnectionData();
        }

        let installedPackages = this.context.get('installedPackages') || {};
        let otherInstancePackages = this.context.get('otherInstancePackages') || [];

        if (_.isUndefined(this.packagesSubtabsView[this.activeSubtab])) {
            let tabName = this.activeSubtab.replaceAll('_', '-');

            let tabView = app.view.createView({
                name: 'package-builder-' + tabName + '-subtab', //ex: ci-packages-connection-subtab
                installedPackages: installedPackages,
                connectionInfo: this.connectionInfo,
            });

            tabView.render();
            tabContent.empty();
            tabContent.append(tabView.$el);
            this.packagesSubtabsView[this.activeSubtab] = tabView;
        } else {
            tabContent.empty();
            tabContent.append(this.packagesSubtabsView[this.activeSubtab].$el);

            if (this.activeSubtab === 'installed_packages') {
                this.showInstalledPackages(installedPackages);
            }

            if (this.activeSubtab === 'remote_packages') {
                this.packagesSubtabsView[this.activeSubtab].entries = otherInstancePackages;
                this.packagesSubtabsView[this.activeSubtab].localInstancePackages = installedPackages;
            }

            this.packagesSubtabsView[this.activeSubtab].connectionInfo = this.connectionInfo;
            this.packagesSubtabsView[this.activeSubtab].render();
        }
    },

    /**
     * Sync connection data
     */
    syncConnectionData: function() {
        // If connection view is not rendered then return, we cannot sync
        if (_.isUndefined(this.packagesSubtabsView.connection)) {
            return;
        }

        if (!_.isUndefined(this.packagesSubtabsView.connection.connectionInfo)) {
            // Sync connection info (url, user, password)
            this.connectionInfo = this.packagesSubtabsView.connection.connectionInfo;
        }
    },

    /**
     * Show installed packages
     * @param {Object} installedPackages
     */
    showInstalledPackages: function(installedPackages) {
        this.packagesSubtabsView[this.activeSubtab].entries = installedPackages;
        this.packagesSubtabsView[this.activeSubtab].isEntries = !_.isEmpty(installedPackages);
    },

    /**
     * @inheritdoc
     */
    _dispose: function() {
        if (_.isObject(this.packagesSubtabsView.connection)) {
            this.packagesSubtabsView.connection.dispose();
            delete this.packagesSubtabsView.connection;
        }

        this._super('_dispose');
    },
});
