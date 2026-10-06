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
 * @class View.Views.Base.AdministrationPackageBuilderRemotePackagesSubtabView
 * @alias SUGAR.App.view.views.BaseAdministrationPackageBuilderRemotePackagesSubtabView
 * @extends View.View
 */
({
    /**
     * Progress alert view
     */
    progressAlertView: false,

    /**
     * Process steps
     */
    processSteps: false,

    /**
     * Process completed steps
     */
    processCompletedSteps: false,

    /**
     * Show disabled row/button or not
     */
    showDisabled: true,

    /**
     * Connection info for remote instance
     */
    connectionInfo: {},

    /**
     * Packages on local instance
     */
    localInstancePackages: {},

    /**
     * Entries to display in the table
     */
    entries: [],

    /**
     * Headers to display in the table
     */
    headers: [
        'Name',
        'Version',
        'Description',
        'Actions'
    ],

    /**
     * Header labels
     */
    headerLabels: [
        'LBL_PACKAGE_BUILDER_NAME',
        'LBL_PACKAGE_BUILDER_VERSION',
        'LBL_PACKAGE_BUILDER_DESCRIPTION',
        'LBL_PACKAGE_BUILDER_ACTIONS'
    ],

    /**
     * @inheritdoc
     */
    initialize: function(options) {
        this._super('initialize', [options]);
        this.connectionInfo = options.connectionInfo;
        this.entries = this.context.get('otherInstancePackages') || [];
        this.localInstancePackages = this.context.get('installedPackages') || {};
    },

    /**
     * @inheritdoc
     */
    render: function() {
        this._super('render');
        this.setActionButtons();
        this.initEvents();
    },

    /**
     * Add event listeners
     */
    initEvents: function() {
        let self = this;

        // click event for each Pull Package button
        this.$el.find('.pullButton').click(this.pullPackage.bind(self));

        // click event for each Update Package button
        this.$el.find('.updateButton').click(this.updatePackagePressed.bind(self));

        // showDisabled button/icon
        this.$el.find('#showDisabled').click(this.disabledVisibilityChanged.bind(self));
    },

    /**
     * Event handler for the showDisabled button
     */
    disabledVisibilityChanged: function() {
        // Update showDisbaled flag
        this.showDisabled = this.showDisabled === false;
        this.render(); // Recreate the view with the new stats
    },

    /**
     * After all the remote packages are displayed in the table,
     * this function will edit each package action, based
     * on the local instance packages
     */
    setActionButtons: function() {
        let packageRows = this.$el.find('.regularRow');
        _.each(packageRows, function(row, key) {
            // Get entry based on key index
            let entry = this.entries[key];
            let packageName = entry.name;
            let localInstancePackage = _.find(this.localInstancePackages, function(localPackage) {
                return (localPackage.name === packageName && localPackage.status === 'installed');
            });

            // If package is not installed on local instance, do nothing
            if (_.isEmpty(localInstancePackage)) {
                return;
            }

            // If we find a matching package, save it on entry
            this.entries[key].localInstanceVersion = localInstancePackage;

            let rowId = '#' + row.id;

            // If this package exisits on local instance with the same version
            if (entry.version === localInstancePackage.version) {
                // Disable pull button from this row
                this.disablePullButton(rowId);
            } else {
                // If package exisits on local instance with a diffrent version
                this.$el.find(rowId + ' .actionsSpan').addClass('hide');// Hide pull button
                this.$el.find(rowId + ' .updateButton').removeClass('hide'); // Show update button
            }
        }.bind(this));
    },

    /**
     * Disable pull button from this row
     *
     * @param {string} rowId
     */
    disablePullButton: function(rowId) {
        // Disable pull button from this row
        this.$el.find(rowId + ' .pullButton').addClass('disabled');
        this.$el.find(rowId + ' .actionsSpan').removeClass('hide');
        this.$el.find(rowId + ' .actionsSpan').attr(
            'data-bs-original-title',
            app.lang.get('LBL_PACKAGE_BUILDER_PULL_PACKAGE_TOOLTIP_DISABLED', 'Administration') // tooltip message
        );

        // If user chosee to hide disbaled rows, hide the row as well
        if (this.showDisabled === false) {
            this.$el.find(rowId + ' .pullButton')[0]
                .parentElement.parentElement.parentElement.parentElement.classList.add('hide');
        }
    },

    /**
     * Pull package from the remote instance
     *
     * @param {Object} $el
     */
    pullPackage: function($el) {
        // Get selected entry (button id stores the index of the entry)
        let rowIndex = $el.currentTarget.id;
        let selectedEntry = this.entries[rowIndex];
        selectedEntry.rowIndex = rowIndex;

        // Show confirmation alert
        App.alert.show('confirm-pull-package', {
            level: 'confirmation',
            messages: app.lang.get('LBL_PACKAGE_BUILDER_CONFIRM_PULL_PACKAGE', 'Administration'),
            autoClose: false,
            onConfirm: () => this.uploadAndInstallPackage(selectedEntry),
        });
    },

    /**
     * Upload and install the package
     *
     * @param {Object} package
     */
    uploadAndInstallPackage: function(packageInfo) {
        let url = app.api.buildURL('Administration/package/remote');
        let stagedVersions = this.getStagedVersions(packageInfo.name);
        let data = {
            'connectionInfo': this.connectionInfo,
            'id': packageInfo.id,
            'stagedVersions': stagedVersions,
        };

        let uploadCallBack = {
            success: function(uploadResponse) {
                if (uploadResponse.access === false) {
                    // Access denied
                    this.closeProgressAlert(
                        'error',
                        app.lang.get(
                            'LBL_PACKAGE_BUILDER_ACCESS_DENIED_WRONG_CONNECTION',
                            'Administration'
                        )
                    );
                    return;
                }

                // Check if we got the file installation id
                if (_.isEmpty(uploadResponse.fileInstallId)) {
                    // Error we didn't get the id of the uploaded package
                    this.closeProgressAlert(
                        'error',
                        app.lang.get(
                            'LBL_PACKAGE_BUILDER_WRONG_UPLOAD',
                            'Administration'
                        )
                    );
                    return;
                }

                this.processCompletedSteps++;

                let calculatedProgress = this.getPercentage(this.processCompletedSteps, this.processSteps);

                this.progressAlertView.logMessage(app.lang.get('LBL_PACKAGE_BUILDER_UPLOAD_SUCCESS', 'Administration'));
                this.progressAlertView.setProgress(calculatedProgress);

                let rowId = '#row-' + packageInfo.rowIndex;
                this.disablePullButton(rowId); // Disable pull button

                // Remove staged packages from the localInstancePackages list
                _.each(stagedVersions, function(stagedPackageId) {
                    delete this.localInstancePackages[stagedPackageId];
                }.bind(this));

                // Add the new package to localInstancePackages
                packageInfo.status = 'staged';
                this.localInstancePackages[uploadResponse.fileInstallId] = packageInfo;
                // Init installation package
                this.installPackage(uploadResponse.fileInstallId, packageInfo);
            }.bind(this),
            error: function(errorData, response) {
                if (response.status === 404) {
                    // Error we didn't get the id of the uploaded package
                    this.closeProgressAlert(
                        'error',
                        app.lang.get(
                            'LBL_PACKAGE_BUILDER_WRONG_UPLOAD',
                            'Administration'
                        )
                    );
                } else {
                    this.closeProgressAlert('error', errorData);
                }
            }.bind(this),
        };

        // If this function is called after the uninstallation, process alert is already created
        if (_.isEmpty(this.progressAlertView)) {
            // There will be 2 stepts, updating and installing package
            this.processSteps = 2;
            this.processCompletedSteps = 0;
            // If the alert view is undefined, create it
            this.showPackagesProgressAlert({
                'initialTitle': app.lang.get('LBL_PACKAGE_BUILDER_PULLING_PACKAGE', 'Administration'),
                'successTitle': app.lang.get('LBL_PACKAGE_BUILDER_PULL_SUCCESS', 'Administration'),
                'errorTitle': app.lang.get('LBL_PACKAGE_BUILDER_PULL_FAILED', 'Administration'),
            });
        }

        let uploadingMessage = app.lang.get('LBL_PACKAGE_BUILDER_UPLOADING', 'Administration') +
            ' ' + packageInfo.name + ' ' + packageInfo.version + ' ...';

        this.progressAlertView.logMessage(uploadingMessage);

        app.api.call('create', url, data, uploadCallBack, {skipMetadataHash: true});
    },

    /**
     * Install the package
     *
     * @param {string} fileInstallId
     * @param {Object} packageInfo
     */
    installPackage: function(fileInstallId, packageInfo) {
        let url = app.api.buildURL('Administration/packages/' + fileInstallId + '/install');

        let installCallBack = {
            success:
                function(installResponse) {
                    if (_.isUndefined(installResponse.id)) {
                        this.closeProgressAlert(
                            'error',
                            app.lang.get(
                                'LBL_PACKAGE_BUILDER_INSTALL_FAILED',
                                'Administration'
                            )
                        );
                        return;
                    }
                    // Update the localInstancePackages, to display the correct actions for the package
                    this.localInstancePackages[installResponse.id] = installResponse;
                    // End progress alert
                    this.closeProgressAlert(
                        'success',
                        app.lang.get(
                            'LBL_PACKAGE_BUILDER_PACKAGE_INSTALLED',
                            'Administration'
                        )
                    );
                    this.updateContextPackages();
                }.bind(this),
            error:
                function(errorData) {
                    this.closeProgressAlert('error', errorData);
                }.bind(this),
        };

        let installMessage = app.lang.get('Installing', 'Addministration') +
            ' ' + packageInfo.name + ' ' + packageInfo.version + ' ...';

        this.progressAlertView.logMessage(installMessage);

        app.api.call('read', url, null, installCallBack, {skipMetadataHash: true});
    },

    /**
     * Get all the staged versions of a package
     * @param {string} localPackageName
     * @return {Array}
     */
    getStagedVersions: function(localPackageName) {
        // in other instance, get all the packages that have this name
        let stagedPackages = [];

        _.each(this.localInstancePackages, function(installedPackage) {
            if (installedPackage.status === 'staged' && installedPackage.name === localPackageName) {
                stagedPackages.push(installedPackage.id);
            }
        });

        return stagedPackages;
    },

    /**
     * Update package pressed
     *
     * @param {Object} $el
     */
    updatePackagePressed: function($el) {
        let rowIndex = $el.currentTarget.id;
        let remotePackage = this.entries[rowIndex]; // Get entry by index (index is stored in id)
        let localInstancePackage = remotePackage.localInstanceVersion;
        remotePackage.rowIndex = rowIndex; // Save row index, this will be used to update the action buttons

        // Show confirmation alert
        App.alert.show('confirm-update-package', {
            level: 'confirmation',
            messages: app.lang.get('LBL_PACKAGE_BUILDER_CONFIRM_PULL_PACKAGE', 'Administration'),
            autoClose: false,
            onConfirm: function() {
                // There will be 3 steps, uninstall old package, update and install new package
                this.processSteps = 3;
                this.processCompletedSteps = 0;
                // Create the progress alert first
                this.showPackagesProgressAlert({
                    'initialTitle': app.lang.get('LBL_PACKAGE_BUILDER_UPDATING_PACKAGE', 'Administration'),
                    'successTitle': app.lang.get('LBL_PACKAGE_BUILDER_UPDATE_SUCCESS', 'Administration'),
                    'errorTitle': app.lang.get('LBL_PACKAGE_BUILDER_UPDATE_FAILED', 'Administration'),
                });
                // Call the updating logic
                this.updatePackage(remotePackage, localInstancePackage);
            }.bind(this),
        });
    },

    /**
     * Update package
     *
     * @param {Object} remotePackage
     * @param {Object} localInstancePackage
     */
    updatePackage: function(remotePackage, localInstancePackage) {
        let url = app.api.buildURL('Administration/packages/' + localInstancePackage.id + '/uninstall');

        let uninstallCallBack = {
            success:
                function(uninstallResponse) {
                    if (_.isUndefined(uninstallResponse.id)) {
                        this.closeProgressAlert(
                            'error',
                            app.lang.get(
                                'LBL_PACKAGE_BUILDER_WRONG_UNINSTALL',
                                'Administration'
                            )
                        );
                        return;
                    }

                    this.processCompletedSteps++;

                    let calculatedProgress = this.getPercentage(this.processCompletedSteps, this.processSteps);

                    this.progressAlertView.logMessage(app.lang.get('LBL_PACKAGE_BUILDER_UNINSTALL_SUCCESS',
                        'Administration'));
                    this.progressAlertView.setProgress(calculatedProgress);

                    // Remove uninstalled package from the localInstancePackages list
                    delete this.localInstancePackages[remotePackage.localInstanceVersion.id];
                    this.localInstancePackages[uninstallResponse.id] = uninstallResponse;

                    // Hide update button, show pull button
                    let rowId = '#row-' + remotePackage.rowIndex;

                    this.$el.find(rowId + ' .updateButton').hide(); // Hide update button
                    this.$el.find(rowId + ' .pullButton').removeClass('disabled'); // Show pull button
                    this.$el.find(rowId + ' .actionsSpan').removeClass('hide');
                    this.$el.find(rowId + ' .actionsSpan').attr(
                        'data-bs-original-title',
                        app.lang.get('LBL_PACKAGE_BUILDER_PULL_PACKAGE_TOOLTIP', 'Administration')
                    );

                    // package uninstalled sucessfully on the other instance begin installing selected package
                    this.uploadAndInstallPackage(remotePackage);
                }.bind(this),
            error:
                function(errorData) {
                    this.closeProgressAlert('error', errorData);
                }.bind(this),
        };

        let uninstallingMessage = app.lang.get('LBL_PACKAGE_BUILDER_UNINSTALLING', 'Administration') +
            ' ' + localInstancePackage.name + ' ' + localInstancePackage.version + ' ...';

        this.progressAlertView.logMessage(uninstallingMessage);

        app.api.call('read', url, null, uninstallCallBack);
    },

    /**
     * Load the progress-alert view, custom view to display the fetching progress
     * @param {Object}
     */
    showPackagesProgressAlert: function(options) {
        let alertContainer = this.$el.find('.progress-alert-container');
        let progressAlertView = app.view.createView({
            name: 'package-builder-progress-alert',
            initialTitle: options.initialTitle,
            successTitle: options.successTitle,
            errorTitle: options.errorTitle,
        });

        progressAlertView.render();

        alertContainer.empty();
        alertContainer.append(progressAlertView.$el);

        // Keep alert view on this
        this.progressAlertView = progressAlertView;
    },

    /**
     * Get the percentage of the process
     *
     * @param {Integer} first
     * @param {Integer} second
     * @return {string}
     */
    getPercentage: function(first, second) {
        // eslint-disable-next-line no-magic-numbers
        return Math.round(((first / second) * 100)) + '%';
    },

    /**
     * Close the progress alert
     *
     * @param {string} status
     * @param {string} message
     */
    closeProgressAlert: function(status, message) {
        if (status === 'success') {
            this.progressAlertView.processSuccessful(message);
        } else if (status === 'error') {
            this.progressAlertView.processFailed(message);
        }

        this.progressAlertView = false;
    },

    /**
     * Set packages in context
     */
    updateContextPackages: function() {
        this.context.set('installedPackages', this.localInstancePackages);
        this.context.set('otherInstancePackages', this.entries);
    }
});
