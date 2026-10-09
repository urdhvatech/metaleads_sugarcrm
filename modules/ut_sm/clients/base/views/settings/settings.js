/**
 * @class View.Views.Base.UtSmSettingsView
 * @alias SUGAR.App.view.views.BaseUtSmSettingsView
 * @extends View.Views.Base.View
 */
({
    /**
     * @inheritdoc
     */
    initialize: function(options) {
        this._super('initialize', [options]);
        this.settings = null;
        this.loadSettings();
    },

    /**
     * @inheritdoc
     */
    _render: function() {
        this._super('_render');
        this._consumeFlash();
    },

    /**
     * Load OAuth settings, connection status, and connected accounts.
     */
    loadSettings: function() {
        var url = app.api.buildURL('ut_sm', 'settings');
        app.alert.show('ut-sm-settings-load', {
            level: 'process',
            title: app.lang.get('LBL_LOADING')
        });
        app.api.call('read', url, null, {
            success: _.bind(function(data) {
                app.alert.dismiss('ut-sm-settings-load');
                this.settings = data || {};
                this.hasConnectedAccounts = !_.isEmpty(this.settings.connected_accounts);
                this.hasReconciliationRun = !!(this.settings.reconciliation && this.settings.reconciliation.last_run);
                this.hasReconciliationError = !!(this.settings.reconciliation && this.settings.reconciliation.last_error);
                this.render();
            }, this),
            error: _.bind(function(error) {
                app.alert.dismiss('ut-sm-settings-load');
                if (this._isLicenseError(error)) {
                    app.router.navigate('bwc/index.php?module=ut_sm&action=license', {trigger: true, replace: true});
                    return;
                }
                this._showError(error);
            }, this)
        });
    },

    /**
     * Show a one-time message passed back from the OAuth redirect.
     */
    _consumeFlash: function() {
        var hash = window.location.hash || '';
        var queryIndex = hash.indexOf('?');
        if (queryIndex === -1) {
            return;
        }
        var params = {};
        _.each(hash.substring(queryIndex + 1).split('&'), function(part) {
            var bits = part.split('=');
            if (bits[0]) {
                params[decodeURIComponent(bits[0])] = decodeURIComponent((bits[1] || '').replace(/\+/g, ' '));
            }
        });
        if (params.oauth_error) {
            app.alert.show('ut-sm-flash', {
                level: 'error',
                messages: params.oauth_error,
                autoClose: false
            });
        } else if (params.oauth_success) {
            app.alert.show('ut-sm-flash', {
                level: 'success',
                messages: params.oauth_success,
                autoClose: true
            });
        }
        app.router.navigate('ut_sm/settings', {trigger: false, replace: true});
    },

    events: {
        'click [data-action=save]': 'saveSettings',
        'click [data-action=cancel]': 'cancel',
        'click [data-action=authorize]': 'authorize',
        'click [data-action=refresh-tokens]': 'refreshTokens',
        'click [data-action=disconnect]': 'disconnect',
        'click [data-action=reconcile]': 'reconcile',
        'click [data-action=configure]': 'configureAccount'
    },

    /**
     * Persist app credentials and webhook settings.
     *
     * @param {Event} evt
     */
    saveSettings: function(evt) {
        if (evt) {
            evt.preventDefault();
        }
        var payload = {
            app_id: this.$('[name=app_id]').val(),
            app_secret: this.$('[name=app_secret]').val(),
            oauth_redirect_uri: this.$('[name=oauth_redirect_uri]').val(),
            callback_url: this.$('[name=callback_url]').val(),
            verify_token: this.$('[name=verify_token]').val(),
            reconciliation_hours: this.$('[name=reconciliation_hours]').val()
        };
        this._licensedPost('ut_sm/settings', payload);
    },

    /**
     * Return to Administration.
     */
    cancel: function() {
        app.router.navigate('Administration', {trigger: true});
    },

    /**
     * Start Meta authorization in the browser session so the OAuth state
     * is available when Facebook redirects back to the entry point.
     */
    authorize: function() {
        if (!this.settings || !this.settings.oauth_url) {
            app.alert.show('ut-sm-oauth-url', {
                level: 'error',
                messages: app.lang.get('LBL_UT_SM_OAUTH_URL_FAILED', this.module)
            });
            return;
        }
        window.location.href = this.settings.oauth_url;
    },

    /**
     * Refresh the stored user token and page subscriptions.
     */
    refreshTokens: function() {
        this._licensedPost('ut_sm/refreshTokens');
    },

    /**
     * Confirm, then disconnect the Meta app.
     */
    disconnect: function() {
        app.alert.show('ut-sm-disconnect-confirm', {
            level: 'confirmation',
            messages: app.lang.get('LBL_UT_SM_DISCONNECT_CONFIRM', this.module),
            onConfirm: _.bind(function() {
                this._post('ut_sm/disconnect', {});
            }, this)
        });
    },

    /**
     * Run reconciliation immediately.
     */
    reconcile: function() {
        this._licensedPost('ut_sm/reconcile');
    },

    /**
     * Open the per-account lead settings screen.
     *
     * @param {Event} evt
     */
    configureAccount: function(evt) {
        var id = this.$(evt.currentTarget).data('id');
        if (!id) {
            return;
        }
        var url = app.api.buildURL('ut_sm', 'account/' + encodeURIComponent(id));
        app.alert.show('ut-sm-account-open', {
            level: 'process',
            title: app.lang.get('LBL_LOADING')
        });
        app.api.call('read', url, null, {
            success: _.bind(function() {
                app.alert.dismiss('ut-sm-account-open');
                app.router.navigate('ut_sm/account/' + id, {trigger: true});
            }, this),
            error: _.bind(function(error) {
                app.alert.dismiss('ut-sm-account-open');
                if (this._isLicenseError(error)) {
                    this._showLicenseAlert();
                    return;
                }
                this._showError(error);
            }, this)
        });
    },

    /**
     * Run a settings action. A missing license shows the alert and leaves this page unchanged.
     *
     * @param {string} path
     * @param {Object} [payload]
     */
    _licensedPost: function(path, payload) {
        if (this._actionInProgress) {
            return;
        }
        this._actionInProgress = true;
        var parts = path.split('/');
        var url = app.api.buildURL(parts.shift(), parts.join('/'));
        app.alert.show('ut-sm-settings-working', {
            level: 'process',
            title: app.lang.get('LBL_LOADING')
        });
        app.api.call('create', url, payload || {}, {
            success: _.bind(function(data) {
                this._actionInProgress = false;
                app.alert.dismiss('ut-sm-settings-working');
                this.settings = data || this.settings;
                this.hasConnectedAccounts = !_.isEmpty(this.settings.connected_accounts);
                this.hasReconciliationRun = !!(this.settings.reconciliation && this.settings.reconciliation.last_run);
                this.hasReconciliationError = !!(this.settings.reconciliation && this.settings.reconciliation.last_error);
                this.render();
                if (data && data.message) {
                    app.alert.show('ut-sm-settings-saved', {
                        level: 'success',
                        messages: data.message,
                        autoClose: true
                    });
                }
            }, this),
            error: _.bind(function(error) {
                this._actionInProgress = false;
                app.alert.dismiss('ut-sm-settings-working');
                if (this._isLicenseError(error)) {
                    this._showLicenseAlert();
                    return;
                }
                this._showError(error);
            }, this)
        });
    },

    /**
     * @param {string} path
     * @param {Object} payload
     * @param {string} [method]
     */
    _post: function(path, payload, method) {
        var parts = path.split('/');
        var url = app.api.buildURL(parts.shift(), parts.join('/'));
        app.alert.show('ut-sm-settings-save', {
            level: 'process',
            title: app.lang.get('LBL_LOADING')
        });
        app.api.call(method || 'create', url, payload, {
            success: _.bind(function(data) {
                app.alert.dismiss('ut-sm-settings-save');
                this.settings = data || this.settings;
                this.hasConnectedAccounts = !_.isEmpty(this.settings.connected_accounts);
                this.hasReconciliationRun = !!(this.settings.reconciliation && this.settings.reconciliation.last_run);
                this.hasReconciliationError = !!(this.settings.reconciliation && this.settings.reconciliation.last_error);
                this.render();
                if (data && data.message) {
                    app.alert.show('ut-sm-settings-saved', {
                        level: 'success',
                        messages: data.message,
                        autoClose: true
                    });
                }
            }, this),
            error: _.bind(function(error) {
                app.alert.dismiss('ut-sm-settings-save');
                if (this._isLicenseError(error)) {
                    app.router.navigate('bwc/index.php?module=ut_sm&action=license', {trigger: true});
                    return;
                }
                this._showError(error);
            }, this)
        });
    },

    /**
     * @param {Object} error
     * @return {boolean}
     */
    _isLicenseError: function(error) {
        var details = [
            this._errorMessage(error),
            error && error.code,
            error && error.responseText,
            error && error.payload && error.payload.error_message
        ];
        return details.join(' ').indexOf('LICENSE_REQUIRED') !== -1;
    },

    /**
     * Red Sugar alert when a settings action is blocked by the license check.
     */
    _showLicenseAlert: function() {
        var message = app.lang.get('LBL_UT_SM_LICENSE_NOT_CONFIGURED', this.module);
        if (!message || message === 'LBL_UT_SM_LICENSE_NOT_CONFIGURED') {
            message = 'License is not configured';
        }
        app.alert.show('ut-sm-license-required', {
            level: 'error',
            messages: message,
            autoClose: false
        });
    },

    /**
     * @param {Object} error
     */
    _showError: function(error) {
        app.alert.show('ut-sm-settings-error', {
            level: 'error',
            messages: this._errorMessage(error),
            autoClose: false
        });
    },

    /**
     * @param {Object|string} error
     * @return {string}
     */
    _errorMessage: function(error) {
        if (!error) {
            return 'Request failed.';
        }
        if (_.isString(error)) {
            return error;
        }
        if (error.message) {
            return error.message;
        }
        if (error.error_message) {
            return error.error_message;
        }
        if (error.payload && error.payload.error_message) {
            return error.payload.error_message;
        }
        var xhr = error.xhr || error;
        if (xhr.responseText) {
            try {
                var body = JSON.parse(xhr.responseText);
                return body.error_message || body.error || 'Request failed.';
            } catch (e) {
                return 'Request failed.';
            }
        }
        return 'Request failed.';
    }
})
