/**
 * @class View.Views.Base.UtSmLicenseView
 * @alias SUGAR.App.view.views.BaseUtSmLicenseView
 * @extends View.Views.Base.View
 */
({
    /**
     * @inheritdoc
     */
    initialize: function(options) {
        this._super('initialize', [options]);
        this.license = null;
        this.validationMessage = '';
        this.validationFailed = false;
        this.validated = false;
        this.loadLicense();
    },

    /**
     * Load the current license key and screen labels.
     */
    loadLicense: function() {
        var url = app.api.buildURL('ut_sm', 'license');
        app.alert.show('ut-sm-license-load', {
            level: 'process',
            title: app.lang.get('LBL_LOADING')
        });
        app.api.call('read', url, null, {
            success: _.bind(function(data) {
                app.alert.dismiss('ut-sm-license-load');
                this.license = data || {};
                this.validated = !!(data && data.is_valid);
                this.render();
            }, this),
            error: _.bind(function(error) {
                app.alert.dismiss('ut-sm-license-load');
                app.alert.show('ut-sm-license-error', {
                    level: 'error',
                    messages: this._errorMessage(error),
                    autoClose: false
                });
            }, this)
        });
    },

    events: {
        'click [data-action=validate]': 'validateLicense',
        'click [data-action=continue]': 'continueToSettings',
        'click [data-action=cancel]': 'cancel',
        'click [data-action=increase]': 'increaseUsers',
        'click [data-action=add-users]': 'moveUsers',
        'click [data-action=remove-users]': 'moveUsers',
        'click [data-action=save-users]': 'saveUsers'
    },

    /**
     * Validate the entered license key.
     *
     * @param {Event} evt
     */
    validateLicense: function(evt) {
        if (evt) {
            evt.preventDefault();
        }
        var key = $.trim(this.$('[name=license_key]').val() || '');
        if (!key) {
            return;
        }
        this._call('create', 'license/validate', {key: key}, _.bind(function(data) {
            this.license = _.extend(this.license || {}, data || {});
            this.validated = !!(data && data.validated);
            this.validationFailed = false;
            this.validationMessage = (data && data.message) || '';
            this.needsMoreUsers = !!(this.license.validate_users && data && data.validated && !data.validated_users);
            this.usersPassed = !!(this.license.validate_users && data && data.validated_users);
            if (data && data.licensed_user_count !== undefined && data.licensed_user_count !== '') {
                this.license.licensed_user_count = data.licensed_user_count;
            }
            this.render();
        }, this));
    },

    /**
     * Open Meta Leads settings after a valid license.
     */
    continueToSettings: function() {
        var route = (this.license && this.license.continue_route) || 'ut_sm/settings';
        app.router.navigate(route, {trigger: true});
    },

    /**
     * Return to Administration.
     */
    cancel: function() {
        app.router.navigate('Administration', {trigger: true});
    },

    /**
     * Increase the purchased user count to the current active user count.
     */
    increaseUsers: function() {
        this._call('create', 'license/change', {
            key: this.$('[name=license_key]').val(),
            user_count: this.license ? this.license.current_users : 0
        }, _.bind(function(data) {
            this.license = _.extend(this.license || {}, data || {});
            this.usersPassed = true;
            this.needsMoreUsers = false;
            this.validated = true;
            this.render();
        }, this));
    },

    /**
     * Move selected users between the unlicensed and licensed lists.
     *
     * @param {Event} evt
     */
    moveUsers: function(evt) {
        var toLicensed = this.$(evt.currentTarget).data('action') === 'add-users';
        var source = toLicensed ? this.$('[name=unlicensed_users]') : this.$('[name=licensed_users]');
        var target = toLicensed ? this.$('[name=licensed_users]') : this.$('[name=unlicensed_users]');
        source.find('option:selected').each(function() {
            target.append($(this));
        });
    },

    /**
     * Save the licensed user list.
     */
    saveUsers: function() {
        var ids = [];
        this.$('[name=licensed_users] option').each(function() {
            ids.push($(this).val());
        });
        this._call('create', 'license/users', {licensed_users: ids}, _.bind(function(data) {
            app.alert.show('ut-sm-users-saved', {
                level: 'success',
                messages: (data && data.message) || 'Users saved successfully.',
                autoClose: true
            });
            this.validated = true;
            this.usersPassed = true;
            this.render();
        }, this));
    },

    /**
     * @param {string} method
     * @param {string} action
     * @param {Object} payload
     * @param {Function} success
     */
    _call: function(method, action, payload, success) {
        var url = app.api.buildURL('ut_sm', action);
        app.alert.show('ut-sm-license-work', {
            level: 'process',
            title: app.lang.get('LBL_LOADING')
        });
        app.api.call(method, url, payload, {
            success: _.bind(function(data) {
                app.alert.dismiss('ut-sm-license-work');
                success(data);
            }, this),
            error: _.bind(function(error) {
                app.alert.dismiss('ut-sm-license-work');
                this.validationFailed = true;
                this.validationMessage = this._errorMessage(error);
                this.validated = false;
                this.render();
                app.alert.show('ut-sm-license-fail', {
                    level: 'error',
                    messages: this.validationMessage,
                    autoClose: false
                });
            }, this)
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
