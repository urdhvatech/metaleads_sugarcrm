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
 * @class View.Views.Base.AdministrationPackageBuilderConfigurationTabView
 * @alias SUGAR.App.view.views.BaseAdministrationPackageBuilderConfigurationTabView
 * @extends View.View
 */
({
    /**
     * Flag to check if the data was refetched
     */
    dataRefetched: false,

    /**
     * Customizations extracted from the instance
     */
    customizations: false,

    /**
     * Connection info for remote instance
     */
    connectionInfo: false,

    /**
     * Progress alert view
     */
    progressAlertView: false,

    /**
     * Elements selected to fetch
     */
    elementsSelectedToFetch: [],

    /**
     * Lablels for categories
     */
    categoriesLabels: {
        acl: 'LBL_PACKAGE_BUILDER_TAB_CUSTOMIZATIONS_ACL_T',
        advanced_workflows: 'LBL_PACKAGE_BUILDER_TAB_CUSTOMIZATIONS_ADVANCEDWORKFLOWS_T',
        dashboards: 'LBL_PACKAGE_BUILDER_TAB_CUSTOMIZATIONS_DASHBOARDS_T',
        dropdowns: 'LBL_PACKAGE_BUILDER_TAB_CUSTOMIZATIONS_DROPDOWNS_T',
        fields: 'LBL_PACKAGE_BUILDER_TAB_CUSTOMIZATIONS_FIELDS_T',
        language: 'LBL_PACKAGE_BUILDER_TAB_CUSTOMIZATIONS_LANGUAGE_T',
        layouts: 'LBL_PACKAGE_BUILDER_TAB_CUSTOMIZATIONS_LAYOUTS_T',
        miscellaneous: 'LBL_PACKAGE_BUILDER_TAB_CUSTOMIZATIONS_MISCELLANEOUS_T',
        relationships: 'LBL_PACKAGE_BUILDER_TAB_CUSTOMIZATIONS_RELATIONSHIPS_T',
        reports: 'LBL_PACKAGE_BUILDER_TAB_CUSTOMIZATIONS_REPORTS_T',
        workflows: 'LBL_PACKAGE_BUILDER_TAB_CUSTOMIZATIONS_WORKFLOWS_T',
    },

    /**
     * All categories
     */
    categoriesOptions: {
        acl: 'Roles',
        advanced_workflows: 'Process Definitions',
        dashboards: 'Dashboards',
        dropdowns: 'Dropdowns',
        fields: 'Fields',
        language: 'Language',
        layouts: 'Layouts',
        miscellaneous: 'Miscellaneous',
        relationships: 'Relationships',
        reports: 'Reports',
        workflows: 'Workflows',
    },

    /**
     * Default categories
     */
    categoriesDefault: [
        'dashboards',
        'dropdowns',
        'fields',
        'language',
        'layouts',
        'relationships',
        'reports',
    ],

    /**
     * @inheritdoc
     */
    initialize: function(options) {
        this._super('initialize', [options]);
        this.setDefaultValues();
    },

    /**
     * Set default values for the categories field
     */
    setDefaultValues: function() {
        _.each(this.categoriesLabels, function(label, key) {
            this.categoriesOptions[key] = app.lang.get(label, 'Administration');
        }, this);
        this.model.set('categories', this.categoriesDefault);
    },

    /**
     * Add events to the view
     */
    initEvents: function() {
        if (_.isUndefined(this.context._events) ||
            _.isEmpty(this.context._events['button:fetch_customizations_button:click'])) {
            this.listenTo(this.context, 'button:fetch_customizations_button:click',
                this.fetchCustomizations.bind(this));
        }

        // click event for each Push Package button
        this.$el.find('.selectAllButton').click(this.selectAll.bind(this));
    },

    /**
     * @inheritdoc
     */
    render: function() {
        this._super('render');
        this.initEvents();
    },

    /**
     * Fetch customizations
     */
    fetchCustomizations: function() {
        this.elementsSelectedToFetch = this.model.get('categories');

        if (_.isEmpty(this.elementsSelectedToFetch)) {
            app.alert.show('empty-categories-alert', {
                'level': 'error',
                'messages': app.lang.get('LBL_PACKAGE_BUILDER_TAB_CONFIG_EMPTY_C_BUTTON', 'Administration'),
                'autoClose': true
            });

            return;
        }

        this.extractCustomizations(this.elementsSelectedToFetch);
    },

    /**
     * Select all categories
     */
    selectAll: function() {
        const allValues = Object.keys(this.categoriesOptions);

        if (this.model.get('categories').length === allValues.length) {
            this.model.set('categories', []);
        } else {
            this.model.set('categories', allValues);
        }
    },

    /**
     * Extract customizations
     * @param {Array} elementsToFetch
     */
    extractCustomizations: function(elementsToFetch) {
        let url = '';
        this.data = {};
        let numberElements = elementsToFetch.length;
        let nrProcessedEl = 0;

        if (!_.isEmpty(this.customizations)) {
            this.dataRefetched = true;
        }

        // Reset customizations
        this.customizations = {};

        url = app.api.buildURL('Administration/package/customizations');

        // Create and display progress alert
        this.showFetchingAlert();

        _.each(elementsToFetch, function callForEachElement(element) {
            let data = {elementsToFetch: [element]};

            let callback = {
                success: function(data) {
                    if ('installed_packages' in data) {
                        let installedPackages = {};
                        _.each(data.installed_packages, function(installedPackage) {
                            installedPackages[installedPackage.id] = installedPackage;
                        });
                        this.context.set('installedPackages', installedPackages);
                    } else {
                        Object.assign(this.customizations, data);
                    }

                    nrProcessedEl++;

                    let calculatedProgress = this.getPercentage(nrProcessedEl, numberElements);
                    let message = app.lang.get(
                        'LBL_PACKAGE_BUILDER_CATEGORY_RECEIVED',
                        'Administration',
                        {category: this.categoriesOptions[element]}
                    );

                    this.progressAlertView.logMessage(message);
                    this.progressAlertView.setProgress(calculatedProgress);
                }.bind(this),
                error: function(errorData) {
                    nrProcessedEl++;

                    let calculatedProgress = this.getPercentage(nrProcessedEl, numberElements);
                    let message = app.lang.get(
                        'LBL_PACKAGE_BUILDER_CATEGORY_RETRIEVE_FAILED',
                        'Administration',
                        {category: this.categoriesOptions[element]}
                    );

                    this.progressAlertView.logMessage(message);
                    this.progressAlertView.setProgress(calculatedProgress);
                }.bind(this),
                complete: function() {
                    // Check if all the customizations were extracted
                    if (nrProcessedEl === numberElements) {
                        // Enable Customizations tab
                        this.enableTab('customizations');

                        // Sort the extracted customizations
                        this.customizations = Object.keys(this.customizations).sort().reduce(
                            (obj, key) => {
                                obj[key] = this.customizations[key];
                                return obj;
                            },
                            {}
                        );

                        this.progressAlertView.processSuccessful(app.lang.get(
                            'LBL_PACKAGE_BUILDER_COMPLETE',
                            'Administration'
                        ));
                    }
                }.bind(this)
            };

            app.api.call('create', url, data, callback);

        }.bind(this));
    },

    /**
     * Get progress percentage
     * @param {number} first
     * @param {number} second
     * @return {string}
     */
    getPercentage: function(first, second) {
        // eslint-disable-next-line no-magic-numbers
        return Math.round(((first / second) * 100)) + '%';
    },

    /**
     * Enable tab
     * @param {string} tabName
     */
    enableTab: function(tabName) {
        const tabClass = '.' + tabName + '_tab'; // e.g.g .customizations_tab
        const dotClass = '.' + tabName + 'Dot'; // e.g.g .customizationsDot
        const tab = this.$el.parent().parent().find(tabClass); // Get tab element

        tab.removeClass('disabled'); // Enable Tab
        tab.find(dotClass).show(); // Show blue notification dot
        tab.addClass('start_animation');
    },

    /**
     * Load the progress-alert view, custom view to display the fetching progress
     */
    showFetchingAlert: function() {
        let alertContainer = this.$el.find('.progress-alert-container');
        let progressAlertView = app.view.createView({
            name: 'package-builder-progress-alert',
            initialTitle: app.lang.get('LBL_PACKAGE_BUILDER_FETCHING_CUSTOMIZATIONS', 'Administration'),
            successTitle: app.lang.get('LBL_PACKAGE_BUILDER_CUSTOMIZATIONS_FETCHED', 'Administration'),
            errorTitle: app.lang.get('LBL_PACKAGE_BUILDER_CUSTOMIZATIONS_FETCHING_FAILED', 'Administration'),
        });

        progressAlertView.render();

        alertContainer.empty();
        alertContainer.append(progressAlertView.$el);

        // Keep alert view on this
        this.progressAlertView = progressAlertView;
    },
});
