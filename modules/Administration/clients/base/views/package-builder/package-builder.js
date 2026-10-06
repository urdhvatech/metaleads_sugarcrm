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
 * @class View.Views.Base.AdministrationPackageBuilderView
 * @alias SUGAR.App.view.views.BaseAdministrationPackageBuilderView
 * @extends View.View
 */
({
    /**
     * Tab views
     */
    tabsView: [],

    /**
     * Customizations
     */
    customizations: [],

    /**
     * Active tab
     */
    activeTab: 'configuration',

    /**
     * Tab list
     */
    tabList: [
        {
            'value': 'configuration',
            'label': 'LBL_PACKAGE_BUILDER_TAB_CONFIG',
            'tooltip': 'LBL_PACKAGE_BUILDER_TAB_CONFIG_HELP',
            'active': true,
            'disabled': false
        },
        {
            'value': 'customizations',
            'label': 'LBL_PACKAGE_BUILDER_TAB_CUSTOMIZATIONS',
            'tooltip': 'LBL_PACKAGE_BUILDER_TAB_CUSTOMIZATIONS_HELP',
            'active': false,
            'disabled': true
        },
        {
            'value': 'packages',
            'label': 'LBL_PACKAGE_BUILDER_TAB_PACKAGES',
            'tooltip': 'LBL_PACKAGE_BUILDER_TAB_PACKAGES_HELP',
            'active': false,
            'disabled': false
        },
    ],

    /**
     * Event handlers
     */
    events: {
        'click .configuration_tab': 'tabChanged',
        'click .customizations_tab': 'tabChanged',
        'click .packages_tab': 'tabChanged',
        'click a[name="cancel_button"]': 'cancelClicked',
    },

    /**
     * Alert
     */
    alertId: 'pb-fetch-local-packages',

    /**
     * @inheritdoc
     */
    initialize: function(options) {
        this._super('initialize', [options]);
        this.checkIfRefetched();
        this.context.set('installedPackagesFetched', false);
    },

    /**
     * @inheritdoc
     */
    render: function() {
        this._super('render');
        this.showTabContent();
    },

    /**
     * If view was previously used and customizations were loaded, set dataRefetched true
     * this flag will reset the selected rows saved data
     */
    checkIfRefetched: function() {
        if ('configuration' in this.tabsView && 'customizations' in this.tabsView) {
            this.tabsView.configuration.dataRefetched = true;
        }
    },

    /**
     * Tab changed event
     * @param {Event} $el
     */
    tabChanged: function($el) {
        let newTab = $el.target.name;
        if (newTab === 'packages' && !this.context.get('installedPackagesFetched')) {
            $el.stopPropagation();
            this.fetchLocalPackages();
            return;
        }
        this.activeTab = newTab;
        this.activeTabChanged();

        // For the customization tab, remove the notification dot and animation
        if (this.activeTab === 'customizations') {
            this.$el.find('.' + this.activeTab + 'Dot').hide();
            this.$el.find('.' + this.activeTab + '_tab').removeClass('start_animation');
        }

        this.showTabContent();
    },

    /**
     * Change the active tab
     */
    activeTabChanged: function() {
        let oldActiveTab = this.$el.find('.parentTabs .active')[0];
        let newActiveTab = this.$el.find('.parentTabs .' + this.activeTab + '_tab')[0];

        if (_.isUndefined(oldActiveTab) === false &&
            _.isUndefined(newActiveTab) === false &&
            oldActiveTab !== newActiveTab) {
            oldActiveTab.classList.remove('active'); // Remove previous active tab class
            newActiveTab.classList.add('active'); // Add active class to the current active tab
        }
    },

    /**
     * Show tab content
     */
    showTabContent: function() {
        let tabContent = this.$el.find('.tab-content');

        // Before we go to the Customizations tab, make sure the customizations are synced
        if (this.activeTab === 'customizations') {
            this.syncCustomizations();
            if (this.tabsView.configuration.dataRefetched === true) {
                this.resetCustomizationsSubtabs();
                this.tabsView.configuration.dataRefetched = false;
            }
        }

        if (_.isUndefined(this.tabsView[this.activeTab])) {
            let tabView;

            if (this.activeTab === 'packages') {
                tabView = app.view.createView({
                    name: 'package-builder-' + this.activeTab + '-tab',
                });
            } else {
                tabView = app.view.createView({
                    name: 'package-builder-' + this.activeTab + '-tab',
                    customizations: this.customizations,
                });
            }

            tabView.render();

            tabContent.empty();
            tabContent.append(tabView.$el);
            this.tabsView[this.activeTab] = tabView;
        } else {
            tabContent.empty();
            tabContent.append(this.tabsView[this.activeTab].$el);
            this.tabsView[this.activeTab].customizations = this.customizations;
            this.tabsView[this.activeTab].render();
        }

    },

    /**
     * Sync customizations
     */
    syncCustomizations: function() {
        // If customizations were fetched, set them tot this.customizations
        if (_.isUndefined(this.tabsView.configuration) === false &&
            _.isUndefined(this.tabsView.configuration.customizations) === false) {
            this.customizations = this.tabsView.configuration.customizations;
        }
    },

    /**
     * Fetch local packages
     */
    fetchLocalPackages: function() {
        let url = app.api.buildURL('Administration/package/customizations');
        let data = {elementsToFetch: ['installed_packages']};
        let callback = {
            success: function(data) {
                if ('installed_packages' in data) {
                    let installedPackages = {};
                    _.each(data.installed_packages, function(installedPackage) {
                        installedPackages[installedPackage.id] = installedPackage;
                    });
                    this.context.set('installedPackages', installedPackages);
                    this.context.set('installedPackagesFetched', true);
                }
            }.bind(this),
            error: function(errorData) {
                let categoryLabel = app.lang.get('LBL_PACKAGE_BUILDER_TAB_PACKAGES_I_PACKAGES_T', 'Administration');
                let message = app.lang.get(
                    'LBL_PACKAGE_BUILDER_CATEGORY_RETRIEVE_FAILED',
                    'Administration',
                    {category: categoryLabel}
                );

                app.alert.show('pb-error', {
                    level: 'error',
                    messages: message
                });
            }.bind(this),
            complete: function() {
                app.alert.dismiss(this.alertId);
                // Check if local packages were extracted
                if (!_.isUndefined(this.context.get('installedPackages'))) {
                    this.activeTab = 'packages';
                    this.activeTabChanged();
                    this.showTabContent();
                }
            }.bind(this)
        };
        // Create and display progress alert
        app.alert.show(this.alertId, {
            level: 'process',
            messages: app.lang.get('LBL_LOADING'),
            autoClose: false,
        });
        app.api.call('create', url, data, callback);
    },

    /**
     * Reset customizations subtabs
     */
    resetCustomizationsSubtabs: function() {
        if (_.isUndefined(this.tabsView.customizations) ||
            _.isUndefined(this.tabsView.customizations.customizationsTabsView)) {
            return;
        }

        _.each(this.tabsView.customizations.customizationsTabsView, function(subtabView) {
            subtabView.dispose();
        });

        this.tabsView.customizations.customizationsTabsView = {};
    },

    /**
     * Cancel button clicked
     */
    cancelClicked: function() {
        app.router.navigate('#Administration', {trigger: true});
    },

    /**
     * @inheritdoc
     */
    _dispose: function() {
        if (_.isObject(this.tabsView.packages)) {
            this.tabsView.packages.dispose();
            delete this.tabsView.packages;
        }

        this._super('_dispose');

        if (_.isUndefined(this.tabsView.customizations) === false) {
            // Reset existing customizations subtabs, when existing view is disposed
            this.resetCustomizationsSubtabs();
        }
    },
});
