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
 * Dashablelist is a dashlet representation of a module list view. Users can
 * build dashlets of this type for any accessible and approved module with
 * their choice of columns from the list view for their chosen module.
 *
 * Options:
 * {String}  module             The module from which the records are
 *                              retrieved.
 * {String}  label              The string (i18n or hard-coded) representing
 *                              the dashlet name that the user sees.
 * {Array}   display_columns    The field names of the columns to include in
 *                              the list view.
 * {String}  filter_id          Filter to be applied, defaults to:
 *                              'assigned_to_me'.
 * {Integer} limit              The number of records to retrieve for the list
 *                              view.
 * {Integer} auto_refresh       How frequently (in minutes) that the dashlet
 *                              should refresh its data collection.
 *
 * Example:
 * <pre><code>
 * // ...
 * array(
 *     'module'          => 'Accounts',
 *     'label'           => 'LBL_MODULE_NAME',
 *     'display_columns' => array(
 *         'name',
 *         'phone_office',
 *         'billing_address_country',
 *     ),
 *     'filter_id'       => 'assigned_to_me',
 *     'limit'           => 15,
 *     'auto_refresh'    => 5,
 * ),
 * //...
 * </code></pre>
 *
 * Note that there are two concepts of "intelligence" for this dashlet.
 *
 * The intelligence property on the controller `this.intelligent` indicates
 * if the dashlet is allowed to link to a record.
 *
 * The intelligent setting retrieved by `this.settings.get('intelligent')` is
 * only relevant if the intelligence property `this.intelligent` is true. This
 * setting indicates if the dashlet is actively linking to a record.
 *
 * @class View.Views.Base.DashablelistView
 * @alias SUGAR.App.view.views.BaseDashablelistView
 * @extends View.Views.Base.ListView
 */
({
    extendsFrom: 'ListView',

    className: 'list-view dark:border-t dark:border-[--border-base]',

    dataView: '',

    /**
     * The plugins used by this view.
     */
    plugins: ['Dashlet', 'Pagination', 'ConfigDrivenList', 'Editable',
            'ErrorDecoration', 'ActionButton', 'ListRowActions', 'SugarLogic',
            'DocumentMerge'],
    /**
     * We want to load field `list` templates
     */
    fallbackFieldTemplate: 'list',

    /**
     * The default settings for a list view dashlet.
     *
     * @property {Object}
     */
    _defaultSettings: {
        limit: 5,
        freeze_first_column: true,
        filter_id: 'assigned_to_me',
        intelligent: '0'
    },

    rightColumns: [],
    leftColumns: [],
    /**
     * List of current inline edit models.
     *
     * @property
     */
    toggledModels: null,

    rowFields: {},

    /**
     * Modules that are permanently blacklisted so users cannot configure a
     * dashlet for these modules.
     *
     * @property {Array}
     */
    moduleBlacklist: [
        'Home',
        'Forecasts',
        'ProductCategories',
        'ProductTemplates',
        'ProductTypes',
        'UserSignatures',
        'OutboundEmail',
        'Administration',
    ],

    /**
     * Module Additions
     *
     * When a specific module is allowed, we should add these other modules that are
     * not first class modules.
     *
     * @property {Array}
     */
    additionalModules: {
        'Project': ['ProjectTask']
    },

    /**
     * Cache of the modules a user is allowed to see.
     *
     * The keys are the module names and the values are the module names after
     * resolving them against module and/or app strings. The cache logic can be
     * seen in {@link BaseDashablelistView#_getAvailableModules}.
     *
     * @property {Object}
     */
    _availableModules: {},

    /**
     * Cache of the fields found in each module's list view definition.
     *
     * This hash is multi-dimensional. The first set of keys are the module
     * names and the values are objects where the keys are the field names and
     * the values are the field names after resolving them against module
     * and/or app strings. The cache logic can be seen in
     * {@link BaseDashablelistView#_getAvailableColumns}.
     *
     * @property {Object}
     */
    _availableColumns: {},

    /**
     * Flag indicates if dashlet is intelligent.
     *
     * If the dashlet is intelligent, it can be linked to a record on the main
     * context, e.g. on the Record View.
     */
    intelligent: null,

    /**
     * Flag indicates if a module is available for display.
     */
    moduleIsAvailable: true,

    /**
     * Flag indicates if dashlet filter is accessible.
     */
    filterIsAccessible: true,

    /**
     * @inheritdoc
     *
     * Append lastStateID on metadata in order to active user cache.
     */
    initialize: function(options) {
        options.meta = _.extend({}, options.meta, {
            last_state: {
                id: 'dashable-list'
            }
        });

        this.contextEvents = _.extend({}, this.contextEvents, {
            'list:editall:fire': 'toggleEdit',
            'list:editrow:fire': 'editClickedRow',
            'list:deleterow:fire': 'warnDelete'
        });

        let dashableListMeta = this._initializeMetadata();

        options.meta.rowactions = dashableListMeta.rowactions;

        this._super('initialize', [options]);
        this.checkIntelligence();
        this._noAccessTemplate = app.template.get(this.name + '.noaccess');

        this.context.set('isUsingListPagination', this._hasAccess('list'));

        this.snapshotSelector = null;
        this.scrollContainer = this.$el;

        this.toggledModels = {};

        this.addRowActions();
    },

    /**
     * Bind events for the dashable list view.
     */
    _bindEvents: function() {
        let rightColumnsEvents = {};
        if (this.rightColumns.length) {
            rightColumnsEvents = {
                'hidden.bs.dropdown .actions': 'resetDropdownDelegate',
                'shown.bs.dropdown .actions': 'delegateDropdown',
                'shown.bs.dropdown .morecol': '_toggleAria',
                'hidden.bs.dropdown .morecol': '_toggleAria'
            };
        }
        this.events = _.extend({}, this.events, rightColumnsEvents);

        //Extend the prototype's events object to setup additional events for this controller
        this.events = _.extend({}, this.events, {
            'click [name=inline-cancel]': 'resize',
            'dblclick tr.single': 'doubleClickEdit'
        });

        this.on('render render:rows', this._setRowFields, this);
        app.routing.before('route', this.beforeRouteDelete, this);
        $(window).on('beforeunload.delete' + this.cid, _.bind(this.warnDeleteOnRefresh, this));
    },

    /**
     * @inheritdoc
     */
    addRowActions: function() {
        // Clear previous column definitions to avoid duplicates
        this.rightColumns = [];
        this.leftColumns = [];

        let _generateMeta = function(label, cssClass, buttons) {
            return {
                type: 'fieldset',
                fields: [
                    {
                        type: 'rowactions',
                        label: label || '',
                        css_class: cssClass,
                        buttons: buttons || []
                    }
                ],
                value: false,
                sortable: false
            };
        };

        const def = this.meta.rowactions;
        if (_.isUndefined(def) || _.isEmpty(def)) {
            return;
        }
        this.rightColumns.push(_generateMeta(def.label, def.css_class, def.actions));

        // Add blank left column
        this.leftColumns.push({
            'type': 'fieldset',
            'label': '',
            'sortable': false,
            'fields': []
        });

        // Add Cancel button to left
        this.leftColumns[0].fields.push({
            type: 'editablelistbutton',
            label: 'LBL_CANCEL_BUTTON_LABEL',
            name: 'inline-cancel',
            css_class: 'btn-link btn-invisible inline-cancel ellipsis_inline'
        });

        // Add Save button to right
        this.rightColumns[0].css_class = `overflow-visible w-fit`;
        this.rightColumns[0].fields.push({
            type: 'editablelistbutton',
            label: 'LBL_SAVE_BUTTON_LABEL',
            name: 'inline-save',
            css_class: 'btn-primary ellipsis_inline'
        });
    },

    /**
     * Toggle editable selected row's model fields.
     *
     * @param {string} modelId Model Id.
     * @param {boolean} isEdit True for edit mode, otherwise toggle back to list mode.
     */
    toggleRow: function(modelId, isEdit) {
        this._super('toggleRow', [modelId, isEdit]);

        this._toggleLeftActionsColumn(isEdit, modelId);
    },

    /**
     * Toggles the left actions column visibility based on the edit mode.
     * @param {boolean} isEdit
     * @param {string} modelId
     */
    _toggleLeftActionsColumn: function(isEdit, modelId = null) {
        if (!isEdit && modelId) {
            // If we are not in edit mode, but a modelId is provided, we only toggle the left column for that model
            let selectedRowSelector = '.left-column[name="' + this.module + '_' + modelId + '"]';
            this.$('table').find(selectedRowSelector).toggleClass('hide', isEdit);

            this._hideLeftActionsHeader(isEdit);
            this._moveLeftActionsColumn(isEdit);
            return;
        }

        this.$('table').find('.left-column').toggleClass('hide', !isEdit);

        this._moveLeftActionsColumn(isEdit);
    },

    /**
     * Moves the left actions column to the left or right based on the edit mode.
     * @param {*} isEdit
     */
    _moveLeftActionsColumn: function(isEdit) {
        this.$('th.stick-first').toggleClass('[&]:ltr:left-0', !isEdit);
        this.$('th.stick-first').toggleClass('[&]:rtl:right-0', !isEdit);
        this.$('td.stick-first').toggleClass('[&]:ltr:left-0', !isEdit);
        this.$('td.stick-first').toggleClass('[&]:rtl:right-0', !isEdit);

        const cancelButtons = this.$('table').find('.left-column .inline-cancel');

        if (_.isObject(cancelButtons) && cancelButtons.length <= 1) {
            // if there are no models in edit mode
            this.$('th.stick-first').toggleClass('[&&&]:left-[68px]', isEdit);
            this.$('td.stick-first').toggleClass('[&&&]:left-[68px]', isEdit);
        } else {
            this.$('th.stick-first').toggleClass('[&&&]:left-[68px]', true);
            this.$('td.stick-first').toggleClass('[&&&]:left-[68px]', true);
        }
    },

    /**
     * Hides the left actions header if there are no cancel buttons present.
     * @param {boolean} isEdit - Whether the row is in edit mode.
     */
    _hideLeftActionsHeader: function(isEdit) {
        const cancelButtons = this.$('table').find('.left-column .inline-cancel');
        const leftColumn = this.$('table').find('.left-column');
        if (_.isObject(cancelButtons) && cancelButtons.length <= 1) {
            leftColumn.toggleClass('hide', !isEdit);
        }
    },

    /**
     * Checks if list rows are editable. Check the rowactions to see if the action associated with
     * starting a row edit is used.
     * @return {boolean}
     */
    isRowEditable: function() {
        let meta = this.options.meta;
        if (_.isEmpty(meta) || _.isEmpty(meta.rowactions) || _.isEmpty(meta.rowactions.actions)) {
            return false;
        }

        return meta.rowactions.actions.some(action => action.event === this.editEventName);
    },

    /**
     * Retrieve the metadata of the recordlist view
     *
     * @return {Object}
     * @private
     */
    _initializeMetadata: function(moduleName) {
        if (moduleName) {
            return app.metadata.getView(moduleName, 'recordlist') || {};
        }
        return app.metadata.getView(null, 'dashablelist') || {};
    },

    /**
     * Prevent shortcuts from dashablelist overriding the shortcuts set by the main view
     * @override
     */
    registerShortcuts: _.noop,

    /**
     * Check if dashlet can be intelligent.
     *
     * A dashlet is considered intelligent when the data relates to the current
     * record.
     *
     * @return {String} Whether or not the dashlet can be intelligent.
     */
    checkIntelligence: function() {
        const context = this.getCurrentContext();
        const module = context.get('module');
        const layout = context.get('layout');
        const targetLayout = context.get('targetLayout');

        const isRecordOrFocus = layout === 'record' || layout === 'focus' || targetLayout === 'focus';
        const isIntelligent = isRecordOrFocus && !_.contains(this.moduleBlacklist, module);

        this.intelligent = isIntelligent ? '1' : '0';
    },

    /**
     * Gets the current context for the dashlet.
     *
     * Returns the parent layout context if available, otherwise falls back to
     * the global app controller context. Important for "intelligent" dashlets
     * that need to relate to the current record.
     *
     * @return {Object} The parent layout context or app controller context.
     */
    getCurrentContext: function() {
        const layoutContext = this.options.layout && this.options.layout.context;
        const currentContext = layoutContext && layoutContext.get('parentLayoutContext') || app.controller.context;

        return currentContext;
    },

    /**
     * Show/hide `linked_fields` field.
     *
     * @param {String} visible '1' to show the field, '0' to hide it.
     * @param {String} [intelligent='1'] Whether the dashlet is in intelligent
     *   mode or not.
     */
    setLinkedFieldVisibility: function(visible, intelligent) {
        var field = this.getField('linked_fields');
        if (!field) {
            return;
        }
        intelligent = (intelligent === false || intelligent === '0') ? '0' : '1';
        var fieldEl = this.$('[data-name=linked_fields]');
        fieldEl.toggle(visible === '1' && intelligent === '1' && !_.isEmpty(field.items));
    },

    /**
     * In case the filter for the dashlet has been retrieved successfully
     * a filter definition based dashlet setup will be triggered.
     *
     *  @param {Object} data object from specified filter request.
     */
    triggerDashletSetup: function(data) {
        this.filterIsAccessible = true;
        this._displayDashlet(data.filter_definition);
    },

    /**
     * Function to check if the set filter is available for the particular module
     *
     * @param moduleName {string} module name
     * @param filterCheck {string} filter_id being checked
     * @return {string} Return the given filter is it exists for that module, else return 'all_records'
     * @private
     */
    _checkFilterPerModule: function(moduleName, filterCheck) {
        let availableFilters = app.metadata.getModule(moduleName, 'filters').basic.meta.filters;
        let filter = _.find(availableFilters, function(filter) {
                return filter.id === filterCheck;
            });
        return _.isUndefined(filter) ? 'all_records' : filter.id;
    },

    /**
     * Must implement this method as a part of the contract with the Dashlet
     * plugin. Kicks off the various paths associated with a dashlet:
     * Configuration, preview, and display.
     *
     * @param {String} view The name of the view as defined by the `oninit`
     *   callback in {@link DashletView#onAttach}.
     */
    initDashlet: function(view) {
        if (this.meta.config) {
            // keep the display_columns and label fields in sync with the selected module when configuring a dashlet
            this.settings.on('change:module', function(model, moduleName) {
                let filterId = this._checkFilterPerModule(moduleName, model.get('filter_id'));
                let label = (filterId === 'assigned_to_me') ? 'TPL_DASHLET_MY_MODULE' : 'LBL_MODULE_NAME';
                model.set('label', app.lang.get(label, moduleName, {
                    module: app.lang.getModuleName(moduleName, {plural: true})
                }));

                // Re-initialize the filterpanel with the new module.
                this.dashModel.set('module', moduleName);
                this.dashModel.set('filter_id', filterId);
                this.layout.trigger('dashlet:filter:reinitialize');

                this._updateDisplayColumns();
                this._hideUnselectedColumns();
                this.updateLinkedFields(moduleName);
            }, this);
            this.settings.on('change:intelligent', function(model, intelligent) {
                let moduleName = this.settings.get('module') || this.context.get('module');
                this.updateLinkedFields(moduleName);
                this.setLinkedFieldVisibility('1', intelligent);
            }, this);
            this.on('render', function() {
                var isVisible = !_.isEmpty(this.settings.get('linked_fields')) ? '1' : '0';
                this.setLinkedFieldVisibility(isVisible, this.settings.get('intelligent'));
            }, this);
        }
        this._initializeSettings();
        this.metaFields = this._getColumnsForDisplay();

        if (this.settings.get('intelligent') == '1') {
            let link = this.settings.get('linked_fields');
            let module = this.settings.get('module');
            let parentModelId = (this.context && this.context.parent && this.context.parent.parent) ?
                this.context.parent.parent.get('modelId') :
                null;
            const currentContext = this.getCurrentContext();
            let model = (parentModelId && currentContext.get('targetLayout') === 'focus') ?
                app.data.createBean(this.context.parent.get('module'), {id: parentModelId}) :
                this.model || currentContext.get('model');
            let options = {
                link: {
                    name: link,
                    bean: model
                }
            };
            this.collection = app.data.createBeanCollection(module, null, options);
            this.collection.setOption('relate', true);
            this.context.set('collection', this.collection);
            this.context.set('link', link);
        } else {
            this.context.unset('link');
        }

        this.before('render', function() {
            if (!this.moduleIsAvailable) {
                this.$el.html(this._noAccessTemplate());
                return false;
            }
            if (!this.filterIsAccessible) {
                this._displayNoFilterAccess();
                return false;
            }
        });

        // the pivot point for the various dashlet paths
        if (this.meta.config) {
            this._configureDashlet();
            this.listenTo(this.layout, 'init', this._addFilterComponent);
            this.listenTo(this.layout.context, 'filter:add', this.updateDashletFilterAndSave);
            this.layout.before('dashletconfig:save', function() {
                this.saveDashletFilter();
                // NOTE: This prevents the drawer from closing prematurely.
                return false;
            }, this);

        } else if (this.moduleIsAvailable) {
            var filterId = this.settings.get('filter_id');
            if (!filterId || this.meta.preview) {
                this._displayDashlet();
                return;
            }

            var filters = app.data.createBeanCollection('Filters');
            filters.setModuleName(this.settings.get('module'));
            filters.load({
                success: _.bind(function() {
                    if (this.disposed) {
                        return;
                    }
                    var filter = filters.collection.get(filterId);
                    var filterDef = filter && filter.get('filter_definition');
                    // In case the filter assigned to the list-dashlet is NOT in the filters collection,
                    // as collection only contains certain number (= max_filters) of entries.
                    // Will make a separate api call to fetch the specified filter data.
                    if (!filterDef) {
                        var url = app.api.buildURL('Filters/' + filterId, null, null);
                        app.api.call('read', url, null, {
                            success: _.bind(this.triggerDashletSetup, this),
                            error: _.bind(this._displayNoFilterAccess, this)
                        });
                    } else if (_.isUndefined(filterDef)) {
                        this.filterIsAccessible = false;
                        this._displayNoFilterAccess();
                    } else {
                        this._displayDashlet(filterDef);
                    }
                }, this),
                error: _.bind(function() {
                    if (this.disposed) {
                        return;
                    }
                    this._displayDashlet();
                }, this)
            });
        }
    },

    /**
     * Display a message when dashlet filter is not accessible.
     */
    _displayNoFilterAccess: function() {
        var template = app.template.get(this.name + '.nofilteraccess');
        var noFilterAccessSupportUrl = null;
        if (!_.isUndefined(app.help) && _.isFunction(app.help.getMoreInfoHelpURL)) {
            noFilterAccessSupportUrl = app.help.getMoreInfoHelpURL('nofilter', 'listviewdashlet');
        }
        this.$el.html(template({noFilterAccessSupportUrl: noFilterAccessSupportUrl}));
        var listBottom = this.layout.getComponent('list-bottom');
        if (listBottom) {
            listBottom.hide();
        }
    },

    /**
     * @inheritdoc
     * Don't load data if dashlet filter is not accessible.
     */
    loadData: function(options) {
        if (!this.filterIsAccessible) {
            if (options && _.isFunction(options.complete)) {
                options.complete();
            }
            return;
        }
        this._super('loadData', [options]);
    },

    /**
     * Fetch the next pagination records.
     */
    showMoreRecords: function() {
        // Show alerts for this request
        this.getNextPagination();
    },

    /**
     * Returns a custom label for this dashlet.
     *
     * @return {string}
     */
    getLabel: function() {
        var module = this.settings.get('module') || this.context.get('module');
        var moduleName = app.lang.getModuleName(module, {plural: true});
        return app.lang.get(this.settings.get('label'), module, {module: moduleName});
    },

    /**
     * This function is invoked by the `dashletconfig:save` event. If the dashlet
     * we are saving is a dashable list, it initiates the save process for a new
     * filter on the appropriate module's list view, otherwise, it takes the
     * `currentFilterId` stored on the context, and saves it on the dashlet.
     *
     * @param {Bean} model The dashlet model.
     */
    saveDashletFilter: function() {
        // Accessing the dashableconfiguration context.
        var context = this.layout.context;

        if (context.editingFilter) {
            // We are editing/creating a new filter
            if (!context.editingFilter.get('name')) {
                context.editingFilter.set('name', app.lang.get('LBL_DASHLET') +
                    ': ' + app.lang.get(this.settings.get('label'), this.settings.get('module')));
            }
            // Triggers the save on `filter-rows` which then triggers
            // `filter:add` which then calls `updateDashletFilterAndSave`
            context.trigger('filter:create:save');
        } else {
            // We are saving a dashlet with a predefined filter
            var filterId = context.get('currentFilterId');
            var obj = {id: filterId};
            this.updateDashletFilterAndSave(obj);
        }
    },

    /**
     * This function is invoked by the `filter:add` event. It saves the
     * filter ID on the dashlet model prior to saving it, for later reference.
     *
     * @param {Bean} filterModel The saved filter model.
     */
    updateDashletFilterAndSave: function(filterModel) {
        // We need to save the filter ID on the dashlet model before saving
        // the dashlet.
        var id = filterModel.id || filterModel.get('id');
        this.settings.set('filter_id', id);
        this.dashModel.set('filter_id', id);

        var componentType = this.dashModel.get('componentType') || 'view';

        // Adding a new dashlet requires componentType to be set on the model.
        if (!this.dashModel.get('componentType')) {
            this.dashModel.set('componentType', componentType);
        }

        app.drawer.close(this.dashModel);
        // The filter collection is not shared amongst views and therefore
        // changes to this collection on different contexts (list views and
        // dashlets) need to be kept in sync.
        app.events.trigger('dashlet:filter:save', this.dashModel.get('module'));
    },

    /**
     * Certain dashlet settings can be defaulted.
     *
     * Builds the available module cache by way of the
     * {@link BaseDashablelistView#_setDefaultModule} call. The module is set
     * after "filter_id" because the value of "filter_id" could impact the value
     * of "label" when the label is set in response to the module change while
     * in configuration mode (see the "module:change" listener in
     * {@link BaseDashablelistView#initDashlet}).
     *
     * @private
     */
    _initializeSettings: function() {
        if (this.intelligent === '0') {
            _.each(this.dashletConfig.panels, function(panel) {
                panel.fields = panel.fields.filter(function(el) {return el.name !== 'intelligent';});
            }, this);
            this.settings.set('intelligent', '0');
            this.dashModel.set('intelligent', '0');
        } else {
            if (_.isUndefined(this.settings.get('intelligent'))) {
                this.settings.set('intelligent', this._defaultSettings.intelligent);
            }
        }
        this.setLinkedFieldVisibility('1', this.settings.get('intelligent'));
        if (!this.settings.get('limit')) {
            this.settings.set('limit', this._defaultSettings.limit);
        }
        if (!this.settings.get('filter_id')) {
            this.settings.set('filter_id', this._defaultSettings.filter_id);
        }
        if (_.isUndefined(this.settings.get('freeze_first_column'))) {
            this.settings.set('freeze_first_column', this._defaultSettings.freeze_first_column);
        }
        this._setDefaultModule();
        if (!this.settings.get('label')) {
            this.settings.set('label', 'LBL_MODULE_NAME');
        }
    },

    /**
     * Sets the default module when a module isn't defined in the dashlet's
     * view definition.
     *
     * If the module was defined but it is not in the list of available modules
     * in config mode, then the view's module will be used.
     * @private
     */
    _setDefaultModule: function() {
        var availableModules = _.keys(this._getAvailableModules());
        var module = this.settings.get('module') || this.context.get('module');

        if (_.contains(availableModules, module)) {
            this.settings.set('module', module);
        } else if (this.meta.config) {
            module = this.context.parent.get('module');
            if (_.contains(this.moduleBlacklist, module)) {
                module = _.first(availableModules);
                // On 'initialize' model is set to context's model - that model can have no access at all
                // and we'll result in 'no-access' template after render. So we change it to default model.
                this.model = app.data.createBean(module);
            }
            this.settings.set('module', module);
        } else {
            this.moduleIsAvailable = false;
        }
    },

    /**
     * When creating a dashlet by default all columns available will be shown.
     * By a flag set in metadata (selected) some column can be rendered hidden
     * and optionally selectable. Display only columns that are not excluded from
     * the initial list of columns. Changes made by the users should not be overwritten.
     */
    _hideUnselectedColumns: function() {
        var module = this.settings.get('module');
        var columns = this.settings.get('display_columns');
        _.each(this.getFieldMetaForView(this._getListMeta(module)), function(fieldDef) {
            if (_.contains(columns, fieldDef.name) && fieldDef.selected === false) {
                columns = _.without(columns, fieldDef.name);
            }
        });
        this.settings.set('display_columns', columns);
    },

    /**
     * Update the display_columns attribute based on the current module defined
     * in settings.
     *
     * This will mark, as selected, all fields in the module's list view
     * definition. Any existing options will be replaced with the new options
     * if the "display_columns" DOM field ({@link EnumField}) exists.
     *
     * @private
     */
    _updateDisplayColumns: function() {
        var availableColumns = this._getAvailableColumns();
        var columnsFieldName = 'display_columns';
        var columnsField = this.getField(columnsFieldName);
        if (columnsField) {
            columnsField.items = availableColumns;
        }
        this.settings.set(columnsFieldName, _.keys(availableColumns));
    },

    /**
     * Update options for `linked_fields` based on current selected module.
     * If there are no options field is hidden.
     *
     * @param {String} moduleName Name of selected module.
     */
    updateLinkedFields: function(moduleName) {
        var linked = this.getLinkedFields(moduleName);
        var displayColumn = this.getField('linked_fields');
        var intelligent = this.dashModel.get('intelligent');
        if (displayColumn) {
            displayColumn.items = linked;
            this.setLinkedFieldVisibility('1', intelligent);
        } else {
            this.setLinkedFieldVisibility('0', intelligent);
        }
        this.settings.set('linked_fields', _.keys(linked)[0]);
    },

    /**
     * Returns object with linked fields.
     *
     * @param {String} moduleName Name of module to find linked fields with.
     * @return {Object} Hash with linked fields labels.
     */
    getLinkedFields: function(moduleName) {
        const module = this.getCurrentContext().get('module') || this.layout.module;
        var fieldDefs = app.metadata.getModule(module).fields;
        var relates = _.filter(fieldDefs, function(field) {
            if (!_.isUndefined(field.type) && (field.type === 'link')) {
                if (app.data.getRelatedModule(module, field.name) === moduleName) {
                    return true;
                }
            }
            return false;
        }, this);
        var result = {};
        _.each(relates, function(field) {
            result[field.name] = app.lang.get(field.vname || field.name, [module, moduleName]);
        }, this);
        return result;
    },

    /**
     * Gets the fields metadata from a particular view's metadata.
     *
     * @param {Object} meta The view's metadata.
     * @return {Object[]} The fields metadata or an empty array.
     */
    getFieldMetaForView: function(meta) {
        meta = _.isObject(meta) ? meta : {};
        let metaFields = !_.isUndefined(meta.panels) ? _.flatten(_.pluck(meta.panels, 'fields')) : [];
        let trueFieldsCount = 3;
        metaFields = _.map(metaFields, function(field) {
            if (!_.isUndefined(field.selected)) {
                if (field.selected === true) {
                    trueFieldsCount--;
                }
                return field;
            }

            field.selected = false;
            if (trueFieldsCount !== 0) {
                field.selected = true;
                trueFieldsCount--;
            }
            return field;
        });
        return metaFields;
    },

    /**
     * @inheritdoc
     */
    getRowDomForModelId: function(id) {
        return this.$(`tr[data-id="${id}"]`);
    },

    /**
     * @inheritdoc
     */
    makeRowVisible: function($selected) {
        if (!$selected) {
            this.$el.scrollTop();
            return;
        }

        let rowTop = $selected.position().top;
        let rowHeight = $selected.height();
        let rowBottom = rowTop + rowHeight;
        let dashletTop = this.$el.scrollTop();
        let dashletHeight = this.$el.height();
        let dashletBottom = dashletTop + dashletHeight;

        if (rowBottom >= dashletBottom || rowTop <= dashletTop) {
            this.$el.scrollTop(rowTop);
        }
    },

    /**
     * ListView sort will close previews, but this is not needed for dashablelists
     * In fact, closing preview causes problem when previewing this list dashlet
     * from dashlet-select
     */
    sort: $.noop,

    /**
     * Perform any necessary setup before the user can configure the dashlet.
     *
     * Modifies the dashlet configuration panel metadata to allow it to be
     * dynamically primed prior to rendering.
     *
     * @private
     */
    _configureDashlet: function() {
        var availableModules = this._getAvailableModules();
        var availableColumns = this._getAvailableColumns();
        var relates = this.getLinkedFields(this.module);
        _.each(this.getFieldMetaForView(this.meta), function(field) {
            switch (field.name) {
                case 'module':
                    // load the list of available modules into the metadata
                    field.options = availableModules;
                    break;
                case 'display_columns':
                    // load the list of available columns into the metadata
                    field.options = availableColumns;
                    break;
                case 'linked_fields':
                    field.options = relates;
                    break;
            }
        });

        // From ConfigDrivenList Plugin
        this.filterConfigFieldsForDashlet();
    },

    /**
     * This function adds the `dashablelist-filter` component to the layout
     * (dashletconfiguration), if the component doesn't already exist.
     */
    _addFilterComponent: function() {
        var filterComponent = this.layout.getComponent('dashablelist-filter');
        if (filterComponent) {
            return;
        }

        this.layout.initComponents([{
            layout: 'dashablelist-filter'
        }]);
    },

    /**
     * This function get Displayed Portal Modules
     *
     * @private
     */
    _portalModulesDelimitation: function() {
        var url = app.api.buildURL('Administration/portalmodules');
        app.api.call('read', url, null, {
            success: _.bind(function(data) {
                app.metadata.portalModules = _.difference(data, this.moduleBlacklist);
            }, this)
        });
    },

    /**
     * Gets all of the modules the current user can see.
     *
     * This is used for populating the module select and list view columns
     * fields. Filters any modules that are blacklisted.
     *
     * @return {Object} {@link BaseDashablelistView#_availableModules}
     * @private
     */
    _getAvailableModules: function() {
        if (_.isEmpty(this._availableModules) || !_.isObject(this._availableModules)) {
            this._availableModules = {};
            var visibleModules = app.metadata.getModuleNames({filter: 'visible', access: 'read'});
            //Enable users module to show for Admin users
            if (app.acl.hasAccess('admin', 'Users')) {
                visibleModules.push('Users');
                if (_.contains(this.moduleBlacklist, 'Users')) {
                    this.moduleBlacklist = _.without(this.moduleBlacklist, 'Users');
                }
            } else if (!_.contains(this.moduleBlacklist, 'Users')) {
                this.moduleBlacklist.push('Users');
            }
            var allowedModules = _.difference(visibleModules, this.moduleBlacklist);

            const context = this.getCurrentContext();
            var contextModule = context.get('module');
            var contextLayout = context.get('layout');
            if (contextModule == 'Administration' && contextLayout == 'portaltheme-config') {
                this._portalModulesDelimitation();
                if (app.metadata.portalModules) {
                    allowedModules = _.intersection(allowedModules, app.metadata.portalModules);
                }
            }

            _.each(this.additionalModules, function(extraModules, module) {
                if (_.contains(allowedModules, module)) {
                    allowedModules = _.sortBy(_.union(allowedModules, extraModules), function(name) {return name;});
                }
            });
            _.each(allowedModules, function(module) {
                var hasListView = !_.isEmpty(this.getFieldMetaForView(app.metadata.getView(module, 'list')));
                if (hasListView) {
                    this._availableModules[module] = app.lang.getModuleName(module, {plural: true});
                }
            }, this);
        }
        return this._availableModules;
    },

    /**
     * Gets the correct list view metadata.
     *
     * Returns the correct module list metadata
     *
     * @param  {String} module
     * @return {Object}
     */
    _getListMeta: function(module) {
        return app.metadata.getView(module, 'list');
    },

    /**
     * Gets all of the fields from the list view metadata for the currently
     * chosen module.
     *
     * This is used for the populating the list view columns field and
     * displaying the list.
     *
     * @return {Object} {@link BaseDashablelistView#_availableColumns}
     * @private
     */
    _getAvailableColumns: function() {
        var columns = {};
        var module = this.settings.get('module');
        if (!module) {
            return columns;
        }

        _.each(this.getFieldMetaForView(this._getListMeta(module)), function(field) {
            columns[field.name] = app.lang.get(field.label || field.name, module);
        });

        return columns;
    },

    /**
     * Perform any necessary setup before displaying the dashlet.
     *
     * @param {Array} [filterDef] The filter definition array.
     * @private
     */
    _displayDashlet: function(filterDef) {
        // Get the columns that are to be displayed and update the panel metadata.
        var columns = this._getColumnsForDisplay();
        this.meta.panels = [{fields: columns}];

        this.context.set('skipFetch', false);
        this.context.set('limit', this.settings.get('limit'));
        this.context.set('fields', this.getFieldNames());

        if (filterDef) {
            this._applyFilterDef(filterDef);
            this.context.reloadData({
                recursive: false,
                error: _.bind(this._displayNoFilterAccess, this)
            });
        } else {
            var listBottom = this.layout.getComponent('list-bottom');
            if (listBottom) {
                listBottom.render();
            }
        }
        this._startAutoRefresh();
    },

    /**
     * Sets the filter definition on the context collection to retrieve records
     * for the list view.
     *
     * @param {Array} filterDef The filter definition array.
     * @private
     */
    _applyFilterDef: function(filterDef) {
        if (filterDef) {

            filterDef = _.isArray(filterDef) ? filterDef : [filterDef];
            /**
             * Filter fields that don't exist either on vardefs or search definition.
             *
             * Special fields (fields that start with `$`) like `$favorite` aren't
             * cleared.
             *
             * TODO move this to a plugin method when refactoring the code (see SC-2555)
             * TODO we should support cleanup on all levels (currently made on 1st
             * level only).
             */
            var specialField = /^\$/;
            var meta = app.metadata.getModule(this.module);
            filterDef = _.filter(filterDef, function(def) {
                var fieldName = _.keys(def).pop();
                return specialField.test(fieldName) || meta.fields[fieldName];
            }, this);

            this.context.get('collection').filterDef = filterDef;
        }
    },

    /**
     * Gets the columns chosen for display for this dashlet list.
     *
     * The display_columns setting might not have been defined when the dashlet
     * is being displayed from a metadata definition, like is the case for
     * preview and the default dashablelist's that are defined. All columns for
     * the selected module are shown in these cases.
     *
     * @return {Object[]} Array of objects defining the field metadata for
     *   each column.
     * @private
     */
    _getColumnsForDisplay: function() {
        var columns = [];
        var fields = this.getFieldMetaForView(this._getListMeta(this.settings.get('module')));
        var moduleMeta = app.metadata.getModule(this.module);
        if (!this.settings.get('display_columns')) {
            this._updateDisplayColumns();
            this._hideUnselectedColumns();
        }
        if (!this.settings.get('linked_fields')) {
            const context = this.getCurrentContext();
            const module = context.get('module') || this.model.module;
            this.updateLinkedFields(module);
        }
        _.each(this.settings.get('display_columns'), function(name) {
            var field = _.find(fields, function(field) {
                return field.name === name;
            }, this);
            // If we don't have metadata for a field, skip it to avoid
            // adding an empty column to the dashlet
            if (_.isUndefined(field)) {
                return;
            }
            // It's possible that a column is on the dashlet and not on the
            // main list view (thus was never patched by metadata-manager).
            // We need to fix up the columns in that case.
            // FIXME: This method should not be used as a public method (though
            // it's being used everywhere in the app) this should be reviewed
            // when SC-3607 gets in.
            field = field || app.metadata._patchFields(this.module, moduleMeta, [name]);

            // Handle setting of the sortable flag on the list. This will not
            // always be true
            var sortableFlag;
            var fieldDef = app.metadata.getModule(this.module).fields[field.name];

            // If the module's field def says nothing about the sortability, then
            // assume it's ok to sort
            if (_.isUndefined(fieldDef) || _.isUndefined(fieldDef.sortable)) {
                sortableFlag = true;
            } else {
                // Get what the field def says it is supposed to do
                sortableFlag = !!fieldDef.sortable;
            }

            var column = _.extend({sortable: sortableFlag}, field);

            columns.push(column);
        }, this);
        return columns;
    },

    /**
     * Starts the automatic refresh of the dashlet.
     *
     * @private
     */
    _startAutoRefresh: function() {
        var refreshRate = parseInt(this.settings.get('auto_refresh'), 10);
        if (refreshRate) {
            this._stopAutoRefresh();
            this._timerId = setInterval(_.bind(function() {
                this.context.resetLoadFlag();
                this.layout.loadData();
            }, this), refreshRate * 1000 * 60);
        }
    },

    /**
     * Cancels the automatic refresh of the dashlet.
     *
     * @private
     */
    _stopAutoRefresh: function() {
        if (this._timerId) {
            clearInterval(this._timerId);
        }
    },

    /**
     * @override
     * @private
     */
    _render: function() {
        if (!this.meta || !this.meta.config) {
            const parent = this._super('_render');

            this.createSnapshotSelectorLegend();

            return parent;
        }

        this.action = 'list';
        return this._super('_render');
    },

    /**
     * Checks if the user has access to the dashlet.
     *
     * @param {string} action The action to check access for, e.g., 'list', 'edit', etc.
     * @return {boolean} True if the user has access, false otherwise.
     */
    _hasAccess: function(action) {
        let module = this._getCurrentModule();
        return app.acl.hasAccess(action, module);
    },

    /**
     * Creates a snapshot selector legend for the dashlet.
     */
    createSnapshotSelectorLegend: function() {
        if (this.meta.config) {
            return;
        }

        if (!this.context || !this.context.get('module')) {
            return;
        }

        const module = this.context.get('module');

        if (!app.utils.isHistoricallyDeltaEnabled(module) ||
            !app.utils.getDeltaActiveFields(module).length) {
            return;
        }

        if (!this.meta || !this.meta.preview) {
            if (!this.snapshotSelector || this.snapshotSelector.disposed) {
                const activeTab = app.sideDrawer ? app.sideDrawer.getActiveTab() : null;

                let parentDeltaState = null;

                if (activeTab && activeTab.context && activeTab.context.deltaLastState) {
                    parentDeltaState = activeTab.context.deltaLastState;

                    if (this.context.get('model')) {
                        this.context.get('model').deltaLastState = parentDeltaState;
                    }
                }

                this.snapshotSelector = app.view.createView({
                    name: 'record-snapshot-selector',
                    context: this.context,
                    layout: this.layout,
                    layoutType: 'dashablelist',
                    cssChild: 'inline-block ml-[0.875rem]',
                    parentDeltaState: parentDeltaState,
                });
            }

            if (this.snapshotSelector && !$.contains(this.el, this.layout.el)) {
                const $dashlet = this.layout.$('[data-dashlet="dashlet"]');
                $dashlet.prepend(this.snapshotSelector.$el);

                this.snapshotSelector.render();
                this.snapshotSelector.delegateEvents();

                this.snapshotSelector.$el.toggleClass('py-[0.3125rem]', true);
                this.snapshotSelector.$el.toggleClass('border-b', true);
                this.snapshotSelector.$el.toggleClass('border-[--border-base]', true);
                this.snapshotSelector.$el.toggleClass('[&&&&&]:h-auto', true);

                $dashlet.toggleClass('flex',true);
                $dashlet.toggleClass('flex-col', true);

                this.$el.toggleClass('flex-1', true);
            }
        }
    },

    /**
     * Disposes the snapshot selector legend for the dashlet.
     */
    dismissSnapshotSelector: function() {
        if (this.snapshotSelector && _.isFunction(this.snapshotSelector.dispose)) {
            this.snapshotSelector.dispose();
            this.snapshotSelector = null;
        }
    },

    /**
     * @inheritdoc
     *
     * Calls {@link BaseDashablelistView#_stopAutoRefresh} so that the refresh will
     * not continue after the view is disposed.
     *
     * @private
     */
    _dispose: function() {
        this._stopAutoRefresh();

        // Unbind any events bound to the window (e.g., beforeunload)
        $(window).off('beforeunload.delete' + this.cid);

        // Unbind any DOM events attached to this.$el
        if (this.scrollContainer) {
            this.scrollContainer.off();
            this.scrollContainer = null;
        }

        // Remove listeners set on this view
        this.stopListening();

        // Null out references to models and collections
        this.toggledModels = null;
        this.rowFields = null;
        this.dismissSnapshotSelector();

        this._super('_dispose');
    }
})
