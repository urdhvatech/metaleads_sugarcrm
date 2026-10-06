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
 * @class View.Views.Base.AdministrationPackageBuilderConnnectionSubtabView
 * @alias SUGAR.App.view.views.BaseAdministrationPackageBuilderConnnectionSubtabView
 * @extends View.View
 */
({
    /**
     * Connection info for remote instance.
     */
    connectionInfo: {},

    /**
     * @inheritdoc
     */
    render: function() {
        this._super('render');
        this.initEvents();
    },

    /**
     * Add event listeners.
     */
    initEvents: function() {
        if (_.isUndefined(this.context._events) ||
            _.isEmpty(this.context._events['button:test_connection_button:click'])) {
            this.listenTo(this.context, 'button:test_connection_button:click', this.testConnection.bind(this));
        }
    },

    /**
     * Test connection to remote instance.
     */
    testConnection: function() {
        const data = {
            connectionInfo: this.getConnectionInfo(),
        };

        if (data.connectionInfo === false) {
            app.alert.show('pb_empty_fields', {
                level: 'error',
                messages: app.lang.get('LBL_PACKAGE_BUILDER_TAB_PACKAGES_EMPTY_FIELD_ERROR', 'Administration'),
                autoClose: true,
                autoCloseDelay: 10000,
            });

            return;
        }

        const urlObj = new URL(data.connectionInfo.url);
        if (urlObj.protocol !== 'https:') {
            app.alert.show('pb_connection_unsupported_protocol', {
                level: 'error',
                messages: app.lang.get('LBL_PACKAGE_BUILDER_INVALID_SCHEME', 'Administration'),
                autoClose: true,
                autoCloseDelay: 10000,
            });

            return;
        }

        const url = app.api.buildURL('Administration/package/getRemotePackages');

        const callback = {
            success: function(response) {
                // Connection test successful
                app.alert.show('pb_test_successful', {
                    level: 'success',
                    messages: app.lang.get('LBL_PACKAGE_BUILDER_CONNECTION_SUCCESS', 'Administration'),
                    autoClose: true
                });

                this.enableRemotePackagesTab(); // Enable the Remote Packages tab
                this.connectionInfo = this.getConnectionInfo(); // Save the connection info on this.
                this.context.set('otherInstancePackages', _.values(response.otherInstancePackages || {}));
            }.bind(this),
            error: function(errorData) {
                app.alert.show('pb_test_connection_failed', {
                    level: 'error',
                    messages: app.lang.get('LBL_PACKAGE_BUILDER_CONNECTION_FAILED', 'Administration'),
                    autoClose: true,
                    autoCloseDelay: 10000,
                });
            }.bind(this),
        };

        // Check if the source info are correct
        app.api.call('create', url, data, callback);
    },

    /**
     * Get connection info.
     * @return {(Object|boolean)} Connection info or false if any of the fields are empty.
     */
    getConnectionInfo: function() {
        const url = this.model.get('instance_url');
        const user = this.model.get('username');
        const password = this.model.get('password');

        if (_.isEmpty(url) || _.isEmpty(user) || _.isEmpty(password)) {
            return false;
        }

        return {
            'url': url,
            'user': user,
            'pass': password,
        };
    },

    /**
     * Enable Remote Packages
     */
    enableRemotePackagesTab: function() {
        const packagesTab = this.$el.parent().parent().find('.remote_packages_tab'); // Get tab element

        packagesTab.find('.remote_packagesDot').show(); // Show blue notification dot
        packagesTab.removeClass('disabled'); // Enable Tab
        packagesTab.addClass('start_animation');
    },
});
