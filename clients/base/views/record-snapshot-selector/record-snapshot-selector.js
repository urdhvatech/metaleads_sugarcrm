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
 * @class View.Views.Base.RecordSnapshotSelectorField
 * @alias SUGAR.App.view.views.BaseRecordSnapshotSelectorField
 * @extends View.View
 */
({
    /**
     * Event listeners
     */
    events: {
        'change [data-fieldname=recordSnapshotSelector]': 'intervalParamsChanged',
    },

    /**
     * @inheritdoc
     */
    initialize: function(options) {
        this._super('initialize', [options]);

        this._initProperties();
        this._registerEvents();
    },

    /**
     * Register events
     */
    _registerEvents: function() {
        if (!this._hasAccess()) {
            return false;
        }

        if (this.collection) {
            this.listenTo(
                this.collection,
                'before:save',
                this.resetSnapshot
            );

            this.listenTo(
                this.collection,
                'data:sync:complete',
                this._filteredRequestSnapshot
            );

            // listen to the pagination when the data is loaded from cache
            // example: in the list view, when the user go back to the previous page
            this.listenTo(
                this.collection,
                'list:paginate:from:cache:complete',
                this.requestSnapshot
            );
        }

        if (this.context) {
            this.listenTo(
                this.context,
                'list:columns:reorder',
                () => setTimeout(() => this.requestSnapshot(), 0),
                this
            );

            this.listenTo(
                this.context,
                'list:columns:reset',
                () => setTimeout(() => this.requestSnapshot(), 0),
                this
            );

            this._registerDashableRecordEvents();
        }
    },

    /**
     * Register events for dashablerecord view.
     */
    _registerDashableRecordEvents: function() {
        this.listenTo(
            this.context,
            'dashablerecord:tab:list:switch',
            this.requestSnapshot,
            this
        );

        this.listenTo(
            this.context,
            'dashablerecord:tab:list:loaded',
            this.requestSnapshot,
            this
        );

        this.listenTo(
            this.context,
            'editablelist:save',
            this.requestSnapshot,
            this
        );

        if (this.options && this.options.layoutType === 'dashablerecord') {
            this.listenTo(
                this.model,
                'data:sync:complete',
                this._dashletDeltaReload,
                this
            );

            this.listenTo(
                this.model,
                'before:save',
                this.resetSnapshot,
                this
            );
        }
    },

    /**
     * Property initialization
     *
     */
    _initProperties: function() {
        this._snapshotTimeframes = app.lang.getAppListStrings('record_snapshot_selector');
        this._snapshotValue = this.options && this.options.parentDeltaState ?
            this.options.parentDeltaState : this._getLastStateValue();

        this._targetAttribute = '[data-target-additional="true"]';
        this._isHistoricallyDeltaConfigured = this.hasHistoricallyDeltaAccess(this.module);
        const options = this.options || {};
        const meta = options.meta || {};

        this.cssChild = meta.cssChild || options.cssChild || '';

        /**
         * Keep track of requests, so we can abort them
         * when the user will change the dropdown before we get the value back
         */
        this.snapshotRequestsHistory = {};
        this._defaultIcon = 'changes-icon';
        this._loadingIcon = 'spinner';
        this._successIcon = 'changes-success-icon';
        this._errorIcon = 'changes-error-icon';
        this._selectionIcon = this._defaultIcon;
        this._deltaFields = app.utils.getDeltaActiveFields(this.module) || [];
    },

    /**
     * Determine if the user has access to Historically Delta features.
     *
     * @param {string} module
     */
    hasHistoricallyDeltaAccess: function(module) {
        const hasHistoricallyDeltaLicense = app.user.hasHistoricallyDeltaLicense();
        const isHistoricallyDeltaEnabled = app.utils.isHistoricallyDeltaEnabled(module);
        const hasDeltaActiveFields = app.utils.getDeltaActiveFields(module).length > 0;

        return hasHistoricallyDeltaLicense && isHistoricallyDeltaEnabled && hasDeltaActiveFields;
    },

    /**
     * Wrapper for requestSnapshot that filters out favorite operations.
     * This is called by the data:sync:complete event listener.
     *
     * @param {string} method - The sync method that was executed.
     * @param {Object} options - The options passed to the sync method.
     * @param {Object} request - The HTTP request object.
     */
    _filteredRequestSnapshot: function(method, options, request) {
        // Skip delta reload for favorite operations
        if (options && options.favorite) {
            return;
        }

        this.requestSnapshot();
    },

    /**
     * Request a snapshot based on provided IDs and module.
     *
     * @param {Array} ids - Array of record IDs to request snapshot for.
     * @param {string} module - The module name for which the snapshot is requested.
     */
    requestSnapshot: function() {
        const hasDeltaFields = this._hasDeltaFields();
        this._toggleSnapshotDropdown(hasDeltaFields);

        if (!hasDeltaFields) {
            return; // Exit early if dropdown should be disabled
        }

        const ids = this._getIds();

        if (_.isEmpty(ids)) {
            return;
        }

        const module = this.module;

        if (_.isEmpty(module)) {
            return;
        }

        const deltaDate = this._snapshotValue;

        this._startSnapshot(deltaDate, ids, module);
    },

    /**
     * Reset the snapshot by cleaning up fields in case of an update
     */
    resetSnapshot: function() {
        if (!this._hasDeltaFields()) {
            return;
        }

        const ids = this._getIds();

        if (_.isEmpty(ids)) {
            return;
        }

        const module = this.module;

        if (_.isEmpty(module)) {
            return;
        }

        this.cleanUpFields(ids, module);
    },

    /**
     * Get the IDs of the selected records.
     * @return {Array}
     */
    _getIds: function() {
        let ids = [];

        if (
            this.collection &&
            this.collection.models &&
            this.collection.models.length > 0
        ) {
            ids = this.collection.models.map(model => model.get('id'));
        } else if (
            this.model &&
            this.model.get('id')
        ) {
            ids = [this.model.get('id')];
        }

        return ids;
    },

    /**
     * Check if there are any delta fields configured in the current view
     * @return {boolean}
     */
    _hasDeltaFields: function() {
        const isSubpanel = this.context.get('isSubpanel') || false;
        const type = this.context.get('dataView') || this.options.layoutType;

        if (!type) {
            return false;
        }

        if (type === 'list' || isSubpanel) {
            return this._hasDeltaFieldsListView(type);
        } else if (type === 'dashablelist') {
            return this._hasDeltaFieldsDashletListView(type);
        } else if (type === 'record') {
            return this._hasDeltaFieldsRecordView(type);
        } else if (type === 'recorddashlet') {
            return this._hasDeltaFieldsDashletRecordView(type);
        }

        return false;
    },

    /**
     * Check if the field is a delta field
     *
     * @param {Object|string} field
     * @return {boolean}
     */
    _isDeltaField: function(field) {
        if (!field || !this._deltaFields) {
            return false;
        }

        const fieldName = _.isString(field) ? field : field.name;

        return this._deltaFields.includes(fieldName);
    },

    /**
     * Check if the list view has delta fields configured
     *
     * @param {string} type
     * @return {boolean}
     */
    _hasDeltaFieldsListView: function(type) {
        const parent = this._getComponentByName(type) || {};
        const recordList = _.find(parent._components, (component) => component.name === 'recordlist') || {};
        const fields = recordList._fields || parent._fields || {};
        const visibleFields = fields.visible || [];

        return visibleFields.some(_.bind(this._isDeltaField, this));
    },

    /**
     * Check if the dashlet list view has delta fields configured
     *
     * @param {string} type
     * @return {boolean}
     */
    _hasDeltaFieldsDashletListView: function(type) {
        const component = this._getComponentByName(type) || {};
        const metaFields = component.metaFields || [];

        return metaFields.some(_.bind(this._isDeltaField, this));
    },

    /**
     * Check if the record view has delta fields configured
     *
     * @param {string} type
     * @return {boolean}
     */
    _hasDeltaFieldsRecordView: function(type) {
        const viewMeta = app.metadata.getView(this.module, type);

        if (!viewMeta || !_.isArray(viewMeta.panels)) {
            return false;
        }

        for (const panel of viewMeta.panels) {
            if (panel.fields && panel.fields.some(_.bind(this._isDeltaField, this))) {
                return true;
            }
        }

        return false;
    },

    /**
     * Check if the dashlet record view has delta fields configured
     *
     * @param {string} type
     * @return {boolean}
     */
    _hasDeltaFieldsDashletRecordView: function(type) {
        const component = this._getComponentByName('dashablerecord') || {};
        const tabs = component.meta && component.meta.tabs;
        const targetTab = _.first(_.filter(tabs, (tab) => tab.module === this.module));

        if (!targetTab) {
            return false;
        }

        if (targetTab.type === 'record') {
            return this._hasDeltaFieldsRecordView(type);
        }

        return targetTab.fields &&
            targetTab.fields.some(_.bind(this._isDeltaField, this));
    },

    /**
     * Get the component by name from the layout's components.
     *
     * @param {string} name
     * @return {Object|null}
     */
    _getComponentByName: function(name) {
        return _.find(this.layout._components, (component) => component && component.name === name);
    },

    /**
     * Toggle the snapshot dropdown based on the visible delta fields
     *
     * @param {boolean} isEnabled
     */
    _toggleSnapshotDropdown: function(isEnabled) {
        if (!this._select2 ||
            !this._select2.recordSnapshotSelector) {
            return;
        }
        const select2Container = this._select2.recordSnapshotSelector;

        if (isEnabled) {
            this.changeSelectIcon(this._successIcon);
            select2Container.enable();
        } else if (!select2Container._enabled) {
            this.changeSelectIcon(this._defaultIcon);
            select2Container.disable();
        }

        this.changeSelectTooltip(!isEnabled, app.lang.get('LBL_NO_VISIBLE_DELTA_FIELDS'));
    },

    /**
     * Determine the field placement type based on various context and options.
     * This is used to identify where the field is placed in the UI, such as in a dashlet or a specific layout.
     *
     * @return {string}
     */
    _determineFieldPlacementType: function() {
        const options = this.options || {};
        const context = this.context || {};
        const optionsMeta = options.meta || {};
        const optionsDef = options.def || {};
        const controllerContext = app.controller && app.controller.context;
        const layout = this.layout || {};
        const layoutOptions = layout.options || {};
        let type = app.utils.deepCopy(optionsMeta.layoutType) ||
                app.utils.deepCopy(optionsDef.layoutType) ||
                options.layoutType ||
                (typeof context.get === 'function' && (context.get('layout') || context.get('layoutName'))) ||
                (controllerContext && typeof controllerContext.get === 'function' && controllerContext.get('layout'));

        if (!type) {
            //maybe we're in a special dashlet
            const dashletType = layout.meta && layout.meta.type;
            const dashletId = layoutOptions.dashletMetaId;

            if (dashletType && dashletId) {
                type = `${dashletType}_${dashletId}`;
            }
        } else if (layoutOptions.dashletMetaId) {
            //we have to get the dashlet Id to make the dropdown unique per dashlet
            type = `${type}_${layoutOptions.dashletMetaId}`;
        }

        if (!type) {
            app.logger.warn('RecordSnapshotSelector: unable to determine field placement type');

            return '';
        }

        return type;
    },

    /**
     * @inheritdoc
     */
    _render: function() {
        if (!this._hasAccess()) {
            this.$el.hide();
            return;
        }

        this._super('_render');

        this._select2 = {};
        this.select2('recordSnapshotSelector', '_queryRecordSnapshotSelector', {
            minimumResultsForSearch: Infinity,
            formatSelection: _.bind(this._getSelect2SnapshotSelection, this),
        });

        this._updateElementStyle();
        this._updateUIElements();
    },

    /**
     * Update the element's style based on the current context
     */
    _updateElementStyle: function() {
        if (!this.context || !this.$el) {
            return;
        }

        const isSubpanel = this.context.get('isSubpanel') || false;

        if (isSubpanel) {
            this.$el.addClass('border-t border-[--border-base] pt-1');
        }

    },

    /**
     * Format the select2 element
     *
     * @param {Object} item
     * @return {string}
     */
    _getSelect2SnapshotSelection: function(data) {
        const icon = data.icon || this._defaultIcon;

        return `
            <span class="flex items-center">
                ${this._getIconElement(icon)}
                ${App.lang.get('LBL_SHOW_CHANGES')}: ${Handlebars.Utils.escapeExpression(data.text)}
            </span>
        `;
    },

    /**
     * Render the icon based on the type provided.
     *
     * @param {string} type
     */
    _getIconElement: function(type) {
        if (!type || _.isEmpty(type)) {
            return '';
        }

        if (type === 'spinner') {
            return `<i class="sicon sicon-refresh sicon-is-spinning text-blue-800"></i>`;
        }

        if (
            app &&
            app.template &&
            typeof app.template.get === 'function') {
            const templateName = `record-snapshot-selector.${type}`;
            const iconPart = app.template.get(templateName);

            if (typeof iconPart === 'function') {
                return iconPart(this);
            } else {
                return '';
            }
        }

        return '';
    },

    /**
     * Update UI Elements
     */
    _updateUIElements: function() {
        if (!this._select2 || !this._select2.recordSnapshotSelector) {
            return;
        }

        this._select2.recordSnapshotSelector.data({
            id: this._snapshotValue,
            text: this._snapshotTimeframes[this._snapshotValue],
            icon: this._selectionIcon
        });
    },

    /**
     * Handle option changes
     *
     * @param {jQuery} e
     */
    intervalParamsChanged: function(e) {
        const value = e.currentTarget.value;
        this._snapshotValue = value;

        // Show spinner
        this.changeSelectIcon(this._loadingIcon);

        this._setUserLastState(value);

        let ids = [];

        if (this.collection && this.collection.models && this.collection.models.length > 0) {
            ids = this.collection.models.map(model => model.get('id'));
        } else if (this.model && this.model.get('id')) {
            ids = [this.model.get('id')];
        }

        if (_.isEmpty(ids)) {
            this.changeSelectIcon(this._defaultIcon);
            return;
        }

        const module = this.module;

        if (_.isEmpty(module)) {
            this.changeSelectIcon(this._defaultIcon);
            return;
        }

        this.model.deltaLastState = value;

        const deltaDate = value;

        this._startSnapshot(deltaDate, ids, module);
    },

    /**
     * Change the select icon
     *
     * @param {string} icon
     */
    changeSelectIcon: function(icon) {
        this._selectionIcon = icon;
        this._updateUIElements();

        const addTooltip = icon === this._errorIcon;
        this.changeSelectTooltip(addTooltip, app.lang.get('LBL_ERROR_LOADING_CHANGES'));
    },

    /**
     * Change the tooltip for the select element
     *
     * @param {boolean} addTooltip
     * @param {string} tooltipText
     */
    changeSelectTooltip: function(addTooltip = false, tooltipText = '') {
        if (!this._select2 ||
            !this._select2.recordSnapshotSelector ||
            !this._select2.recordSnapshotSelector.container) {
            return;
        }
        const select2Container = this._select2.recordSnapshotSelector;
        const select2ContainerEl = select2Container.container;

        if (addTooltip) {
            $(select2ContainerEl)
                .attr('rel', 'tooltip')
                .attr('title', tooltipText)
                .attr('data-bs-placement', 'bottom');
        } else {
            $(select2ContainerEl).removeAttr('rel title data-bs-placement');
        }
    },

    /**
     * Start the snapshot process based on the selected delta date.
     * If the selected value is 'none', it cleans up fields without making an API call.
     * If a valid delta date is selected, it cleans up fields and requests snapshot values.
     *
     * @param {string} deltaDate
     * @param {Array} ids
     * @param {string} module
     */
    _startSnapshot(deltaDate, ids, module) {
        if (deltaDate === this._hideStateSelector()) {
            // If the selected value is 'none', clean up fields without making an API call
            this.cleanUpFields(ids, module);
            this.changeSelectIcon(this._defaultIcon);
            this.context.trigger('record:deltas:calculated', false);
        } else {
            this.cleanUpFields(ids, module);
            this.requestSnapshotValues(ids, module, deltaDate);
        }
    },

    /**
     * Populate the select2 list
     *
     * @param {Object} query
     *
     */
    _queryRecordSnapshotSelector: function(query) {
        this._query(query, '_snapshotTimeframes');
    },

    /**
     * Generic select2 selection list builder
     *
     * @param {Object} query
     * @param {string} list
     *
     */
    _query: function(query, list) {
        let listElements = this[list];
        let data = {
            results: [],
            more: false
        };

        if (_.isObject(listElements)) {
            _.each(listElements, function pushValidResults(element, index) {
                if (query.matcher(query.term, element)) {
                    data.results.push({id: index, text: element});
                }
            });
        } else {
            listElements = null;
        }

        query.callback(data);
    },

    /**
     * Create generic Select2 options object
     *
     * @return {Object}
     */
    _getSelect2Options: function(additionalOptions) {
        var select2Options = _.extend({}, additionalOptions);

        return select2Options;
    },

    /**
     * Create generic Select2 component or return a cached select2 element
     *
     * @param {string} fieldname
     * @param {string} queryFunc
     */
    select2: function(fieldname, queryFunc, additionalOptions) {
        if (this._select2 && this._select2[fieldname]) {
            return this._select2[fieldname];
        };

        this._disposeSelect2(fieldname);

        if (queryFunc && this[queryFunc]) {
            additionalOptions.query = _.bind(this[queryFunc], this);
        }

        var el = this.$('[data-fieldname=' + fieldname + ']')
            .select2(this._getSelect2Options(additionalOptions))
            .data('select2');

        this._select2 = this._select2 || {};
        this._select2[fieldname] = el;
        this._select2[fieldname].disable();

        return el;
    },

    /**
     * Dispose a select2 element
     */
    _disposeSelect2: function(name) {
        if (this._select2 && _.isObject(this._select2)) {
            delete this._select2[name];
        }

        this.$('[data-fieldname=' + name + ']').select2('destroy');
    },

    /**
     * Prevent triggering the model change to avoid the api call for filtering
     * before our values are collected
     *
     * @inheritdoc
     */
    unformat: function() {
        return this._snapshotValue;
    },

    /**
     * Remove subsection field
     *
     * @param {Array} ids
     * @param {string} module
     */
    cleanUpFields: function(ids, module) {
        if (_.isEmpty(ids) || _.isEmpty(module)) {
            return;
        }

        ids.map(id => {
            const eventName = `${id}:subsection:field:remove`;
            this.context.trigger(eventName, this._targetAttribute);
        });
    },

    /**
     * Request snapshot values from server-side
     */
    requestSnapshotValues: function(ids, module, deltaDate) {
        const snapshotValue = this._snapshotValue;
        const requestType = 'create';
        const apiPath = 'historically/delta';

        const requestMeta = {
            'module': module,
            'recordIds': ids,
            'deltaDate': deltaDate
        };

        const success = _.bind(function successCallback(payload) {
            if (this.disposed) {
                return;
            }

            _.each(payload, (record, recordId) => {
                _.each(record, (fieldData, fieldName) => {
                    const hbsName = 'subsection';

                    const eventName = `${recordId}:${fieldName}:subsection:field:create`;
                    this.context.trigger(eventName, hbsName, fieldData, this._targetAttribute, module);
                }, this);
            }, this);

            const hasDeltaFields = this._hasDeltaFields();
            this._toggleSnapshotDropdown(hasDeltaFields);

            this.context.trigger('record:deltas:calculated', true);
        }, this);

        const error = _.bind(function errorCallback(e) {
            if (this.disposed) {
                return;
            }

            app.logger.error('Error while fetching snapshot values', e);

            this.changeSelectIcon(this._errorIcon);
        }, this);

        const complete = _.bind(function completeCallback() {
            if (this.disposed) {
                return;
            }

            delete this.snapshotRequestsHistory[snapshotValue];
        }, this);

        const apiCallbacks = {
            success,
            error,
            complete,
        };

        const apiUrl = app.api.buildURL(apiPath, requestType, requestMeta, {});
        const request = app.api.call(requestType, apiUrl, requestMeta, apiCallbacks);

        if (request) {
            this.snapshotRequestsHistory[snapshotValue] = request;
        }
    },

    /**
     * Get the last state value for the user
     *
     * @return {string}
     */
    _getLastStateValue: function() {
        const key = this._buildUserLastStateKey();
        const lastState = app.user.lastState.get(key);

        if (!_.isUndefined(lastState)) {
            return lastState;
        }

        return this._getUserDefaultState();
    },

    /**
     * Determine the key for the user last state
     *
     * @return {string}
     */
    _buildUserLastStateKey: function() {
        const viewType = this._determineFieldPlacementType();
        const currentUserId = app.user.get('id');
        const options = this.options || {};
        const controllerModule = app.controller && app.controller.context && app.controller.context.get('module') || '';
        const contextModule = options.module || controllerModule;

        const identifierKey = `record_snapshot_selector_${contextModule}_${viewType}_${currentUserId}_value`;

        if (this.layout && this.layout.module) {
            const placeholderModule = controllerModule || this.layout.module;

            const lastStateKey = app.user.lastState.buildKey(this.name, identifierKey, placeholderModule);

            return lastStateKey;
        }

        return '';
    },

    /**
     * Get the default state for the user
     *
     * @return {string}
     */
    _getUserDefaultState: function() {
        return '7_days';
    },

    /**
     * Get the state selector value to hide the selector
     *
     * @return {string}
     */
    _hideStateSelector: function() {
        return 'none';
    },

    /**
     * We don't load any data here, this is just a placeholder
     * to avoid errors in the base view
     * @inheritdoc
     */
    loadData: function() {},

    /**
     * Update user last state
     *
     * @param {string} value
     */
    _setUserLastState: function(value) {
        const key = this._buildUserLastStateKey();

        app.user.lastState.set(key, value);
    },

    /**
     * Reload the deltas dashlet when the dashable record validation is complete
     */
    _dashletDeltaReload: function() {
        if (this.context.get('_dashableValidationComplete')) {
            this.requestSnapshot();
            this.context.set('_dashableValidationComplete', false, {silent: true});
        }
    },

    /**
     * Check if the user has access to the view and if the historically delta is configured
     *
     * @return {boolean}
     */
    _hasAccess: function() {
        return app.acl.hasAccess('list', this.module) && this._isHistoricallyDeltaConfigured;
    },

    /**
     * @inheritdoc
     */
    _dispose: function() {
        this._disposeSelect2('recordSnapshotSelector');

        this._select2 = {};
        this.snapshotRequestsHistory = {};

        this._super('_dispose');
    },
});
