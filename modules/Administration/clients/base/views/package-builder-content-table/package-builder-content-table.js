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
 * @class View.Views.Base.AdministrationPackageBuilderContentTableView
 * @alias SUGAR.App.view.views.BaseAdministrationPackageBuilderContentTableView
 * @extends View.View
 */
({
    /**
     * Title
     */
    title: '',

    /**
     * Headers
     */
    headers: [],

    /**
     * Header labels
     */
    headerLabels: [],

    /**
     * Entries
     */
    entries: [],

    /**
     * Has content
     */
    hasContent: false,

    /**
     * Sorting data
     */
    sortingData: false,

    /**
     * Checked rows
     */
    checkedRows: {},

    /**
     * Search term
     */
    searchTerm: '',

    /**
     * All header labels
     */
    allHeaderLabels: {
        dashboards: {
            'id': 'LBL_PACKAGE_BUILDER_ID',
            'name': 'LBL_PACKAGE_BUILDER_NAME',
            'dashboard_module': 'LBL_PACKAGE_BUILDER_DASHBOARD_MODULE',
            'view_name': 'LBL_PACKAGE_BUILDER_VIEW',
            'team_id': 'LBL_PACKAGE_BUILDER_TEAM',
            'default_dashboard': 'LBL_PACKAGE_BUILDER_DEFAULT_DASHBOARD',
            'assigned_user_id': 'LBL_PACKAGE_BUILDER_ASSIGNED_USER',
            'date_modified': 'LBL_PACKAGE_BUILDER_DATE_MODIFIED',
        },
        fields: {
            'Name': 'LBL_PACKAGE_BUILDER_NAME',
            'Type': 'LBL_PACKAGE_BUILDER_TYPE',
            'Custom Module': 'LBL_PACKAGE_BUILDER_CUSTOM_MODULE',
            'Date Modified': 'LBL_PACKAGE_BUILDER_DATE_MODIFIED',
        },
        layouts: {
            'Module': 'LBL_PACKAGE_BUILDER_MODULE',
            'Client': 'LBL_PACKAGE_BUILDER_CLIENT',
            'View': 'LBL_PACKAGE_BUILDER_VIEW',
        },
        search_layouts: {
            'Module': 'LBL_PACKAGE_BUILDER_MODULE',
            'Client': 'LBL_PACKAGE_BUILDER_CLIENT',
            'Filters': 'LBL_PACKAGE_BUILDER_FILTERS',
        },
        dropdowns: {
            'Dropdown Name': 'LBL_PACKAGE_BUILDER_DROPDOWN_NAME',
        },
        relationships: {
            'Name': 'LBL_PACKAGE_BUILDER_NAME',
            'Type': 'LBL_PACKAGE_BUILDER_TYPE',
            'Left Module': 'LBL_PACKAGE_BUILDER_LEFT_MODULE',
            'Right Module': 'LBL_PACKAGE_BUILDER_RIGHT_MODULE',
            'From Studio': 'LBL_PACKAGE_BUILDER_FROM_STUDIO',
        },
        workflows: {
            'Name': 'LBL_PACKAGE_BUILDER_NAME',
            'Base Module': 'LBL_PACKAGE_BUILDER_BASE_MODULE',
        },
        reports: {
            'Report Name': 'LBL_PACKAGE_BUILDER_REPORT_NAME',
            'Report Type': 'LBL_PACKAGE_BUILDER_REPORT_TYPE',
            'Module': 'LBL_PACKAGE_BUILDER_MODULE',
        },
        advanced_workflows: {
            'Name': 'LBL_PACKAGE_BUILDER_NAME',
            'Module': 'LBL_PACKAGE_BUILDER_MODULE',
            'Status': 'LBL_PACKAGE_BUILDER_STATUS',
            'Description': 'LBL_PACKAGE_BUILDER_DESCRIPTION',
        },
        acl: {
            'Name': 'LBL_PACKAGE_BUILDER_NAME',
            'Description': 'LBL_PACKAGE_BUILDER_DESCRIPTION',
        },
        language: {
            'Module': 'LBL_PACKAGE_BUILDER_MODULE',
        },
        miscellaneous: {
            'Category': 'LBL_PACKAGE_BUILDER_CATEGORY',
            'Description': 'LBL_PACKAGE_BUILDER_DESCRIPTION',
        },
    },

    /**
     * @inheritdoc
     */
    initialize: function(options) {
        this._super('initialize', [options]);

        this.checkedRows = {};
        this.title = options.tableData.tableName;
        this.headers = options.tableData.tableHeaders;
        this.headerLabels = _.map(this.headers, function(value) {
            return this.allHeaderLabels[this.title][value];
        }.bind(this));
        this.initEntries(options);
        this.tableContext = new app.Context({});

        this.addCheckboxFields();
        this.registerHandleBarsHelpers();
        this._bindEvents();
        this.initMatchingRows();
    },

    /**
     * @inheritdoc
     * @private
     */
    _bindEvents: function() {
        this.tableContext.on('list:paginate', this.renderTable.bind(this));
    },

    /**
     * Initialize entries
     *
     * @param {Object} options
     */
    initEntries: function(options) {
        this.entries = options.tableData.tableEntries ? options.tableData.tableEntries : [];
        this.entries.map((el, i) => this.entries[i] = _.extend({}, el, {_id: i}));
    },

    /**
     * Add all entries IDs to the list of matching rows
     */
    initMatchingRows: function() {
        const matchingRows = Array.from(this.entries.keys());
        this.tableContext.set('matchingRows', matchingRows);
    },

    /**
     * @inheritdoc
     */
    render: function() {
        this.hasContent = (this.entries.length > 0);
        this.model.set('filterInput', this.searchTerm);
        // Call super
        this._super('render');
        this.showPagination();

        this.initSearchInputEvent();
        this.doneTyping();
        this.updateSelectedDisplay();
        this.bindCheckboxEvents();
    },

    /**
     * Add events for rendered checkboxes
     */
    bindCheckboxEvents: function() {
        const checkboxes = this.$el.find('.checkboxFilter');
        _.each(checkboxes, (checkbox) =>
            checkbox.addEventListener('click', this.doneTyping.bind(this)));
    },

    /**
     * Render table and add it to the view
     */
    renderTable: function() {
        const collection = this.tableContext.get('collection').models || [];
        this.collectionIds = [];
        collection.map((model) => this.collectionIds.push(model.get('_id')));

        const tableTpl = app.template.get(this.type + '.table.' + this.module);
        const tableHtml = tableTpl(this);
        this.$('#table-content').html(tableHtml);

        this.initClickHandlers();
        this.activateCheckedRows();
        this.updateCheckAll();
        this._drawAlert();
        const selection = this.$('.checkAll .selection');
        const currentState = selection.prop('checked');
        selection.attr('data-bs-original-title',
            app.lang.get(currentState ? 'LBL_LISTVIEW_DESELECT_ALL_ON_PAGE' : 'LBL_LISTVIEW_SELECT_ALL_ON_PAGE')
        );
    },

    /**
     * Register handlebars helpers
     */
    registerHandleBarsHelpers: function() {
        if (_.isUndefined(Handlebars.helpers.wciFormatEntryValue)) {
            Handlebars
                .registerHelper(
                    'wciFormatEntryValue',
                    function wRegisterWciFormatEntryValue(rawValue, tabTitle, options) {
                        // Alter this variable in order to alter the displayed value
                        let modifiedValue = rawValue;

                        // If we are on reports and header number is 2 (Report Type), alter value
                        let reportTypeIndex = 2;
                        if (tabTitle === 'reports' && options.data.index === reportTypeIndex) {
                            // Report Type Mapping
                            let typeMap = {
                                'summary': app.lang.get(
                                    'LBL_PACKAGE_BUILDER_REPORT_SUMMATION',
                                    'Administration'
                                ),
                                'tabular': app.lang.get(
                                    'LBL_PACKAGE_BUILDER_REPORT_ROWS_AND_COLUMNS',
                                    'Administration'
                                ),
                                'detailed_summary': app.lang.get(
                                    'LBL_PACKAGE_BUILDER_REPORT_SUMMATION_WITH_DETAILS',
                                    'Administration'
                                ),
                                'Matrix': app.lang.get(
                                    'LBL_PACKAGE_BUILDER_REPORT_MATRIX',
                                    'Administration'
                                ),
                            };
                            modifiedValue = typeMap[rawValue];
                        }

                        return options.fn(modifiedValue);
                    }
                );
        }

        if (_.isUndefined(Handlebars.helpers.getValueByIndex)) {
            Handlebars
                .registerHelper(
                    'getValueByIndex',
                    (el, index) =>
                        (_.isArray(el) || _.isObject(el)) ? el[index] : ''
                );
        }
    },

    /**
     * This function will init the stop-typing events for the filter search input
     */
    initSearchInputEvent: function() {
        // Timming setup
        let typingTimer = null;
        let doneTypingInterval = 1000; // 1s after the user is done typing
        let searchInput = this.$el.find('.filterSearchInput'); // Get the search input element

        // On keyup, start the countdown
        searchInput.on('keyup', function onKeyupStartCd() {
            clearTimeout(typingTimer);
            typingTimer = setTimeout(this.doneTyping.bind(this), doneTypingInterval);
        }.bind(this));

        // On keydown, clear the countdown
        searchInput.on('keydown', function onKeydownClearCd() {
            clearTimeout(typingTimer);
        }.bind(this));
    },

    /**
     * User is done typing in the search filter
     */
    doneTyping: function() {
        let searchTerm = this.$el.find('.filterSearchInput input').val();
        let filterHeaders = this.getCheckedHeaders(); // array with checked headers by index (e.g. [0,2,3])

        if (_.isEmpty(searchTerm) || _.isEmpty(filterHeaders)) {
            // If currently the search term is empty, but this.searchTerm is not empty(previously was a value)
            if (!_.isEmpty(this.searchTerm)) {
                // Reset this.searchTerm
                this.searchTerm = '';
                // Uncheck the CheckAll header checkbox, this means the user is done filtering the rows
                this.$el.find('.table .headerRow .selection').eq(0).prop('checked', false);
            }
        }

        this.filterRows(searchTerm, filterHeaders);
    },

    /**
     * Get the index for the checked types
     * @return {Array} Array with the checked headers by index
     */
    getCheckedHeaders: function() {
        // Get the checkboxes used for filtering
        let checkboxes = this.$el.find('#filterCustomizations .checkboxFilter [type="checkbox"]');
        let headers = []; // here insert the checked headers

        _.each(checkboxes, function(checkbox, key) {
            if (checkbox.checked === true) {
                headers.push(key);
            }
        });

        return headers; // return the list with the checked headers
    },

    /**
     * Filter the rows based on the search term and the headers that are checked
     *
     * @param {string} searchTerm - The search term
     * @param {Array} filterHeaders - The headers that are checked
     */
    filterRows: function(searchTerm, filterHeaders) {
        app.alert.show('pb_filtering_loading', {
            level: 'process',
            title: app.lang.get('LBL_PACKAGE_BUILDER_FILTERING', 'Administration'),
        });

        let matchingRows = [];

        _.each(this.entries, function(entry, entryKey) {
            let entryMached = false; // current entry/row is maching the filtering condition

            if (!searchTerm || _.isEmpty(filterHeaders)) {
                entryMached = true;
            } else {
                _.each(filterHeaders, function(headerIndex) {
                    let indexValue = entry[headerIndex];
                    if (_.isNull(indexValue) === false && _.isUndefined(indexValue) === false &&
                        indexValue.toString().toLocaleLowerCase().includes(searchTerm.toLocaleLowerCase())) {
                        entryMached = true;
                    }
                });
            }
            // If the row is matching
            if (entryMached) {
                // Push the row index in to the matchingRows list
                matchingRows.push(entryKey);
            }
        });

        // Unhide the rows that mach the filter
        app.alert.dismiss('pb_filtering_loading');
        this.searchTerm = searchTerm; // After the filtering process is done, save the search term on this

        this.tableContext.set('matchingRows', matchingRows);
        this.tableContext.trigger('filter:fetch:success');
    },

    /**
     * Render pagination and add it to the view
     */
    showPagination: function() {
        let pagination = app.view.createView({
            name: 'package-builder-pagination',
            module: this.module,
            context: this.tableContext,
            layout: {},
            tableData: this.entries,
        });

        pagination.render();
        this.$('.customizations-pagination').html(pagination.$el);
    },

    /**
     * Update the selected display
     */
    updateSelectedDisplay: function() {
        // Get selected rows length
        const selectedCount = Object.keys(this.checkedRows).length;
        let displayText = '';

        this._hideAlert();
        if (selectedCount > 0) {
            const totalCount = this.entries.length;
            displayText = '(' + selectedCount + '/' + totalCount + ')';
            this._drawAlert();
        }

        const classPath = '.' + this.title + '_tab #selected_display'; // Tab header class path
        this.$el.parent().parent().find(classPath).html(displayText); // Set display text

        this.updateCheckAll();
        const selection = this.$('.checkAll .selection');
        selection.attr('data-bs-original-title', app.lang.get(selection.prop('checked') ?
            'LBL_LISTVIEW_DESELECT_ALL_ON_PAGE' : 'LBL_LISTVIEW_SELECT_ALL_ON_PAGE')
        );
    },

    /**
     * Update Check All checkbox state
     */
    updateCheckAll: function() {
        // If all rows are selected, check the checkAll checkbox
        const checkAll = this._isFullConsist();
        this.$el.find('.checkAll .selection').eq(0).prop('checked', checkAll);
    },

    /**
     * Set click handlers
     */
    initClickHandlers: function() {
        this.$el.find('.regularRow').click(this.checkRegularRowHandler.bind(this));

        // CheckAll function
        this.$el.find('.checkAll').click(this.checkAllHandler.bind(this));

        this.$el.find('.clearFilterInputButton').click(this.clearFilterInputClicked.bind(this));

        // Sorting Function
        this.$el.find('.sorting').click(
            function clickHandle() {
                const sortIndex = $(arguments[0].currentTarget).attr('id');
                const sortField = this.headers[sortIndex];
                let sortType = '';

                // Choose sorting order
                if (this.sortingData === false) {
                    sortType = 'asc';
                } else {
                    const currentSortType = this.sortingData.type;
                    if (this.sortingData.field == sortField) {
                        sortType = currentSortType == 'asc' ? 'desc' : 'asc';
                    } else {
                        sortType = 'asc';
                    }
                }

                let a = this.entries;
                let swapped = null;

                // Begin sorting
                do {
                    swapped = false;
                    for (var i = 0; i < a.length - 1; i++) {
                        let temp = null;
                        let condition = false;

                        // Get swapping terms
                        let aTerm = this.formatSortTerm(a[i][sortIndex]);
                        let bTerm = this.formatSortTerm(a[i + 1][sortIndex]);

                        // Make comparison
                        if (sortType == 'asc') {
                            condition = aTerm > bTerm;
                        } else if (sortType == 'desc') {
                            condition = aTerm < bTerm;
                        }

                        // Swap if condition true
                        if (condition) {
                            temp = a[i];
                            a[i] = a[i + 1];
                            a[i + 1] = temp;
                            swapped = true;
                        }
                    }
                } while (swapped);

                this.sortingData = {field: sortField, type: sortType};
                this.render();
                let sortEl = this.$el.find('th#' + sortIndex)[0];
                if (sortEl) {
                    sortEl.className = 'sorting_' + sortType;
                }
            }.bind(this)
        );
    },

    /**
     * Function-handler for Check All click event
     *
     * @param e
     */
    checkAllHandler: function(e) {
        const target = $(e.target);
        const selection = target.closest('.headerRow').find('.selection').eq(0);
        let currentState = selection.prop('checked');

        // activate checkbox if click target is not input selector
        if (target.hasClass('checkAll')) {
            currentState = !currentState;
            selection.prop('checked', currentState);
        }

        const tableElement = target.closest('#fields_table');
        tableElement.find('tr.regularRow').map((i, elItem) =>
            $(elItem).find('.selection').eq(0).prop('checked', currentState));

        if (currentState) {
            this.collectionIds.map(rowId => {
                this.checkedRows[rowId] = rowId;
            });
        } else {
            this.collectionIds.map(rowId => {
                if (!_.isUndefined(this.checkedRows[rowId])) {
                    delete this.checkedRows[rowId];
                }
            });
        }
        this.updateSelectedDisplay();
    },

    /**
     * Function-handler for Row selection
     *
     * @param e
     */
    checkRegularRowHandler: function(e) {
        const target = $(e.target);
        const selection = target.hasClass('selection') ?
            target :
            target.closest('.regularRow').find('.selection').eq(0);
        const row = selection.closest('tr');
        let currentState = selection.prop('checked');

        // activate checkbox if click target is not input selector
        if (!target.hasClass('selection')) {
            currentState = !currentState;
            selection.prop('checked', currentState);
        }

        const rowId = row.data('id');

        if (currentState && _.isUndefined(this.checkedRows[rowId])) {
            this.checkedRows[rowId] = rowId;
        } else if (!currentState) {
            delete this.checkedRows[rowId];
        }
        this.updateSelectedDisplay();
    },

    /**
     * Activate checkboxes of selected rows
     */
    activateCheckedRows: function() {
        this.collectionIds.map(rowId => {
            if (!_.isUndefined(this.checkedRows[rowId])) {
                this.$el.find(`.regularRow[data-id=${rowId}] .selection`).prop('checked', true);
            }
        });
    },

    /**
     * Format sorting term
     * @param {string} sortingTerm  The sorting term
     * @return {(string|number)} The formatted sorting term
     */
    formatSortTerm: function(sortingTerm) {
        if (_.isNull(sortingTerm) || _.isUndefined(sortingTerm)) {
            return '';
        }

        if (isNaN(sortingTerm)) {
            // Is not a number
            return _.isEmpty(sortingTerm) ? '' : sortingTerm;
        }

        return parseInt(sortingTerm);
    },

    /**
     * Clear filter input clicked
     */
    clearFilterInputClicked: function() {
        this.$el.find('.filterSearchInput input').val('');
        this.doneTyping(); // Trigger search
    },

    /**
     * Get selection
     * @return {Array} The selected rows
     */
    getSelection: function() {
        let restrictions = [];

        if (this.hasContent === false) {
            restrictions = {sync: this.sync};
        } else {
            const indexesMatching = {};
            this.entries.map((el, i) => indexesMatching[el._id] = i);

            _.each(this.checkedRows, (id) => {
                let newElem = {};
                const index = indexesMatching[id];
                for (let i = 0; i < this.headers.length; i++) {
                    newElem[this.headers[i]] = this.entries[index][i];
                }

                restrictions.push(newElem);
            });
        }
        return restrictions;
    },

    /**
     * Add checkbox fields
     */
    addCheckboxFields: function() {
        _.each(this.headers, function(value, key, list) {
            if (value != 'Date Modified') {
                const checkField = {
                    'allowClear': false,
                    'default': true,
                    'label': app.lang.get(this.headerLabels[key], 'Administration'),
                    'labelDown': true,
                    'name': this.buildFieldName(value),
                    'type': 'bool',
                    'openRow': (key === 0),
                    'closeRow': (key === list.length),
                    'css_class': 'checkboxFilter',
                };

                this.meta.panels[0].fields.push(checkField);
            }
        }.bind(this));
    },

    /**
     * Build field name
     * @param {string} displayValue - The display value
     * @return {string} The field name
     */
    buildFieldName: function(displayValue) {
        let fieldName = '';

        if (displayValue.includes(' ')) {
            // If header name has multiple words, return e.g. Custom Module => custom_module
            let fieldWords = displayValue.split(' ');
            let lowercased = fieldWords.map(word => word.toLowerCase());

            fieldName = lowercased.join('_');
        } else {
            fieldName = displayValue.toLowerCase();
        }

        // There are several categories with module field, create unique one for each category
        if (fieldName === 'module') {
            fieldName = fieldName + '_' + this.title;
        }

        return fieldName;
    },

    /**
     * Draw various type of alerts
     * @private
     */
    _drawAlert: function() {
        const selectedCount = Object.keys(this.checkedRows).length;
        if (selectedCount > 0) {
            const totalCount = this.entries.length;
            const alert = (this._isFullConsist() && selectedCount < totalCount) ?
                this._getSelectAllAlert() : this._getSelectedOffsetAlert();
            this._showAlert(alert);
        }
    },

    /**
     * Get selected offset alert
     * @return {jQuery}
     * @private
     */
    _getSelectedOffsetAlert: function() {
        const selectedCount = Object.keys(this.checkedRows).length;
        const totalCount = this.entries.length;
        const selectedOffsetTpl = app.template.getView('list.selected-offset');
        const selectedOffsetAlert = $('<span></span>').append(selectedOffsetTpl({
            num: selectedCount,
            all_selected: totalCount === selectedCount
        }));
        selectedOffsetAlert.find('[data-action=clear]').map((index, el) => {
            $(el).on('click', () => {
                this.checkedRows = {};
                const selectedCheckboxes = this.$el.find('#fields_table').find('tr.regularRow .selection:checked');
                selectedCheckboxes.map((i, elItem) => $(elItem).prop('checked', false));
                this.$el.find('.headerRow').find('.selection:checked').prop('checked', false)
                    .attr('data-bs-original-title', app.lang.get('LBL_LISTVIEW_SELECT_ALL_ON_PAGE'));
                this.$el.parent().parent().find('.' + this.title + '_tab #selected_display').html('');
                this._hideAlert();
            });
        });
        return selectedOffsetAlert;
    },

    /**
     * Get select all alert
     * @return {jQuery}
     * @private
     */
    _getSelectAllAlert: function() {
        const selectedCount = Object.keys(this.checkedRows).length;
        const totalCount = this.entries.length;
        const selectAllTpl = app.template.compile(null, app.lang.get('TPL_LISTVIEW_SELECT_ALL_RECORDS'));
        const selectAllLinkTpl = new Handlebars.SafeString(
            '<button type="button" class="btn btn-link btn-inline" data-action="select-all">' +
            app.lang.get('LBL_LISTVIEW_SELECT_ALL_RECORDS') +
            '</button>'
        );
        const selectAllAlert = $('<span></span>').append(selectAllTpl({
            num: selectedCount,
            link: selectAllLinkTpl
        }));
        selectAllAlert.find('[data-action=select-all]').map((index, el) => {
            $(el).on('click', () => {
                const matchingRows = this.tableContext.get('matchingRows');
                matchingRows.map(rowId => {
                    this.checkedRows[rowId] = rowId;
                });
                this._hideAlert();
                this._showAlert(this._getSelectedOffsetAlert());
                const displayText = '(' + totalCount + '/' + totalCount + ')';
                this.$el.parent().parent().find('.' + this.title + '_tab #selected_display').html(displayText);
            });
        });
        return selectAllAlert;
    },

    /**
     * Show alert
     * @param {jQuery} alert
     * @private
     */
    _showAlert: function(alert) {
        this.$('[data-target=alert]').html(alert);
        this.$('[data-target=alert-container]').removeClass('hide');
    },

    /**
     * Hide alert
     * @private
     */
    _hideAlert: function() {
        this.$('[data-target=alert-container]').addClass('hide');
        this.$('[data-target=alert]').empty();
    },

    /**
     * Check if all rows of the displayed table were selected
     * @private
     * @return {boolean} True if all rows are selected
     */
    _isFullConsist: function() {
        return !this.collectionIds.some(rowId =>
            _.isUndefined(this.checkedRows[rowId]));
    },
});
