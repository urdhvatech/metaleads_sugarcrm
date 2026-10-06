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
 * @class View.Views.Base.AdministrationHistoricallyDeltaConfigView
 * @alias SUGAR.App.view.views.BaseAdministrationHistoricallyDeltaConfigView
 * @extends View.Views.Base.AdministrationConfigView
 */
({
    extendsFrom: 'AdministrationConfigView',

    /**
     * Key to retrieve the data from settings
     * See ConfigApiHandler->setConfig for more details about the formatting
     */
    prefix: 'delta_',

    /**
     * Key to retrieve the data from settings
     */
    dataKey: 'delta_modules_data',

    /**
     * Original settings
     */
    originalSettings: {},

    /**
     * Initial fields settings
     */
    initialFieldsSettings: {},

    /**
     * Event listeners
     */
    events: {
        'change input[type=checkbox]': 'changeHandler',
    },

    /**.
     * @inheritdoc
     */
    initialize: function(options) {
        this._super('initialize', [options]);

        this._initProperties();
    },

    /**
     * Initialize properties
     */
    _initProperties: function() {
        this.configModule = this.context.get('target');

        this.saveMessage = 'LBL_HISTORICALLY_DELTA_SAVED';
    },

    /**
     * Get the available fields from the module
     *
     * @param {String} moduleName
     * @param {Array} targetedFields
     *
     * @return {Array}
     */
    _getAvailableModuleFields: function(moduleName, targetedFields) {
        if (!moduleName) {
            return [];
        }

        const moduleMeta = app.metadata.getModule(moduleName);

        if (!moduleMeta) {
            return [];
        }

        const moduleFields = moduleMeta.fields;

        const result = targetedFields
            .filter(key => moduleFields[key])
            .map(key => ({ name: moduleFields[key].name, vname: moduleFields[key].vname}));

        return result;
    },

    /**
     * @inheritdoc
     */
    copySettingsToModel: function(settings) {
        this._super('copySettingsToModel', [settings]);

        const dataKey = this.dataKey;

        this.originalSettings = app.utils.deepCopy(settings);

        if (_.has(settings, dataKey)) {
            this.initialFieldsSettings = app.utils.deepCopy(settings[dataKey][this.configModule]);
            this.model.set(this.dataKey, this.initialFieldsSettings, {silent: true});

            const targetedModuleFields = _.keys(this.initialFieldsSettings);
            const existing = this.initialFieldsSettings || {};
            const available = this._getAvailableModuleFields(this.configModule, targetedModuleFields);

            this.availableFields = available.map(field => {
                const fieldData = existing[field.name] || {};
                return {
                    ...field,
                    enabled: !!fieldData.enabled
                };
            });
        }
    },

    /**
     * Enable/disable a module.
     * @param {UIEvent} e
     */
    changeHandler: function(e) {
        const fieldName = $(e.currentTarget).data('name');
        const isChecked = e.currentTarget.checked;

        const data = app.utils.deepCopy(this.model.get(this.dataKey)) || {};

        if (!_.has(data, fieldName)) {
            data[fieldName] = {};
        }

        data[fieldName].enabled = isChecked;
        this.model.set(this.dataKey, data, {silent: false});
    },

    /**
     * @inheritdoc
     */
    toggleHeaderButton: function(state) {
        var header = this.layout.getComponent('historically-delta-config-header');

        if (header) {
            header.enableButton(state);
        }
    },

    /**
     * @inheritdoc
     */
    save: function() {
        const currentModule = this.configModule;
        const dataKey = this.dataKey;

        const fullSettings = app.utils.deepCopy(this.originalSettings);

        if (!_.has(fullSettings, dataKey)) {
            fullSettings[dataKey] = {};
        }

        const modelData = this.model.get(dataKey) || fullSettings[dataKey][currentModule] || {};

        fullSettings[dataKey][currentModule] = modelData;

        const options = {
            success: _.bind(this.saveSuccessHandler, this),
            error: _.bind(this.saveErrorHandler, this)
        };

        app.api.call('create', app.api.buildURL(this.module, this.settingPrefix), fullSettings, options);
    },

    /**
     * On a successful save return to the Administration page.
     */
    closeView: function() {
        app.sync();

        if (app.drawer && app.drawer.count()) {
            app.drawer.close(this.context, this.context.get('model'));
        } else {
            app.router.navigate(this.module, {trigger: true});
        }
    },

    /**
     * @inheritdoc
     */
    _dispose: function() {
        if (this.context) {
            this.context.off('save:config', this.boundSaveHandler);
        }
        this._super('_dispose');
    },
})
