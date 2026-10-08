/**
 * @class View.Views.Base.UtSmAccountSettingsView
 * @alias SUGAR.App.view.views.BaseUtSmAccountSettingsView
 * @extends View.Views.Base.View
 */
({
    /**
     * @inheritdoc
     */
    initialize: function(options) {
        this._super('initialize', [options]);
        this.account = null;
        this.accountId = this.context.get('accountId');
        this.showRoundRobin = false;
        this.showSpecificUser = false;
        this.showSecurityGroup = false;
        if (!this.accountId) {
            app.router.navigate('ut_sm/settings', {trigger: true, replace: true});
            return;
        }
        this.loadAccount();
    },

    /**
     * Load assignment, forms, and field mapping for the connected account.
     */
    loadAccount: function() {
        var url = app.api.buildURL('ut_sm', 'account/' + encodeURIComponent(this.accountId));
        app.alert.show('ut-sm-account-load', {
            level: 'process',
            title: app.lang.get('LBL_LOADING')
        });
        app.api.call('read', url, null, {
            success: _.bind(function(data) {
                app.alert.dismiss('ut-sm-account-load');
                this.account = data || {};
                this._setAccountFlags();
                this.render();
            }, this),
            error: _.bind(function(error) {
                app.alert.dismiss('ut-sm-account-load');
                if (this._errorMessage(error).indexOf('LICENSE_REQUIRED') !== -1) {
                    app.router.navigate('ut_sm/license', {trigger: true, replace: true});
                    return;
                }
                this._showError(error);
            }, this)
        });
    },

    events: {
        'click [data-action=save]': 'saveAccount',
        'click [data-action=cancel]': 'cancel',
        'click [data-action=refresh-forms]': 'refreshForms',
        'change [name=assignment_type]': 'onAssignmentChange'
    },

    /**
     * Show the assignment inputs that match the selected type.
     */
    onAssignmentChange: function() {
        this._setAssignmentFlags(this.$('[name=assignment_type]').val());
        this.$('[data-panel]').hide();
        this.$('[data-panel="' + this.$('[name=assignment_type]').val() + '"]').show();
    },

    /**
     * @param {string} type
     */
    _setAssignmentFlags: function(type) {
        this.showRoundRobin = type === 'round_robin';
        this.showSpecificUser = type === 'specific_user';
        this.showSecurityGroup = type === 'security_group';
    },

    /**
     * Template flags derived from the loaded account payload.
     */
    _setAccountFlags: function() {
        var account = this.account || {};
        this._setAssignmentFlags(account.assignment_type);
        this.hasUsers = !_.isEmpty(account.users);
        this.hasSecurityGroups = !_.isEmpty(account.security_groups);
        this.hasForms = !_.isEmpty(account.forms);
        this.hasFormMappings = !_.isEmpty(account.forms_with_mappings);
    },

    /**
     * Save assignment, enabled forms, and field mapping.
     *
     * @param {Event} evt
     */
    saveAccount: function(evt) {
        if (evt) {
            evt.preventDefault();
        }
        var rrUserIds = [];
        this.$('[name=rr_user_ids]:checked').each(function() {
            rrUserIds.push($(this).val());
        });
        var enabledFormIds = [];
        this.$('[name=enabled_form_ids]:checked').each(function() {
            enabledFormIds.push($(this).val());
        });
        var fieldMap = {};
        this.$('select[data-meta-key]').each(function() {
            var $el = $(this);
            var formId = $el.attr('data-form-id');
            var metaKey = $el.attr('data-meta-key');
            if (!fieldMap[formId]) {
                fieldMap[formId] = {};
            }
            fieldMap[formId][metaKey] = $el.val();
        });

        this._send('update', {
            assignment_type: this.$('[name=assignment_type]').val(),
            assignment_user_id: this.$('[name=assignment_user_id]').val(),
            assignment_group_id: this.$('[name=assignment_group_id]').val(),
            rr_user_ids: rrUserIds,
            enabled_form_ids: enabledFormIds,
            field_map: fieldMap
        });
    },

    /**
     * Reload Meta lead forms for this account.
     */
    refreshForms: function() {
        var url = app.api.buildURL('ut_sm', 'account/' + encodeURIComponent(this.accountId) + '/refreshForms');
        this._request('create', url);
    },

    /**
     * Return to the main settings screen.
     */
    cancel: function() {
        app.router.navigate('ut_sm/settings', {trigger: true});
    },

    /**
     * @param {string} method
     * @param {Object} payload
     */
    _send: function(method, payload) {
        var url = app.api.buildURL('ut_sm', 'account/' + encodeURIComponent(this.accountId));
        this._request(method, url, payload);
    },

    /**
     * @param {string} method
     * @param {string} url
     * @param {Object} [payload]
     */
    _request: function(method, url, payload) {
        app.alert.show('ut-sm-account-save', {
            level: 'process',
            title: app.lang.get('LBL_LOADING')
        });
        app.api.call(method, url, payload || {}, {
            success: _.bind(function(data) {
                app.alert.dismiss('ut-sm-account-save');
                this.account = data || this.account;
                this._setAccountFlags();
                this.render();
                if (data && data.message) {
                    app.alert.show('ut-sm-account-saved', {
                        level: 'success',
                        messages: data.message,
                        autoClose: true
                    });
                }
            }, this),
            error: _.bind(function(error) {
                app.alert.dismiss('ut-sm-account-save');
                if (this._errorMessage(error).indexOf('LICENSE_REQUIRED') !== -1) {
                    app.router.navigate('ut_sm/license', {trigger: true});
                    return;
                }
                this._showError(error);
            }, this)
        });
    },

    /**
     * @param {Object} error
     */
    _showError: function(error) {
        app.alert.show('ut-sm-account-error', {
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
