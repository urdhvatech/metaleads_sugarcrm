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
 * @class View.Views.Base.AdministrationPackageBuilderCustomizationsTabView
 * @alias SUGAR.App.view.views.BaseAdministrationPackageBuilderCustomizationsTabView
 * @extends View.View
 */
({
    /**
     * Customizations tabs
     */
    customizationsTabs: {},

    /**
     * Customizations tabs view
     */
    customizationsTabsView: {},

    /**
     * Customizations data
     */
    customizations: false,

    /**
     * Active customizations tab
     */
    customizationsActiveTab: '',

    /**
     * Invalid characters for package name
     */
    invalidCharacters: ['/', '@', '!', '#', '$', '%', '*', '.', '=', '<', '>', '?', '-', '|', '\\', '^',
        '(', ')', '[', ']', '{', '}', ';', '+', '§', '±', ','],

    /**
     * @inheritdoc
     */
    initialize: function(options) {
        this._super('initialize', [options]);

        this.customizations = options.customizations;
        this.customizationsTabsView = {};
    },

    /**
     * @inheritdoc
     */
    render: function() {
        this.setDefaultValues();
        this.buildTabs();

        // Call super
        this._super('render');

        this.initEvents();
        this.displaySelectedHeaders();
        this.showCustomizationsTabContent();
    },

    /**
     * Add event listeners
     */
    initEvents: function() {
        // Tabs events
        const tabs = this.$el.find('.customizationsTabs');
        _.each(tabs, function addEvClickOnTabs(tab) {
            tab.addEventListener('click', this.customizationsTabChanged.bind(this));
        }.bind(this));

        // Init create button event
        if (_.isUndefined(this.context._events) ||
            _.isEmpty(this.context._events['button:download_package_button:click'])) {
            this.listenTo(this.context, 'button:download_package_button:click',
                this.openCreatePackageDialog.bind(this));
        }
        // Init add to local packages button event
        if (_.isUndefined(this.context._events) ||
            _.isEmpty(this.context._events['button:add_to_local_packages_button:click'])) {
            this.listenTo(
                this.context, 'button:add_to_local_packages_button:click',
                this.addToLocalPackagesDialog.bind(this)
            );
        }
    },

    /**
     * Build tabs
     */
    buildTabs: function() {
        let isFirstCategory = true;
        _.each(this.customizations, function(categoryData, category) {
            // First element will be the active tab
            if (isFirstCategory) {
                // Set category as active tab
                this.customizationsTabs[category].active = true;
                this.customizationsActiveTab = category;
                isFirstCategory = false;
            }
            // If we have data for this category, enable category tab
            if (this.categoryHasContent(category)) {
                this.customizationsTabs[category].disabled = false;
            }
        }.bind(this));
    },

    /**
     * Check if category has content
     * @param {string} categoryName
     * @return {boolean}
     */
    categoryHasContent: function(categoryName) {
        let hasData = false;
        const categoryData = this.customizations[categoryName];
        if (categoryName === 'miscellaneous') {
            const scheduledJobs = (categoryData.scheduled_jobs.map.db.schedulers.length > 0);
            const displayModules = (categoryData.display_modules_and_subpanels.map.db.config.length > 0);
            const quickCreate = (categoryData.quick_create_bar.map.db.length > 0);

            hasData = (displayModules || scheduledJobs || quickCreate);
        } else {
            hasData = (categoryData.length > 0);
        }

        return hasData;
    },

    /**
     * Show customizations tab content
     */
    showCustomizationsTabContent: function() {
        let tabContent = this.$el.find('.customizations-tab-content');

        // Get table data
        let tableData = this.getTableData();

        if (_.isUndefined(this.customizationsTabsView[this.customizationsActiveTab])) {
            // Create the new view
            let tabView = app.view.createView({
                name: 'package-builder-content-table',
                tableData: tableData,
            });

            tabView.render();

            tabContent.empty();
            tabContent.append(tabView.$el);
            this.customizationsTabsView[this.customizationsActiveTab] = tabView;
        } else {
            tabContent.empty();
            tabContent.append(this.customizationsTabsView[this.customizationsActiveTab].$el);
            // If there are no entries
            if (_.isEmpty(this.customizationsTabsView[this.customizationsActiveTab].entries)) {
                // Sync with tableData, entries might have been reset when customizations were refetched
                this.customizationsTabsView[this.customizationsActiveTab].entries = tableData.tableEntries;
            }
            this.customizationsTabsView[this.customizationsActiveTab].render();
        }
    },

    /**
     * Get table data
     * @return {Object}
     */
    getTableData: function() {
        let tableHeaders = false;
        let viewEntries = [];

        // Get selected/active tab customizations data
        let data = _.find(this.customizations, function(data, category) {
            return category === this.customizationsActiveTab;
        }.bind(this));

        let entries = data || [];

        _.each(entries, function(entry) {
            let elementData = entry.data;

            if (!tableHeaders) {
                tableHeaders = [];
                for (let headerName in elementData) {
                    tableHeaders.push(headerName);
                }
            }

            let viewEntry = [];

            for (let i = 0; i < tableHeaders.length; i++) {
                viewEntry.push(elementData[tableHeaders[i]]);
            }
            viewEntries.push(viewEntry);
        }, this);

        if (this.customizationsActiveTab == 'miscellaneous') {
            tableHeaders = ['Category', 'Description'];
            viewEntries = [];
            for (let category in data) {
                let desc = data[category].data.Description;
                viewEntries.push([category, desc]);
            }
        }

        return {
            'tableName': this.customizationsActiveTab,
            'tableHeaders': tableHeaders,
            'tableEntries': viewEntries,
        };
    },

    /**
     * Customizations tab changed
     * @param {Event} $el
     */
    customizationsTabChanged: function($el) {
        this.customizationsActiveTab = $el.target.getAttribute('name');
        this.activeSubtabChanged();
        this.showCustomizationsTabContent();
    },

    /**
     * Active subtab changed
     */
    activeSubtabChanged: function() {
        let oldActiveSubtab = this.$el.find('.customizations-tab-list  .tab.active')[0];
        let newActiveSubtab = this.$el.find('.customizations-tab-list  .' + this.customizationsActiveTab + '_tab')[0];

        if (_.isUndefined(oldActiveSubtab) === false && _.isUndefined(newActiveSubtab) === false &&
            oldActiveSubtab !== newActiveSubtab
        ) {
            oldActiveSubtab.classList.remove('active'); // Remove previous active tab class
            newActiveSubtab.classList.add('active'); // Add active class to the current active tab
        }
    },

    /**
     * Set default values
     */
    setDefaultValues: function() {
        this.customizationsTabs = {
            'acl': {
                'value': 'acl',
                'label': 'LBL_PACKAGE_BUILDER_TAB_CUSTOMIZATIONS_ACL_T',
                'active': false,
                'disabled': true
            },
            'advanced_workflows': {
                'value': 'advanced_workflows',
                'label': 'LBL_PACKAGE_BUILDER_TAB_CUSTOMIZATIONS_ADVANCEDWORKFLOWS_T',
                'active': false,
                'disabled': true
            },
            'dashboards': {
                'value': 'dashboards',
                'label': 'LBL_PACKAGE_BUILDER_TAB_CUSTOMIZATIONS_DASHBOARDS_T',
                'active': false,
                'disabled': true
            },
            'dropdowns': {
                'value': 'dropdowns',
                'label': 'LBL_PACKAGE_BUILDER_TAB_CUSTOMIZATIONS_DROPDOWNS_T',
                'active': false,
                'disabled': true
            },
            'fields': {
                'value': 'fields',
                'label': 'LBL_PACKAGE_BUILDER_TAB_CUSTOMIZATIONS_FIELDS_T',
                'active': false,
                'disabled': true
            },
            'language': {
                'value': 'language',
                'label': 'LBL_PACKAGE_BUILDER_TAB_CUSTOMIZATIONS_LANGUAGE_T',
                'active': false,
                'disabled': true
            },
            'layouts': {
                'value': 'layouts',
                'label': 'LBL_PACKAGE_BUILDER_TAB_CUSTOMIZATIONS_LAYOUTS_T',
                'active': false,
                'disabled': true
            },
            'miscellaneous': {
                'value': 'miscellaneous',
                'label': 'LBL_PACKAGE_BUILDER_TAB_CUSTOMIZATIONS_MISCELLANEOUS_T',
                'active': false,
                'disabled': true
            },
            'relationships': {
                'value': 'relationships',
                'label': 'LBL_PACKAGE_BUILDER_TAB_CUSTOMIZATIONS_RELATIONSHIPS_T',
                'active': false,
                'disabled': true
            },
            'reports': {
                'value': 'reports',
                'label': 'LBL_PACKAGE_BUILDER_TAB_CUSTOMIZATIONS_REPORTS_T',
                'active': false,
                'disabled': true
            },
            'search_layouts': {
                'value': 'search_layouts',
                'label': 'LBL_PACKAGE_BUILDER_TAB_CUSTOMIZATIONS_SEARCHL_T',
                'active': false,
                'disabled': true
            },
            'workflows': {
                'value': 'workflows',
                'label': 'LBL_PACKAGE_BUILDER_TAB_CUSTOMIZATIONS_WORKFLOWS_T',
                'active': false,
                'disabled': true
            },
        };
    },

    /**
     * Open create package dialog
     */
    openCreatePackageDialog: function() {
        if (this.customizations === false) {
            App.alert.show('pb_alert', {
                level: 'info',
                messages: app.lang.get('LBL_PACKAGE_BUILDER_NEED_FETCH_CUSTOMIZATIONS', 'Administration'),
                autoClose: true
            });
            return;
        }

        if (!this.hasSelections()) {
            App.alert.show('pb_alert', {
                level: 'error',
                messages: app.lang.get('LBL_PACKAGE_BUILDER_NEED_SELECT_CUSTOMIZATIONS', 'Administration'),
                autoClose: true
            });
            return;
        }

        let modalDiv =
            '<div id="pb_modal" class="modal hide">' +
            '<div class="modal-dialog">' +
            '<div class="modal-content">' +
            '<div class="modal-header" style="padding:5px 15px 5px 15px;">' +
            '<h4 class="inline px-1">' +
            app.lang.get('LBL_PACKAGE_BUILDER_DOWNLOAD_PACKAGE', 'Administration') + '</h4>' +
            '</div>' +
            '<div class="modal-body !pb-10" style="margin-left:20px; margin-bottom:20px;">' +

            '<div class="mt-4 mb-3 text-sm">' +
                app.lang.get('LBL_PACKAGE_BUILDER_PACKAGE_SAVE_MESSAGE', this.module) +
            '</div>' +

            '<div id="additional_settings">' +
            '<div class="mb-4" style="margin-top:5px; margin-right:40px;">' +
            '<label class="flex mb-1">' + app.lang.get('LBL_PACKAGE_BUILDER_PACKAGE_NAME', 'Administration') +
                ':</label>' +
            '<input type="text" name="name" value="" maxlength="255" class="input-large" ' +
            'aria-label="' + app.lang.get('LBL_PACKAGE_BUILDER_PACKAGE_NAME', 'Administration') + '"' +
            ' style="width:100%;">' +
            '</div>' +
            '</div>' +
            '</div>' +
            '<div class="modal-footer !py-2.5 !px-0 flex justify-end gap-2.5">' +
            '<button type="button" class="btn btn-secondary !text-gray-800 !border-gray-300 !leading-[1.375rem] ' +
                'dark:!bg-gray-800 dark:!border-gray-600 dark:!text-gray-400 ' +
                'dark:hover:!bg-gray-700 dark:hover:!border-gray-400"' +
                'data-bs-dismiss="modal">' +
                app.lang.get('LBL_PACKAGE_BUILDER_CANCEL', 'Administration') +
            '</button>' +
            '<a id="create_package_modal" class="btn btn-primary !leading-[1.5rem] !m-0" data-bs-dismiss="modal">' +
                app.lang.get('LBL_PACKAGE_BUILDER_DOWNLOAD_PACKAGE', 'Administration') +
            '</a>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>';

        if ($('#pb_modal').length != 0) {
            $('#pb_modal').remove();
        }

        let result = $(modalDiv)
            .appendTo('body')
            .modal('show');

        $('#pb_modal').find('input[name="name"]').val(this._getDefaultPackageName());
        result.find('#create_package_modal').on(
            'click',
            function clickHandler(event) {
                let name = $('#pb_modal').find('input[name="name"]').val();

                // Check if zip name is valid
                let invalidCharactersFound = this.getInvalidCharacters(name);
                if (invalidCharactersFound.length > 0) {
                    // Show invalid characters found
                    let lbl = app.lang.get('LBL_PACKAGE_BUILDER_TAB_CREATE_PACKAGE_INVALID_NAME', 'Administration');
                    app.alert.show('pb_alert_invalid_package_name', {
                        level: 'error',
                        messages: lbl + invalidCharactersFound,
                        autoClose: true
                    });

                    event.stopPropagation(); // stop click event
                    return; // stop package creation
                }

                let cb = function(content) {
                    //eslint-disable-next-line no-undef
                    saveAs(content, this.packageName);
                };
                const successMessage = app.lang.get('LBL_PACKAGE_BUILDER_CREATE_PACKAGE_SUCCESS', this.module);
                this.getFilteredCustomizations(this.createPackage.bind(this, name, cb, successMessage));
            }.bind(this)
        );
    },

    /**
     * Open create package dialog
     */
    addToLocalPackagesDialog: function() {
        if (!this.hasSelections()) {
            App.alert.show('pb_alert', {
                level: 'error',
                messages: app.lang.get('LBL_PACKAGE_BUILDER_NEED_SELECT_CUSTOMIZATIONS', 'Administration'),
                autoClose: true
            });
            return;
        }
        const modalDialog = app.template.getView(this.name + '.modal-dialog', this.module);
        const args = {
            packageName: this._getDefaultPackageName()
        };
        const modalId = '#pb_modal_local_package';
        if ($(modalId).length != 0) {
            $(modalId).remove();
        }
        $(modalDialog(args)).appendTo('body').modal('show').find('#add_to_local').on(
            'click',
            (event) => {
                let name = $(modalId).find('input[name="name"]').val();
                if (!this.validatePackageName(name)) {
                    event.stopPropagation(); // stop click event
                    return; // stop package creation
                }
                this.getFilteredCustomizations(
                    this.createPackage.bind(
                        this,
                        name,
                        (content) => {
                            const data = new FormData();
                            const file = new File([content], name + '.zip', {type: 'application/zip'});
                            data.append('upgrade_zip', file);
                            const options = {
                                'skipMetadataHash': true,
                                'contentType': false,
                                'processData': false,
                                success: function(data) {
                                    if (this.context && data.id) {
                                        let installedPackages = this.context.get('installedPackages') || {};
                                        installedPackages[data.id] = data;
                                        this.context.set('installedPackages', installedPackages);
                                    }
                                }.bind(this)
                            };

                            if (file.size > app.config.uploadMaxsize) {
                                const bytesToMB = 1000000;
                                const maxSizeMB = Math.round(app.config.uploadMaxsize / bytesToMB);

                                let message = app.lang.get(
                                    'LBL_PACKAGE_BUILDER_TAB_PACKAGES_MAX_SIZE_ERR',
                                    'Administration'
                                );
                                message = message.replace('<max_size>', maxSizeMB);

                                app.alert.dismiss('pb_alert');
                                app.alert.show('pb_alert', {
                                    level: 'error',
                                    messages: message,
                                    autoClose: true,
                                    autoCloseDelay: 5000
                                });

                                return;
                            }

                            app.api.call('create', app.api.buildURL('Administration/packages'), data, null, options);
                        },
                        app.lang.get('LBL_PACKAGE_BUILDER_ADD_TO_LOCAL_SUCCESS_MESSAGE', this.module)
                    )
                );
            }
        );
    },

    /**
     * Validate package name
     * @param {string} name
     * @return {boolean}
     */
    validatePackageName: function(name) {
        let invalidCharactersFound = this.getInvalidCharacters(name);
        if (invalidCharactersFound.length > 0) {
            // Show invalid characters found
            let lbl = app.lang.get('LBL_PACKAGE_BUILDER_TAB_CREATE_PACKAGE_INVALID_NAME', 'Administration');
            app.alert.show('pb_alert_invalid_package_name', {
                level: 'error',
                messages: lbl + invalidCharactersFound,
                autoClose: true
            });
            return false;
        }
        return true;
    },

    /**
     * Check if we have any selections
     * @return {boolean}
     */
    hasSelections: function() {
        let selectedItems = [];
        let selectionsFound = false;
        // Check if we got any selection, if we found at least one selection retrun false
        _.each(this.customizationsTabsView, function(categoryView) {
            selectedItems = categoryView.getSelection();

            if (selectedItems.length > 0) {
                selectionsFound = true;
                return; // we have selections, stop iteration
            }
        }, this);
        return selectionsFound;
    },

    /**
     * Get invalid characters
     * @param {string} name
     * @return {string}
     */
    getInvalidCharacters: function(name) {
        let result = '';

        _.each(this.invalidCharacters, function(invalidCh) {
            // If invalid character found
            if (name.includes(invalidCh)) {
                // Append to result
                result = result + invalidCh + ' ';
            }
        }.bind(this));

        return result;
    },

    /**
     * Get filtered customizations
     * @param {Function} callback
     */
    getFilteredCustomizations: function(callback) {
        this.filteredCustomizations = {};
        this.summaryRestrictions = {};

        _.each(this.customizationsTabsView, function(currentElement) {
            this.summaryRestrictions[currentElement.title] = currentElement.getSelection();
        }, this);

        for (let category in this.summaryRestrictions) {
            this.filteredCustomizations[category] = {};
            this.filteredCustomizations[category].map = {};
            this.filteredCustomizations[category].map.files = [];
            this.filteredCustomizations[category].map.db = {};
            if (category === 'miscellaneous') {
                for (let i = 0; i < this.summaryRestrictions[category].length; i++) {
                    let subcategory = this.summaryRestrictions[category][i].Category;
                    let categoryCustomizationsMap = this.customizations[category][subcategory].map;
                    this.filteredCustomizations[subcategory] = {};
                    this.filteredCustomizations[subcategory].map = {};
                    this.filteredCustomizations[subcategory].map.files = [];
                    this.filteredCustomizations[subcategory].map.db = {};
                    this.filteredCustomizations[subcategory].map.db = categoryCustomizationsMap.db;
                    this.filteredCustomizations[subcategory].map.files = categoryCustomizationsMap.files;
                }
            } else if (category === 'fields') {
                this.getElementsDataForFields(this.summaryRestrictions[category]);
            } else if (category !== 'dashboards' && category !== 'advanced_workflows') {
                this.getElementsData(category, this.summaryRestrictions[category]);
            }
        }

        // Fetch db data for dashboards and advanced workflows
        if (!_.isEmpty(this.summaryRestrictions.dashboards) ||
            !_.isEmpty(this.summaryRestrictions.advanced_workflows)) {
            // Fetch db data for dashboards and advanced workflows
            let elements = {
                dashboards: this.summaryRestrictions.dashboards || [],
                advanced_workflows: this.summaryRestrictions.advanced_workflows || []
            };
            this.fetchDbData(elements, callback);
        } else if (_.isFunction(callback)) {
            callback();
        }
    },

    /**
     * Create package
     * @param {string} packageName
     * @param {Function} callbackFunction
     * @param {string} successMessage
     */
    createPackage: function(packageName, callbackFunction, successMessage) {
        let url = app.api.buildURL('Administration/package');
        let data = {
            customizations: this.filteredCustomizations,
            packageName: packageName
        };
        let callback = function(data) {
            if (data === false) {
                app.alert.dismiss('pb_loading');
                app.alert.show('pb_alert', {
                    level: 'warning',
                    messages: app.lang.get('LBL_PACKAGE_BUILDER_TAB_CREATE_PACKAGE_OVER_LIMIT', 'Administration'),
                    autoClose: false
                });
                return;
            }
            this.packageName = data.package_info.packageName;
            let filesMapping = [];
            for (let category in data) {
                let filesMap = false;
                if (data[category] && data[category].map && data[category].map.files) {
                    filesMap = data[category].map.files;
                }
                if (filesMap) {
                    for (let index = 0; index < filesMap.length; index++) {
                        let fileInfo = filesMap[index];
                        filesMapping.push({
                            path: fileInfo.path,
                            content: fileInfo.content
                        });
                    }
                }
            }

            let map = {};
            for (let index1 = 0; index1 < filesMapping.length; index1++) {
                let currentPath = map;
                let path = filesMapping[index1].path;
                path = path.split('/');
                for (let i = 0; i < path.length; i++) {
                    let currentPathSection = path[i];
                    if (!currentPath[currentPathSection]) {
                        // eslint-disable-next-line max-depth
                        if (i == path.length - 1) {
                            currentPath[currentPathSection] = {
                                content: filesMapping[index1].content
                            };
                        } else {
                            currentPath[currentPathSection] = {};
                        }
                    }
                    currentPath = currentPath[currentPathSection];
                }
            }

            /**
             * Recursive function that creates the archive
             * @param {Object} map the folder mapping
             * @param {Object} archiveFolder current folder of archive
             * @return {null}
             */
            function createArchive(map, archiveFolder) {
                for (let i in map) {
                    if (map[i].content) {
                        archiveFolder.file(i, map[i].content, {
                            base64: true
                        });
                    } else {
                        let folder = archiveFolder.folder(i);
                        createArchive.call(this, map[i], folder);
                    }
                }
            }

            //eslint-disable-next-line no-undef
            let packageZip = new JSZip();
            createArchive.call(this, map, packageZip);
            packageZip.generateAsync({type: 'blob'}).then(
                function cb(content) {
                    app.alert.dismiss('pb_loading');
                    app.alert.show('pb_alert', {
                        level: 'success',
                        messages: successMessage,
                        autoClose: true
                    });

                    if (callbackFunction) {
                        callbackFunction.call(this, content, successMessage);
                    }
                }.bind(this)
            );
        }.bind(this);

        app.api.call('create', url, data, {
            success: callback
        });
        app.alert.show('pb_loading', {
            level: 'process',
            title: app.lang.get('LBL_PACKAGE_BUILDER_CREATING_PACKAGE', 'Administration'),
        });
    },

    /**
     * Get elements data for fields
     * @param {Array} elements
     */
    getElementsDataForFields: function(elements) {
        _.each(elements, function(elementData, pos, requestedElements) {
            let category = 'fields';
            let result = _.find(this.customizations[category], function(value) {
                return value.data.Name === elementData.Name && value.data.Type === elementData.Type &&
                    value.data['Custom Module'] === elementData['Custom Module'];
            });

            if (result) {
                let customizationElement = result.map;
                let elementFiles = customizationElement.files;

                _.each(elementFiles, function(fileData) {
                    this.filteredCustomizations[category].map.files.push(fileData);
                }, this);

                /*eslint-disable*/
                let dbData = customizationElement.db;
                if (dbData.length !== 0) {
                    for (let tableName in dbData) {
                        if (!this.filteredCustomizations[category].map.db[tableName]) {
                            this.filteredCustomizations[category].map.db[tableName] = [];
                        }
                        let tableDBEntries = dbData[tableName];

                        _.each(tableDBEntries, function(dbRow) {
                            this.filteredCustomizations[category].map.db[tableName].push(dbRow);
                        }, this);
                    }
                }

                switch (elementData.type) {
                    case 'relate':
                        //we need to include also the id_name data
                        let fieldMetaData = result.map.db.fields_meta_data[0];
                        if (fieldMetaData && fieldMetaData.ext3) {
                            this._addFieldToPackageIfNeeded(
                                requestedElements,
                                category,
                                fieldMetaData.ext3,
                                elementData
                            );
                        }
                        break;
                    case 'currency':
                        //we need to take also the related fields. Generally base_rate and currency_id
                        if (_.isArray(result.map.related_fields)) {
                            _.each(result.map.related_fields, function addCurrencyRelatedField(fieldName) {
                                this._addFieldToPackageIfNeeded(requestedElements, category, fieldName, elementData);
                            }, this);
                        }
                        break;
                }
                /*eslint-enable*/
            }
        }, this);
    },

    /**
     * Add field to package if needed
     * @param requestedElements {array}
     * @param category {String}
     * @param fieldName {String}
     * @param elementData {Object}
     * @private
     */
    _addFieldToPackageIfNeeded: function(requestedElements, category, fieldName, elementData) {
        if (requestedElements.findIndex(function idIsAlreadySelected(el) {
                return el.name === fieldName;
            }) === -1
        ) {
            let relatedField = this.customizations[category]
                .find(function findRelated(entry) {
                    return entry.data.name === fieldName &&
                        entry.data.custom_module === elementData.custom_module;
                });

            if (relatedField !== undefined) {
                this.getElementsDataForFields([relatedField.data]);
            }
        }
    },

    /**
     * Get elements data
     * @param {string} category
     * @param {Array} elements
     */
    getElementsData: function(category, elements) {
        _.each(elements, function(elementData) {
            let result;
            let customizationsEntries = this.customizations[category];
            if (category === 'dashboards' || category === 'advanced_workflows') {
                result = elementData;
            } else {
                let elementValuesStringify = JSON.stringify(Object.values(elementData));
                result = _.find(customizationsEntries, function findFunc(value) {
                    return JSON.stringify(Object.values(value.data)) === elementValuesStringify;
                });
            }

            if (result) {
                let customizationElement = result.map;
                let elementFiles = customizationElement.files;

                _.each(elementFiles, function(fileData) {
                    this.filteredCustomizations[category].map.files.push(fileData);
                }, this);

                /*eslint-disable*/
                let dbData = customizationElement.db;
                if (dbData.length !== 0) {
                    for (let tableName in dbData) {
                        if (!this.filteredCustomizations[category].map.db[tableName]) {
                            this.filteredCustomizations[category].map.db[tableName] = [];
                        }
                        let tableDBEntries = dbData[tableName];

                        _.each(tableDBEntries, function(dbRow) {
                            this.filteredCustomizations[category].map.db[tableName].push(dbRow);
                        }, this);
                    }
                }
                /*eslint-enable*/
            }
        }, this);
    },

    /**
     * Fetch db data
     * @param {Array} customizations
     * @param {Function} callback
     */
    fetchDbData: function(customizations, callback) {
        let url = app.api.buildURL('Administration', 'package/data');
        let self = this;
        let callbacks = {
            'success': function(data, request) {
                if (self.disposed) {
                    return;
                }

                _.each(data, function(categoryData, category) {
                    self.getElementsData(category, categoryData);
                });
                if (_.isFunction(callback)) {
                    callback();
                }
            },
            'error': function() {
                if (self.disposed) {
                    return;
                }

                App.alert.show('pb_loading_data', {
                    level: 'error',
                    messages: app.lang.get('LBL_PACKAGE_BUILDER_ERROR_LOADING_DATA', 'Administratio'),
                    autoClose: true
                });
            },
        };
        let data = {};
        _.each(customizations, function(elements, category) {
            data[category] = [];
            let customizationsEntries = self.customizations[category];
            if (category === 'advanced_workflows' || category === 'dashboards') {
                _.each(elements, function(selectedEl, key) {
                    let result = _.find(customizationsEntries, function(value) {
                        // Compare just values as headers might differ for some tabs
                        return JSON.stringify(Object.values(value.data)) === JSON.stringify(Object.values(selectedEl));
                    });

                    data[category].push(result);
                });
            }
        });

        app.api.call('create', url, data, callbacks);
    },

    /**
     * Display selected headers
     */
    displaySelectedHeaders: function() {
        _.each(this.customizationsTabsView, function(subtabView) {
            // In case of refetched data, the checked rows will be reset
            if (Object.keys(subtabView.checkedRows).length === 0) {
                return;
            }

            // Get selected rows length
            let selectedCount = Object.keys(subtabView.checkedRows).length;
            let displayText = '';

            if (selectedCount > 0) {
                let totalCount = subtabView.entries.length;
                displayText = '(' + selectedCount + '/' + totalCount + ')';
            }

            let classPath = '.' + subtabView.title + '_tab #selected_display'; // Tab header class path
            this.$el.find(classPath).html(displayText); // Set display text
        }.bind(this));
    },

    /**
     * Get default package name
     * @return {string}
     */
    _getDefaultPackageName: function() {
        let customFormatDateNumbers = function(number) {
            let sliceNr = -2;
            return ('0' + number).slice(sliceNr);
        };
        let today = new Date();
        return 'PackageBuilderBundle_' +
            today.getFullYear() +
            '_' +
            customFormatDateNumbers(today.getMonth() + 1) +
            '_' +
            customFormatDateNumbers(today.getDate()) +
            '_' +
            customFormatDateNumbers(today.getHours()) +
            customFormatDateNumbers(today.getMinutes()) +
            customFormatDateNumbers(today.getSeconds());
    }
});
