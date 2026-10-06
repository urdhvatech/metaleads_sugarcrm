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
 * @class View.Views.Base.Users.CreateView
 * @alias SUGAR.App.view.views.BaseUsersCreateView
 * @extends View.Views.Base.CreateView
 */
({
    extendsFrom: 'CreateView',

    /**
     * @inheritdoc
     *
     * Sets correct metadata if the user being created is a special type
     */
    initialize: function(options) {
        let userType = options.context.get('userType');
        if (['group', 'portalapi'].includes(userType)) {
            options.meta = _.extend({}, app.metadata.getView(options.module, `record-${userType}`), options.meta);
            this.showExtraMeta = false;
        }

        this._super('initialize', [options]);
        this._setConfigBasedDefaults();
    },

    /**
     * Sets default values for fields whose defaults are based on system configuration
     *
     * @private
     */
    _setConfigBasedDefaults: function() {
        let defaults = {};
        let configDefaultFields = _.filter(app.metadata.getModule('Users', 'fields'), (field) => {
            return _.has(field, 'defaultFromConfig');
        });

        _.each(configDefaultFields, function(configDefaultField) {
            if (_.has(app.config, configDefaultField.defaultFromConfig)) {
                defaults[configDefaultField.name] = app.config[configDefaultField.defaultFromConfig];
            }
        }, this);

        if (!_.isEmpty(defaults)) {
            this.model.setDefault(defaults);
        }
    },

    /**
     * @inheritdoc
     */
    _getNoAccessErrorMessage: function(error) {
        if (error.code === 'license_seats_needed' && _.isString(error.message)) {
            return error.message;
        }
        return this._super('_getNoAccessErrorMessage', [error]);
    },

    /**
     * @inheritdoc
     *
     * Bypasses IDM mode restrictions for creating group and portal type users
     */
    getCustomSaveOptions: function(options) {
        if (app.config.idmModeEnabled && ['group', 'portalapi'].includes(this.context.get('userType'))) {
            options = options || {};
            options.params = options.params || {};
            options.params.skip_idm_mode_restrictions = true;
        }

        return options;
    },
});
