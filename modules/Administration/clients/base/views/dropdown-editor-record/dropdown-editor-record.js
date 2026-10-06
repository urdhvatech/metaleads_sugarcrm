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
 * @class View.Views.Base.AdministrationDropdownEditorRecordView
 * @alias SUGAR.App.view.views.BaseAdministrationDropdownEditorRecordView
 * @augments View.Views.Base.ConfigPanelView
 */
({
    extendsFrom: 'ConfigPanelView',

    /**
     * Store current open dropdown name
     */
    dropdown_name: '',

    /**
     * Store a dropdown style template
     */
    dropdownStyleTemplate: {
        backgroundColor: '',
        icon: {
            class: '',
            color: '',
        },
        text: {
            color: '',
            isBold: false,
            isItalic: false,
            isLineThrough: false,
            isUnderline: false,
        },
        colorway: {
            title: '',
            class: 'no_style',
        },
    },

    /**
     * Flag to indicate if the dropdown has classification data
     */
    hasClassificationData: false,

    /**
     * Flag to indicate if the dropdown has enable formatting
     */
    formatting: false,

    /**
     * Store the sort order of the dropdown items
     */
    sortOrder: '',

    /**
     * Store the field to order by
     */
    orderByField: 'dropdown_label',

    /**
     * Classification type
     */
    classificationType: '',

    /**
     * Classification default value
     */
    defaultClassification: '',

    /**
     * Flag to show / hide comparison language selector.
     */
    hideComparisonLanguage: true,

    /**
     * Cached models to compare with the current collection.
     */
    cachedModels: [],

    /**
     * Flag to indicate if the selected role is the "Base" one.
     */
    isBaseRole: true,

    /**
     * @inheritdoc
     */
    events: {
        'click a[name="add_new_dropdown_btn"]': 'addNewDropdownItem',
        'click a[name="dropdown_format"]': 'highlightedDropdownItem',
        'click a[name="dropdown_delete"]': 'deleteDropdownItem',
        'click a[name="sort_btn"]': 'sortDropdownItems',
        'click #togBtn': 'enableFormatting',
        'input input[name="dropdown_name"]': 'inputDropdownName',
        'input .search-group .search-input': 'inputSearch',
        'click .search-group .sicon-close': 'clearSearch',
        'change input[type=checkbox]': 'updateCheckboxes',
        'change [name=role_selection]': 'roleChange',
        'input input[name=dropdown_label]': 'changeDropdownLabel',
    },

    /**
     * @inheritdoc
     * @param options
     */
    initialize: function(options) {
        this._super('initialize', [options]);

        this.dropdown_name = options.context.get('dropdownName');
        this.isCreateMode = _.isUndefined(this.dropdown_name);

        this.metaFields = this._getMetaFields();
        this._currentUrl = Backbone.history.getFragment();

        this.model.set({
            dropdown_name: this.isCreateMode ? '' : this.dropdown_name,
            language_selection: app.lang.getLanguage(),
            role_selection: '0',
            comparison_language_selection: app.lang.getLanguage(),
            ootb: false,
        });

        // Set default values for dropdown classification
        this.collection = new Backbone.Collection();
        this.collection.reset();

        this.isCreateMode ? this.addNewDropdownItem() : this.fetchDropdownData();
        $('body').on('click', this.collapseFormattingStyle.bind(this));
        this.cachedModels = JSON.parse(JSON.stringify(this.collection.models));
    },

    /**
     * Collapse Formatting Style panel
     *
     * @param {jQuery.Event} evt
     */
    collapseFormattingStyle: function(evt) {
        const $target = $(evt.target);
        if (!$target.closest('.formatting-style').length) {
            $('.formatting-style').removeClass('expanded');
        }
    },

    /**
     * @inheritdoc
     */
    _render: function() {
        const fields = this._adjustModelDisplayFields();
        this.rebuildPanels(fields);

        this.tplRoles = this.tplLangs = (this.isCreateMode) ? 'disabled' : 'edit';
        this.visibleModels = _.filter(this.collection.models, (model) => model.get('hidden') !== true);

        this._super('_render');

        const focusedEl = $('input.required[value=""]', this.$el);
        focusedEl.first().focus();

        this.addDragAndDrop();
    },

    /**
     * Adding drag and drop behavior
     */
    addDragAndDrop: function() {
        const sortableItems = this.$('tbody');

        if (sortableItems.length) {
            _.each(sortableItems, function(sortableItem) {
                $(sortableItem).sortable({
                    // allow draggable items to be connected with other tbody elements
                    connectWith: 'tbody',
                    opacity: 1,
                    // allow drag to only go in Y axis direction
                    axis: 'y',
                    // the helper function to create a clone of the row being dragged
                    helper: _.bind(this._helper, this),
                    // the items to make sortable
                    items: 'tr.dropdown-editor-record-row',
                    // adds a slow animation when "dropping" a group, removing this causes the row
                    // to immediately snap into place wherever it's sorted
                    revert: true,
                    // the CSS class to apply to the placeholder underneath the helper clone the user is dragging
                    placeholder: 'ui-state-highlight',
                    // handler for when dragging starts
                    start: _.bind(this._onDragStart, this),
                    // handler for when dragging stops; the "drop" event
                    stop: _.bind(this._onDragStop, this),
                    cursor: 'move',
                    // Don't allow dragging to start from clicking in the actions menu
                    cancel: 'input, textarea, button, select, option, .dropdown-delete-btn',
                });
            }, this);
        }
    },

    /**
     * Event handler for the sortstart "drag" event
     *
     * @param {jQuery.Event} evt The jQuery sortstart event
     * @param {object} ui The jQuery Sortable UI Object
     * @private
     */
    _onDragStart: function(evt, ui) {
        const initialIndex = ui.item.index();
        ui.item.data('initialIndex', initialIndex);
    },

    /**
     * Event handler for the sortstop "drop" event
     *
     * @param {jQuery.Event} evt The jQuery sortstop event
     * @param {object} ui The jQuery Sortable UI Object
     * @private
     */
    _onDragStop: function(evt, ui) {
        const newIndex = ui.item.index();
        const oldIndex = ui.item.data('initialIndex');
        const newCollection = this.reorderCollection(oldIndex, newIndex, this.collection);
        this.collection.reset(newCollection);

        this.context.trigger('formatting-panel:state:changed', this.collection.at(newIndex));
    },

    /**
     * Function to create a clone of the row being dragged
     *
     * @param {jQuery.Event} evt The jQuery event
     * @param {object} ui The jQuery Sortable UI Object
     * @returns {jQuery} The jQuery object representing the helper clone
     * @private
     */
    _helper: function(evt, ui) {
        const listTD = ui.find('td');
        const uiHelper = ui.clone();
        const listHelperTD = uiHelper.find('td');

        _.each(listTD, (td, key) => {
            $(listHelperTD[key]).css('min-width', $(td).width());
        });

        return uiHelper;
    },

    /**
     * Reorders array
     *
     * @param {int} oldIndex
     * @param {int} newIndex
     * @param {Array} originalArray The original array
     * @returns {Array} The reordered array
     */
    reorderCollection: function(oldIndex, newIndex, originalArray) {
        const movedItem = originalArray.find((item, index) => index === oldIndex);
        const remainingItems = originalArray.filter((item, index) => index !== oldIndex);

        const reorderedItems = [
            ...remainingItems.slice(0, newIndex),
            movedItem,
            ...remainingItems.slice(newIndex),
        ];

        return reorderedItems;
    },

    /**
     * @inheritdoc
     */
    bindDataChange: function() {
        this._super('bindDataChange');
        if (this.isCreateMode) {
            this.listenTo(this.context, 'button:save_button:click', this.create);
        } else {
            this.listenTo(this.model, 'change:language_selection', this.languageSelectionChange);
            this.listenTo(this.model, 'change:comparison_language_selection', this.comparisonLanguageSelectionChange);
            this.listenTo(this.context, 'button:save_button:click', this.saveDropdownData);
            this.listenTo(this.context, 'restore:dropdown', this.restoreDropdownData);
        }

        this.listenTo(this.context, 'highlighted-dropdown-item:changed', this.changeDropdownItemHighlight);
        this.listenTo(this.context, 'formatting-style:changed', this.applyStyles);
        this.listenTo(this.context, 'formatting-style:removed', this.applyStyles);

        app.routing.before('route', this.beforeRouteChange, this);
    },

    /**
     * Applies actual styles and highlights the particular dropdown item
     */
    applyStyles: function(currID) {
        this.render();
        this.changeDropdownItemHighlight(currID);
    },

    /**
     * Enables or disables the formatting for the dropdown items
     *
     * @param event
     */
    enableFormatting: function(event) {
        if (!event) {
            return;
        }

        this.formatting = event.currentTarget.checked;
        // Toggle  the charts color and dropdowns color
        this.toggleBgColor();

        if (!this.formatting) {
            this.context.trigger('formatting-panel:close');
        }

        this.render();
    },

    /**
     * Processing the field change event and displaying its value in the Formatting panel
     *
     * @param event
     */
    changeDropdownLabel: function(event) {
        const rowId = this.$(event.currentTarget).closest('.dropdown-editor-record-row').attr('id');

        this.context.trigger('formatting-panel:dropdown_label:changed', rowId, event.currentTarget.value);
    },

    /**
     * Handles the role change event.
     *
     * @param newRoleEvt
     */
    roleChange: function(newRoleEvt) {
        this.context.trigger('formatting-panel:close');

        if (this._dataHasChanges()) {
            app.alert.show('dropdown_editor_delete', {
                level: 'confirmation',
                messages: app.lang.get('LBL_DROPDOWN_UNSAVED_ROLE_RESTORE_WARNING', this.module),
                autoClose: false,
                templateOptions: {
                    alertClass: 'unsaved-role-change-alert alert-warning',
                    alertIcon: 'sicon-warning-line-lg',
                },
                confirm: {
                    label: app.lang.get('LBL_DROPDOWN_UNSAVED_ROLE_CHANGE_CONFIRM', this.module),
                },
                cancel: {
                    label: app.lang.get('LBL_DROPDOWN_UNSAVED_ROLE_CHANGE_CANCEL', this.module),
                },
                onConfirm: () => {
                    this.fetchDropdownData();
                },
                onCancel: () => {
                    this.model.set('role_selection', newRoleEvt.removed.id);
                    app.alert.dismiss('dropdown_editor_delete');
                },
            });
        } else {
            this.fetchDropdownData();
        }

        this.isBaseRole = (this.model.get('role_selection') === '0');
    },

    /**
     * Checks if the role has changes compared to the cached models.
     *
     * @returns {boolean}
     * @private
     */
    _dataHasChanges: function() {
        return JSON.stringify(this.collection.models) !== JSON.stringify(this.cachedModels);
    },

    /**
     * Handles the language selection change event.
     */
    languageSelectionChange: function(newLangEvt) {
        this.context.trigger('formatting-panel:close');
        this.hideComparisonLanguage = this.model.get('language_selection') === app.lang.getLanguage();

        if (this._dataHasChanges()) {
            app.alert.show('dropdown_editor_delete', {
                level: 'confirmation',
                messages: app.lang.get('LBL_DROPDOWN_UNSAVED_LANG_RESTORE_WARNING', this.module),
                autoClose: false,
                templateOptions: {
                    alertClass: 'unsaved-lang-change-alert alert-warning',
                    alertIcon: 'sicon-warning-line-lg',
                },
                confirm: {
                    label: app.lang.get('LBL_DROPDOWN_UNSAVED_LANGUAGE_CHANGE_CONFIRM', this.module),
                },
                cancel: {
                    label: app.lang.get('LBL_DROPDOWN_UNSAVED_LANGUAGE_CHANGE_CANCEL', this.module),
                },
                onConfirm: () => {
                    this.model.set('language_selection', newLangEvt.get('language_selection'));
                    this.fetchDropdownData();
                },
                onCancel: () => {
                    this.model.set('language_selection', newLangEvt._previousAttributes.language_selection);
                    app.alert.dismiss('dropdown_editor_delete');
                },
            });
        } else {
            this.fetchDropdownData();
        }
    },

    /**
     * Handles the comparison language selection change event.
     */
    comparisonLanguageSelectionChange: function(newCompLangEvt) {
        this.context.trigger('formatting-panel:close');
        if (this._dataHasChanges()) {
            app.alert.show('dropdown_editor_delete', {
                level: 'confirmation',
                messages: app.lang.get('LBL_DROPDOWN_UNSAVED_LANG_RESTORE_WARNING', this.module),
                autoClose: false,
                templateOptions: {
                    alertClass: 'unsaved-lang-change-alert alert-warning',
                    alertIcon: 'sicon-warning-line-lg',
                },
                confirm: {
                    label: app.lang.get('LBL_DROPDOWN_UNSAVED_LANGUAGE_CHANGE_CONFIRM', this.module),
                },
                cancel: {
                    label: app.lang.get('LBL_DROPDOWN_UNSAVED_LANGUAGE_CHANGE_CANCEL', this.module),
                },
                onConfirm: () => {
                    this.model.set(
                        'comparison_language_selection',
                        newCompLangEvt.get('comparison_language_selection',
                        ));
                    this.fetchDropdownData();
                },
                onCancel: () => {
                    this.model.set(
                        'comparison_language_selection',
                        newCompLangEvt._previousAttributes.comparison_language_selection,
                    );
                    app.alert.dismiss('dropdown_editor_delete');
                },
            });
        } else {
            this.fetchDropdownData();
        }
    },

    /**
     * @inheritdoc
     * @returns {boolean}
     */
    beforeRouteChange: function() {
        if (this._dataHasChanges()) {
            const targetUrl = Backbone.history.getFragment();
            // Replace the url hash back to the current staying page
            app.router.navigate(this._currentUrl, {trigger: false, replace: true});
            app.alert.show('leave_confirmation', {
                level: 'confirmation',
                messages: app.lang.get('LBL_WARN_UNSAVED_CHANGES', this.module),
                onConfirm: _.bind(function() {
                    app.routing.offBefore('route', this.beforeRouteChange, this);
                    this.collection.reset();
                    if (app.drawer.count()) {
                        app.drawer.close();
                    }
                    app.router.navigate(targetUrl, {trigger: true});
                }, this),
                onCancel: $.noop,
            });
            return false;
        }
        return true;
    },

    /**
     * Sets the classification options for the dropdown classification field
     * based on the classifications data received from the server.
     *
     * @param {object} classifications - The classifications data received from the server.
     */
    setClassificationOptions: function(classifications) {
        const classificationField = this.metaFields.dropdown_classification;

        if (classificationField) {
            classificationField.options = _.first(_.values(classifications)).options;
        }
    },

    /**
     * Adjusts fields / columns to be displayed based on current selected role or
     * current opened dropdown
     *
     * @returns {Array} The adjusted fields to be displayed
     */
    _adjustModelDisplayFields: function() {
        let fields = JSON.parse(JSON.stringify(this.metaFields));

        // hide `classification` field (for most of doms, except 'sales_stage_dom' at the moment)
        if (!this.hasClassificationData) {
            fields = _.filter(fields, (field) => field.name !== 'dropdown_classification');
        }

        if (this.isBaseRole) {
            // hide `role` (checkboxes) field for "Base Role"
            fields = _.filter(fields, (field) => field.name !== 'dropdown_role');

            // add editability to `dropdown_key` field in "Create Mode"
            if (this.isCreateMode) {
                fields = _.map(fields, (field) => {
                    if (field.name === 'dropdown_key') {
                        field.template = 'edit';
                    }
                    return field;
                });
            }
        }

        // hide `label_comparison` if selected language is different from the current app language
        if (this.hideComparisonLanguage) {
            fields = _.filter(fields, (field) => field.name !== 'dropdown_label_comparison');
        }

        // remove fields editability for Not "Base Role"
        if (!this.isBaseRole) {
            fields = _.map(fields, (field) => {
                if (field.name !== 'dropdown_role') {
                    field.template = 'detail';
                }
                return field;
            });
        }

        this.setSortDirection(fields);

        return fields;
    },

    /**
     * @param fields
     */
    rebuildPanels: function(fields) {
        const panel = this._getPanelById('dropdown-editor-header');
        if (!panel) {
            return;
        }

        panel.fields = fields;
    },

    /**
     * @param id
     * @returns {object|null} The panel definition or null if not found
     */
    _getPanelById: function(id) {
        const panel = _.find(this.meta.panels, function(panel) {
            return panel.id === id;
        }, this);

        if (!panel) {
            return null;
        }

        return panel;
    },

    /**
     * Gets the set of field definitions on the view from metadata
     *
     * @returns {object} the map of {field name} => {field def} from meta fields
     * @private
     */
    _getMetaFields: function() {
        const metaFields = [];
        const panel = this._getPanelById('dropdown-editor-header');

        if (!panel) {
            return metaFields;
        }

        _.each(panel.fields, function(panelField) {
            metaFields.push(panelField);
            if (panelField.fields) {
                _.each(panelField.fields, function(subfield) {
                    metaFields.push(subfield);
                }, this);
            }
        }, this);

        return _.object(_.pluck(metaFields, 'name'), metaFields);
    },

    /**
     * Returns the required fields based on metadata.
     * @returns {*}
     * @private
     */
    _getRequiredValidationFields: function() {
        const requiredMetaFields = _.filter(_.keys(this.metaFields), (key) =>
            this.metaFields[key].required === true,
        );

        const requiredFields = _.chain(this.meta)
            .values()
            .flatten()
            .filter({required: true})
            .map('name')
            .value();

        return _.union(requiredFields, requiredMetaFields);
    },

    /**
     * Extensible function that returns the module/config URL for save
     *
     * @returns {string} The Config Save URL
     * @protected
     *
     */
    _getConfigUrl: function(options) {
        return app.api.buildURL(this.module, `dropdownEditor/${this.model.get('dropdown_name')}`, null, options);
    },

    /**
     * Fetch DropdownData
     */
    fetchDropdownData: function() {
        const queryParams = {
            'language': this.model.get('language_selection'),
            'role': this.model.get('role_selection'),
            'comparisonLanguage': this.model.get('comparison_language_selection'),
        };

        const options = {
            success: _.bind(function(data) {
                const newModels = [];
                const dropdownData = data.dropdownData;

                if (_.isEmpty(data.dropdownData)) {
                    return;
                }

                this.model.set('ootb', data.ootb);
                this.context.trigger('ootb:changed');
                this.formatting = data.formatting || false;

                if (!_.isEmpty(data.classifications)) {
                    this.hasClassificationData = true;
                    this.setClassificationOptions(data.classifications);
                    this.classificationType = _.first(_.keys(data.classifications));
                    this.defaultClassification = data.classifications[this.classificationType].default;
                }

                // Build a clean key order: dedupe dropdownOrder and append missing keys
                const rawOrder = (_.isArray(data.dropdownOrder) && data.dropdownOrder.length) ?
                    data.dropdownOrder : _.keys(dropdownData);
                const seen = {};
                const orderedKeys = [];
                _.each(rawOrder, function(k) {
                    if (!_.has(seen, k)) {
                        seen[k] = true;
                        orderedKeys.push(k);
                    }
                });
                _.each(_.keys(dropdownData), function(k) {
                    if (!_.has(seen, k)) {
                        seen[k] = true;
                        orderedKeys.push(k);
                    }
                });

                orderedKeys.forEach(function(key) {
                    const item = dropdownData[key];
                    if (!item) {
                        return;
                    }
                    const label = item.label !== '' ? item.label : '-blank-';
                    const value = item.value !== '' ? item.value : '-blank-';

                    let model = {
                        dropdown_label: label,
                        dropdown_label_comparison: item.comparisonLabel ? item.comparisonLabel : label,
                        dropdown_key: value,
                        dropdown_role: item.role || false,
                        dropdown_classification: item.classification ? _.first(_.values(item.classification)) : '',
                        dropdownStyle: item.style,
                    };

                    model = new Backbone.Model(model);
                    model.fields = app.utils.deepCopy(this.metaFields);
                    model._syncedAttributes = app.utils.deepCopy(model.attributes);
                    newModels.push(model);
                }, this);

                this.cachedModels = JSON.parse(JSON.stringify(newModels));
                this.collection.reset(newModels);
                this.collection.comparator = function(model) {
                    return model.get(this.orderByField);
                };
                this.sortOrder = '';
                this.render();
                this.updateCheckboxes();
            }, this),
        };

        app.api.call('read', this._getConfigUrl(queryParams), [], options);
    },

    /**
     * Restore the default data of thr dropdown list.
     */
    restoreDropdownData: function() {
        const options = {
            success: _.bind(function() {
                this.collection.reset();
            }, this),
            complete: _.bind(function() {
                this.callOptionsSuccess(this.model.get('dropdown_name'), 'restored');
            }, this),
        };

        app.alert.show('dropdown-editor-restore', {
            level: 'process',
            title: app.lang.get('LBL_LOADING'),
            autoClose: false,
        });

        app.api.call(
            'update',
            app.api.buildURL(this.module, `dropdownEditor/restore/${this.model.get('dropdown_name')}`),
            [],
            options,
        );
    },

    /**
     * Create Dropdown editor DOM.
     */
    create: function() {
        if (!this.validateCollection()) {
            return;
        }

        this.dropdown_name = $('[name=dropdown_name]', this.$el).val().trim();

        if (!this.isValidName(this.dropdown_name)) {
            app.alert.show('dropdown_validate_name', {
                level: 'error',
                autoClose: false,
                messages: app.lang.get('LBL_DROPDOWN_VALIDATE_NAME', this.module),
            });

            return;
        }

        const options = {
            success: _.bind(function() {
                this.collection.reset();
                this.callOptionsSuccess(this.model.get('dropdown_name'), 'created');
            }, this),
            complete: _.bind(function() {
                app.alert.dismiss('dropdown-editor-save');
            }, this),
            error: _.bind(function(data) {
                if (data.code === 'edit_conflict') {
                    app.alert.show('dropdown_name_already_exists', {
                        level: 'error',
                        autoClose: false,
                        messages: app.lang.get('LBL_DROPDOWN_NAME_ALREADY_EXISTS', this.module),
                    });
                }
            }, this),
        };
        app.alert.show('dropdown-editor-save', {
            level: 'process',
            title: app.lang.get('LBL_SAVING'),
            autoClose: false,
        });

        const data = this._getSaveDropdownDataAttributes();
        data.dropdown_name = this.model.get('dropdown_name');

        const url = app.api.buildURL(this.module, 'dropdownEditor/create');
        app.api.call('create', url, data, options);
    },

    /**
     * Validates dropdown name (ASCII letter start; letters/digits/underscore; 1–64 chars)
     *
     * @param {string} str The string to validate
     * @returns {boolean} Returns true if the string is a valid dropdown name, false otherwise
     */
    isValidName: function(str) {
        return /^[A-Za-z][A-Za-z0-9_]{0,63}$/.test(str);
    },

    /**
     * Validates dropdown item name (letters/digits/underscore; 0–63 chars)
     *
     * @param str
     * @returns {boolean}
     */
    isValidDropdownItemName: function(str) {
        return /^[A-Za-z0-9_\s]{0,63}$/.test(str);
    },

    /**
     * Validates each collection models required fields
     * @returns {boolean} True if all models are valid, false otherwise
     */
    validateCollection: function() {
        let isValid = true;

        const $inputs = $('input', this.$el);
        _.each($inputs, function(input) {
            $(input).parent().removeClass('error');
        });

        const verificationModels = [this.model, ...this.collection.models];

        const requiredFields = this._getRequiredValidationFields();

        _.each(verificationModels, function(model) {
            isValid = this.validateModel(model, requiredFields) && isValid;
        }, this);

        if (!isValid) {
            app.alert.show('dropdown-validate-warning', {
                level: 'error',
                title: app.lang.get('LBL_ERROR'),
                messages: 'ERR_RESOLVE_ERRORS',
                autoClose: true,
            });
        }

        if (isValid && !this.validateDropdownItems()) {
            isValid = false;

            app.alert.show('dropdown-validate-warning', {
                level: 'error',
                title: app.lang.get('LBL_ERROR'),
                messages: 'LBL_DROPDOWN_ITEMS_VALIDATION_MSG',
                autoClose: true,
                autoCloseDelay: '5000',
            });
        }

        return isValid;
    },

    /**
     * Validates the fields of a given model. Applies error styling to the
     * field if errors are encountered
     *
     * @param {Backbone.Model} model The model that was changed
     * @param {Array} requiredFields The list of required fields
     * @returns {boolean} True if the model is valid, false otherwise
     */
    validateModel: function(model, requiredFields) {
        let isValid = true;

        _.each(requiredFields, (field) => {
            const value = model.get(field);
            if (_.isUndefined(value) || !_.isString(value) || value.trim() !== '') {
                return;
            }
            isValid = false;

            const $inputs = $(`input[name=${field}]`, this.$el);
            _.each($inputs, (input) => {
                if (!$(input).val().trim()) {
                    $(input).parent().addClass('error');
                }
            });
        }, this);

        return isValid;
    },

    /**
     * Validate Dropdown items keys
     *
     * @returns {boolean} True if all dropdown items are valid, false otherwise
     */
    validateDropdownItems: function() {
        return this.collection.models.reduce((isValid, model) =>
            this.validateDropdownModel(model) && isValid, true);
    },

    /**
     * Validate a single Dropdown model item
     *
     * @param model {Backbone.Model} The model to validate
     * @returns {boolean}
     */
    validateDropdownModel: function(model) {
        const field = this.getField('dropdown_key', model);
        let isValid = true;

        // Validate only new dropdown items
        if (field?.name === 'dropdown_key' && model.get('is_new') === true) {
            const dropdownKey = model.get('dropdown_key');

            if (dropdownKey === '' || dropdownKey === '-blank-') {
                isValid = true;
            } else {
                isValid = dropdownKey ? this.isValidDropdownItemName(dropdownKey) : false;
            }

            field.$el.toggleClass('error', !isValid);
        }

        return isValid;
    },

    /**
     * Sort Dropdown items
     */
    sortDropdownItems: function() {
        this.context.trigger('formatting-panel:close');
        this.sortOrder = this.sortOrder === 'desc' ? 'asc' : 'desc';
        // takes lowercase into account for sorting
        const getSortableLabel = (model) => {
            const label = model.get('dropdown_label');
            return label ? label.toLowerCase() : '';
        };

        let sorted = _.sortBy(this.collection.models, getSortableLabel);
        if (this.sortOrder === 'desc') {
            sorted = sorted.reverse();
        }

        this.collection.models = sorted;

        this.render();
        this.updateCheckboxes();
    },

    /**
     * Set the sort direction for the dropdown items
     */
    setSortDirection: function(fields) {
        const list = fields || this.fields || [];

        const field = _.find(list, (field) => field.name === this.orderByField);

        if (field) {
            if (this.sortOrder === 'desc') {
                field.sortDirection = 'arrow-down';
            } else if (this.sortOrder === 'asc') {
                field.sortDirection = 'arrow-up';
            } else if (field && field.sortDirection) {
                delete field.sortDirection;
            }
        }
    },

    /**
     * Save the dropdown data.
     *
     */
    saveDropdownData: function() {
        if (!this.validateCollection()) {
            return;
        }

        _.each(this.collection.models, (model) => {
            model.unset('is_new', {
                silent: true,
            });
        });

        const dropdownData = this._getSaveDropdownDataAttributes();

        if (!dropdownData) {
            return;
        }

        const options = {
            success: _.bind(function() {
                this.collection.reset();
                this.cachedModels = JSON.parse(JSON.stringify(this.collection.models));
                this.callOptionsSuccess(this.model.get('dropdown_name'), 'updated');
            }, this),
        };
        app.alert.show('dropdown-editor-save', {
            level: 'process',
            title: app.lang.get('LBL_SAVING'),
            autoClose: false,
        });

        const url = app.api.buildURL(this.module, `dropdownEditor/${this.model.get('dropdown_name')}`);
        app.api.call('update', url, dropdownData, options);
    },

    /**
     * Output message and redirect to Dropdown list page
     *
     * @param {string} name Dropdown name.
     * @param {string} action Action on dropdown (created / updated)
     */
    callOptionsSuccess: function(name, action) {
        app.alert.dismissAll();

        const msg = app.lang.get('LBL_DROPDOWN', this.module) + ` "${name}" ` +
            app.lang.get('LBL_DROPDOWN_DOM_HAVE_BEEN_' + action.toUpperCase(), this.module);
        app.alert.show('dom_set', {
            level: 'success',
            messages: app.lang.get(msg),
            autoClose: true,
            autoCloseDelay: 5000,
        });

        let type = null;

        try {
            const prevs = JSON.parse(sessionStorage.getItem('previous_page_loads'));
            if (prevs) {
                const referrerUrl = prevs[0].url;
                type = new URL(referrerUrl).searchParams.get('type');
            }
        } catch (e) {
            app.logger.warn('Failed to parse or validate previous page URL from sessionStorage', e);
        }

        if (app.drawer.count()) {
            app.drawer.close();

            if (action !== 'created') {
                return;
            }
        }

        app.events.trigger('dropdowns:dropdown:created', name);

        if (type === 'dropdowns') {
            this.cachedModels = [];
            const route = app.bwc.buildRoute('ModuleBuilder', null, 'index', {
                type: 'dropdowns',
            });

            app.router.navigate(route, {
                trigger: true,
            });
        }
    },

    /**
     * Returns the model attributes for save
     *
     * @returns {object} The DropdownData Save attributes object
     * @protected
     */
    _getSaveDropdownDataAttributes: function() {
        // Preserve insertion order (including numeric-like keys) while sending an object by
        // including an explicit dropdownOrder array for the server to follow.
        const attributes = {};
        const order = [];
        const seenKeys = new Set();
        const classificationType = this.classificationType;
        let hasDuplicate = false;
        let blankError = false;
        this.collection.models.forEach((model) => {
            const dropdownKey = model.get('dropdown_key');
            const dropdownLabel = model.get('dropdown_label');
            const keyItem = dropdownKey === '-blank-' ? '' : dropdownKey;
            const classification = {};
            classification[classificationType] = model.get('dropdown_classification') === '-blank-' ?
                '' : model.get('dropdown_classification');

            if (seenKeys.has(keyItem)) {
                hasDuplicate = true;

                // Highlight duplicate key field
                const field = this.getField('dropdown_key', model);
                field?.$el?.toggleClass('error', true);
            } else if (!_.isEmpty(dropdownKey) && _.isEmpty(dropdownLabel)) {
                blankError = true;
            } else {
                seenKeys.add(keyItem);
                const style = model.get('dropdownStyle') || {};
                const icon = (style && style.icon) || {};
                const text = (style && style.text) || {};
                const colorway = (style && style.colorway) || {};
                const item = {
                    label: dropdownLabel === '-blank-' ? '' : dropdownLabel,
                    value: keyItem,
                    role: model.get('dropdown_role'),
                    classification: classification,
                    comparisonLabel: keyItem,
                    style: {
                        backgroundColor: style.backgroundColor,
                        prevBgColor: style.prevBgColor,
                        icon: {
                            'class': icon.class,
                            'color': icon.color,
                        },
                        text: {
                            color: text.color,
                            isBold: text.isBold,
                            isItalic: text.isItalic,
                            isLineThrough: text.isLineThrough,
                            isUnderline: text.isUnderline,
                        },
                        colorway: {
                            'title': colorway.title,
                            'class': colorway.class,
                        },
                    },
                };
                attributes[keyItem] = item;
                order.push(keyItem);
            }
        });

        if (hasDuplicate) {
            app.alert.show('dropdown-editor-duplicate-keys', {
                level: 'error',
                title: app.lang.get('LBL_ERROR'),
                messages: app.lang.get('LBL_DROPDOWN_KEY_EXISTS', this.module),
                autoClose: false,
            });
            return false;
        }

        if (blankError) {
            app.alert.show('dropdown-editor-blank-label', {
                level: 'error',
                title: app.lang.get('LBL_ERROR'),
                messages: app.lang.get('LBL_DROPDOWN_BLANK_WARNING', this.module),
                autoClose: false,
            });
            return false;
        }

        return {
            dropdownData: attributes,
            dropdownOrder: order,
            language: this.model.get('language_selection'),
            role: this.model.get('role_selection'),
            comparisonLanguage: this.model.get('comparison_language_selection'),
            formatting: this.formatting || false,
        };
    },

    /**
     * Handle click and add new dropdown item to the list
     */
    addNewDropdownItem: function() {
        this.context.trigger('formatting-panel:close');
        this.sortOrder = '';

        this.dropdownStyleTemplate.colorway.title = app.lang.get('LBL_DROPDOWN_NO_STYLE', this.module);

        const newModel = new Backbone.Model({
            dropdown_label: '',
            dropdown_key: '',
            dropdown_role: true,
            is_new: true,
            dropdown_classification: this.defaultClassification || false,
            dropdownStyle: JSON.parse(JSON.stringify(this.dropdownStyleTemplate)),
        });

        this.collection.add(newModel);

        this.render();
        this.updateCheckboxes();

        this.$el.find('input[name="dropdown_key"]').last().focus();
    },

    /**
     * Highlights a dropdown item
     *
     * @param event
     */
    highlightedDropdownItem: function(event) {
        if (!event) {
            return;
        }

        this.$('.dropdown-editor-record-row').removeClass('active');
        this.$(event.currentTarget).closest('.dropdown-editor-record-row').addClass('active');
    },

    /**
     * Highlights the particular dropdown item
     *
     * @param currID {string} The ID of the current dropdown item
     */
    changeDropdownItemHighlight: function(currID) {
        if (!currID) {
            return;
        }

        this.$('.dropdown-editor-record-row').removeClass('active');
        this.$('#' + currID).addClass('active');
    },

    /**
     * Delete icon row event handler
     *
     * @param evt
     */
    deleteDropdownItem: function(evt) {
        app.alert.show('dropdown_editor_delete', {
            level: 'confirmation',
            messages: app.lang.get('LBL_DROPDOWN_REMOVE_CONFIRM', this.module),
            autoClose: false,
            onConfirm: () => {
                this._deleteDropdownItem(evt);
            },
        });
    },

    /**
     *
     * Delete dropdown item from the list
     *
     * @param evt
     * @private
     */
    _deleteDropdownItem: function(evt) {
        this.context.trigger('formatting-panel:close');
        const $row = this.$(evt.currentTarget).closest('.dropdown-editor-record-row');
        const rowId = $row.attr('id');

        const filteredModels = _.filter(this.collection.models, (m) => m.cid !== rowId);

        this.collection.reset(filteredModels);

        this.render();
        this.updateCheckboxes();
    },

    /**
     * The entered DOM name should be applied by next render.
     */
    inputDropdownName: function(evt) {
        this.dropdown_name = $(evt.currentTarget).val().trim();
        this.model.set('dropdown_name', this.dropdown_name);
    },

    /**
     * Search for items by fields: key and label.
     */
    inputSearch: function() {
        this.context.trigger('formatting-panel:close');
        this.needle = $('.search-input', this.$el).val().toLowerCase();
        this.collection.models.map((model) => {
            const key = model.get('dropdown_key').toLowerCase();
            const label = model.get('dropdown_label').toLowerCase();
            model.set('hidden', !key.includes(this.needle) && !label.includes(this.needle));
        }, this);

        const hiddenModels = this.collection.models.filter((model) => model.get('hidden'));
        this.emptySearchResult = (hiddenModels.length === this.collection.models.length);
        this.render();

        const searchInput = $('.search-input', this.$el);
        searchInput.focus();
        searchInput[0].setSelectionRange(this.needle.length, this.needle.length);

        const isNeedleEmpty = _.isEmpty(this.needle);
        $('.sicon-search', searchInput.parent()).toggleClass('hidden', !isNeedleEmpty);
        $('.sicon-close', searchInput.parent()).toggleClass('hidden', isNeedleEmpty);
        this.updateCheckboxes();
    },

    /**
     * Clear search result.
     *
     * @param {Event} evt
     */
    clearSearch: function(evt) {
        $(evt.currentTarget)
            .closest('.search-group')
            .find('.search-input')
            .val('')
            .trigger('input');
    },

    /**
     * Update a state for all checkboxes.
     *
     * @param event|null
     */
    updateCheckboxes: function(event = null) {
        if (event && $(event.target).hasClass('toggle_all')) {
            const isChecked = $(event.target).prop('checked');
            _.each(this.collection.models, (model) => model.set('dropdown_role', isChecked));
        } else {
            const checkboxes = this.$('tbody input[type=checkbox]');
            const isChecked = _.every(checkboxes, (checkbox) => $(checkbox).is(':checked'));
            this.$('input.toggle_all').prop('checked', checkboxes.length ? isChecked : false);
        }
    },

    /**
     * Toggle  the charts color system and dropdowns
     *
     */
    toggleBgColor: function() {
        this.collection.models.map((model) => {
            const style = model.get('dropdownStyle');
            const bgColor = style.backgroundColor;
            style.backgroundColor = style.prevBgColor || style.backgroundColor;
            style.prevBgColor = bgColor;
            model.set('dropdownStyle', style);
        }, this);
    },

    /**
     * @inheritdoc
     */
    _dispose: function() {
        app.routing.offBefore('route', this.beforeRouteChange, this);
        $('body').off('click');
        this.stopListening();
        this._super('_dispose');
    },
})
