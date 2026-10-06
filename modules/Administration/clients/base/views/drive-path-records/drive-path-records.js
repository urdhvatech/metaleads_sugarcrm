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
 * @class View.Views.Base.AdministrationDrivePathRecordsView
 * @alias SUGAR.App.view.views.BaseAdminstrationDrivePathRecordsView
 * @extends View.Views.Base.View
 */
({
    /**
     * drive types that do support variable paths
     */
    variablePathDisabled: [],
    variablePathReadOnly: ['onedrive', 'sharepoint','google'],
    variablePathEnabled: ['google', 'dropbox', 'onedrive', 'sharepoint'],

    /**
     * field types to use in paths
     */
    acceptedFieldTypes: [
        'varchar', 'text', 'datetime', 'relate', 'phone', 'url',
    ],

    /**
     * flag to disable interaction with the view
     */
    canInteract: true,

    /**
     * list of modules which can't be used
     */
    denyModules: [
        'Login', 'Home', 'WebLogicHooks', 'UpgradeWizard',
        'Styleguide', 'Activities', 'Administration', 'Audit',
        'Calendar', 'MergeRecords', 'Quotas', 'Teams', 'TeamNotices', 'TimePeriods', 'Schedulers', 'Campaigns',
        'CampaignLog', 'CampaignTrackers', 'Documents', 'DocumentRevisions', 'Connectors', 'ReportMaker',
        'DataSets', 'CustomQueries', 'WorkFlow', 'EAPM', 'Users', 'ACLRoles', 'InboundEmail', 'Releases',
        'EmailMarketing', 'EmailTemplates', 'SNIP', 'SavedSearch', 'Trackers', 'TrackerPerfs', 'TrackerSessions',
        'TrackerQueries', 'SugarFavorites', 'OAuthKeys', 'OAuthTokens', 'EmailAddresses',
        'Sugar_Favorites', 'VisualPipeline', 'ConsoleConfiguration', 'SugarLive',
        'iFrames', 'Sync', 'DataArchiver', 'MobileDevices',
        'PushNotifications', 'PdfManager', 'Dashboards', 'Expressions', 'DataSet_Attribute',
        'EmailParticipants', 'Library', 'Words', 'EmbeddedFiles', 'DataPrivacy', 'CustomFields', 'ArchiveRuns',
        'KBDocuments', 'KBArticles', 'FAQ', 'Subscriptions', 'ForecastManagerWorksheets', 'ForecastWorksheets',
        'pmse_Business_Rules', 'pmse_Project', 'pmse_Inbox', 'pmse_Emails_Templates', 'ProductBundleNotes',
        'Comments', 'Feeds', 'HintAccountsets', 'HintNewsNotifications', 'HintEnrichFieldConfigs',
        'HintNotificationTargets', 'CloudDrivePaths', 'Worksheet', 'Employees', 'Newsletters', 'Filters',
        'Feedbacks', 'Tags', 'Categories', 'CommentLog', 'Holidays', 'OutboundEmail', 'Shippers'
    ],

    /**
     * initial record path
     */
    recordPath: '',

    /**
     * default path
     */
    defaultPath: [{
        name: 'My files',
        folderId: 'root'
    }],

    /**
     * @inheritdoc
     */
    events: {
        'click .addField': 'addField',
        'change .moduleList': 'updateFieldList',
        'click .selectPath': 'selectPath',
        'click .savePath': 'savePath',
        'click .removePath': 'removePath',
    },

    /**
     * @inheritdoc
     */
    initialize: function(options) {
        this._super('initialize', arguments);
        this.driveType = this.context.get('driveType');

        this.getModuleList();
        this.loadPaths();
    },

    /**
     * initial load of saved paths
     */
    loadPaths: function() {
        app.alert.dismissAll();

        const url = app.api.buildURL('CloudDrivePaths', null, null, {
            max_num: -1,
            filter: [
                {
                    type: {
                        $equals: this.driveType
                    },
                    is_root: {
                        $equals: 0
                    },
                }
            ],
            order_by: 'path_module:asc',
        });

        app.alert.show('path-loading', {
            level: 'process'
        });

        app.api.call('read', url, null, {
            success: _.bind(this._renderPaths, this),
            error: function(error) {
                app.alert.show('path-load-error', {
                    level: 'error',
                    messages: app.lang.get('LBL_DRIVE_LOAD_PATH_ERROR'),
                });
            },
        });
    },

    /**
     * Manipulate paths so we can render them
     *
     * @param {Array} data
     */
    _renderPaths: function(data) {
        app.alert.dismiss('path-loading');

        this.paths = _.isArray(data.records) && !_.isEmpty(data.records) ? data.records : [];
        this._getFilteredModuleList();

        for (let path of this.paths) {
            try {
                let pathDisplay = _.map(JSON.parse(path.path), function(item) {
                    return item.name;
                }).join('/');
                path.pathDisplay = pathDisplay;
            } catch (e) {
                path.pathDisplay = path.path;
            }
        }

        /**
         * Make sure we have one empty path at the begining
         */
        let rootName = app.lang.getAppString('LBL_MY_FILES');
        if (this.driveType !== 'sharepoint') {
            this.paths.unshift({
                path: '',
                pathDisplay: rootName,
            });
        } else {
            this.paths.unshift({
                path: '',
                pathDisplay: app.lang.get('LBL_DEFAULT_STARTING_PATH', this.module),
            });
        }
        this.render();
        this.canInteract = true;
    },

    /**
     * set initial record path upon addition
     *
     * @param {string} module
     * @param {Event} evt
     * @param {string} path
     */
    setRecordPath: function(module, evt, path) {
        let defaultRecordName = module === 'Contacts' || module === 'Leads' ?
            `${module}/$first_name $last_name` : `${module}/$name`;

        if (!module) {
            defaultRecordName = '';
        }

        this.$(evt.target)
            .parent()
            .parent()
            .children('.span3')
            .children('.recordPath')
            .val(defaultRecordName);
    },

    /**
     * @inheritdoc
     */
    _render: function(options) {
        this._super('_render', arguments);

        this.initDropdowns();
    },

    /**
     * list of available modules
     */
    getModuleList: function() {
        let modulesMeta = app.metadata.getModules({
            filter: 'display_tab',
            access: true,
        });

        this.modules = Object.keys(modulesMeta)
            .filter(key => !this.denyModules.includes(key))
            .reduce((obj, key) => {
                obj[key] = modulesMeta[key];
                return obj;
            }, {});
    },

    /**
     * dropdowns as select2
     */
    initDropdowns: function() {
        this.$('.moduleList').select2({
            autoClear: true,
            containerCssClass: 'select2-choices-pills-close',
            placeholder: app.lang.get('LBL_SELECT_MODULE', this.module),
        });

        this.$('.moduleList').trigger('change');
    },

    /**
     * Add a field variable to the record path
     *
     * @param {Event} evt
     */
    addField: function(evt) {
        let fieldDropdown = this.$(evt.target)
                                .closest('.span6')
                                .parent()
                                .children('.span6')
                                .children('.fieldList');

        if (_.isEmptyValue(fieldDropdown.select2('data'))) {
            app.alert.show('field-required', {
                level: 'error',
                messages: app.lang.get('LBL_SHARED_FIELD_REQUIRED'),
                autoClose: true,
            });

            return;
        }
        let fieldName = fieldDropdown.select2('data').id;
        let recordPath = this.$(evt.target).closest('.span6');
        if (this.driveType === 'google') {
            recordPath = recordPath.children('.recordPathVariable');
        } else {
            recordPath = recordPath.parent()
                            .children('.span3')
                            .children('.recordPath');
        }

        let currentRecordPath = recordPath.val();
        let newPath = currentRecordPath.concat(fieldName);

        recordPath.val(newPath);
    },

    /**
     * Whenever the module changes we need to make sure the field list changes
     *
     * @param {Event} evt
     */
    updateFieldList: function(evt) {
        let _dropdown = this.$(evt.target)
                            .parent()
                            .parent()
                            .children('.span6')
                            .children('.fieldList');
        let path = this.$(evt.target)
                       .closest('.span3')
                       .parent()
                       .find('.recordPath')
                       .val();
        let _module = this.$(evt.target)
                          .parent()
                          .find('select.moduleList')
                          .val();
        let dropdownFields = [];
        if (_.isObject(this.modules[_module]) && _.has(this.modules[_module], 'fields')) {
            let fields = _.filter(this.modules[_module].fields, function(field) {
                return field.type !== 'link' &&
                    field.name &&
                    typeof field.name === 'string' &&
                    field.name.length > 0;
            });
            _.each(fields, targetField => {
                if (_.isObject(targetField)) {
                    let itemName = app.lang.get(targetField.vname, _module) || targetField.name;
                    let itemId = `$${targetField.name}`;
                    const duplicatedName = _.filter(fields, field => field.vname === targetField.vname);

                    if (duplicatedName.length > 1) {
                        itemName = `${itemName} (${targetField.name})`;
                    }
                    dropdownFields.push({
                        id: itemId,
                        text: itemName,
                    });
                }
            });
        }

        _dropdown.select2({
            data: {
                results: dropdownFields
            }
        });
    },

    /**
     * Opens the remote selection drawer so we can select paths from drive
     *
     * @param {Event} evt
     */
    selectPath: function(evt) {
        evt.preventDefault();
        evt.stopPropagation();
        const pathModule = this.$(evt.target)
            .parents('.row-fluid')
            .children('.span3')
            .children('select.moduleList').val();

        const pathId = evt.target.dataset.id;

        if (_.isEmpty(pathModule)) {
            app.alert.show('module-required', {
                level: 'error',
                messages: app.lang.getModString('LBL_MODULE_REQUIRED', this.module),
            });
            return;
        }

        // open the selection drawer
        app.drawer.open({
            context: {
                pathModule: pathModule,
                isRoot: false,
                parentId: 'root',
                folderName: '',
                driveType: this.driveType,
                pathId: pathId
            },
            layout: 'drive-path-select',
        }, _.bind(this.loadPaths, this));
    },

    /**
     * Save a path
     *
     * @param {Event} evt
     */
    savePath: function(evt) {
        let variablePath = '';
        const pathModule = this.$(evt.target)
            .parents('.row-fluid')
            .children('.span3')
            .children('select.moduleList').val();

        const pathId = evt.target.dataset.id;

        // we cannot save a module path without module
        if (!pathModule) {
            app.alert.show('module-required', {
                level: 'error',
                messages: app.lang.getModString('LBL_MODULE_REQUIRED', this.module),
            });
            return;
        }

        let path = this.$(evt.target)
            .parents('.row-fluid')
            .children('.span3')
            .children('.recordPath').val() || 'My files';

        const url = app.api.buildURL('CloudDrive', 'path');

        app.alert.show('path-saving-processing', {
            level: 'process'
        });

        const pathRow = this.$(evt.target).parents('.row-fluid.path');
        const isShared = pathRow.data('isshared');
        const isSharedDrive = pathRow.data('isshareddrive');
        let folderId = pathRow.data('folderid');
        let siteId = null;
        const currentPath = pathRow.data('currentpath');
        const driveId = pathRow.data('driveid');

        //reset folder id if paths do not match
        if (currentPath !== path && !this.variablePathReadOnly.includes(this.driveType)) {
            folderId = null;
        } else if (this.variablePathReadOnly.includes(this.driveType)) {
            // this is meant for onedrive and sharepoint where setting variable paths follow a more strict logic
            // do not change the path. It is readonly.
            const isSharepoint = this.driveType === 'sharepoint';
            const isNonSharepointWithPath = this.driveType !== 'sharepoint' && !_.isEmptyValue(currentPath);

            if (isSharepoint || isNonSharepointWithPath) {
                path = currentPath;
            }

            if (isSharepoint && _.isArray(currentPath)) {
                const siteObj = _.find(currentPath, item => item.resourceType === 'site' && item.id);
                siteId = siteObj ? siteObj.id : null;
            }

            //default path when user sets the path to 'My files'
            if (!isSharepoint && path === 'My files') {
                path = this.defaultPath;
            }

            // we need to check if recordPathVariable is set
            const recordPathVariable = this.$(evt.target)
                .parents('.row-fluid')
                .children('.span6')
                .children('.recordPathVariable').val();

            if (recordPathVariable) {
                variablePath = recordPathVariable;
            }
        }

        this.canInteract = false;

        // do not save empty paths
        if (_.isEmpty(path)) {
            app.alert.show('path-required', {
                level: 'error',
                messages: app.lang.getModString('LBL_PATH_REQUIRED', this.module),
            });
            app.alert.dismiss('path-saving-processing');
            return;
        }

        // make sure path is a string
        // path might be a json object if it is a variable path
        if (typeof path !== 'string') {
            try {
                path = JSON.stringify(path);
            } catch (e) {
                app.alert.show('path-error', {
                    level: 'error',
                    messages: app.lang.getModString('LBL_PATH_ERROR', this.module),
                });
                app.alert.dismiss('path-saving-processing');
                return;
            }
        }

        let params = {
            pathModule: pathModule,
            isRoot: false,
            type: this.driveType,
            drivePath: path,
            isShared: isShared,
            isSharedDrive: isSharedDrive,
            folderId: folderId,
            driveId: driveId,
            pathId: pathId,
            variablePath: variablePath,
            modifySiteId: false,
        };

        if (this.driveType === 'sharepoint') {
            params.modifySiteId = true;
            params.siteId = siteId;
        }

        app.api.call('create', url, params, {
            success: _.bind(function() {
                app.alert.show('path-saved', {
                    level: 'success',
                    messages: app.lang.getModString('LBL_PATH_SAVED', this.module),
                });
                this.loadPaths();
            }, this),
            error: function(error) {
                app.alert.show('path-error', {
                    level: 'error',
                    messages: error.message,
                });
            },
            complete: function() {
                app.alert.dismiss('path-saving-processing');
            }
        });
    },

    /**
     * Remove a path
     *
     * @param {Event} evt
     */
    removePath: function(evt) {
        if (!this.canInteract) {
            app.alert.show('disallow-action', {
                level: 'warning',
                messages: app.lang.get('LBL_DISALLOW_ACTION', this.module),
                autoClose: true,
            });
            return;
        }

        const pathId = evt.target.dataset.id;
        const url = app.api.buildURL('CloudDrive', 'path');

        evt.currentTarget.classList.add('disabled');

        app.api.call('delete', url, {
            pathId: pathId,
        }, {
            success: _.bind(function() {
                app.alert.show('path-deleted', {
                    level: 'success',
                    messages: app.lang.get('LBL_ROOT_PATH_REMOVED', this.module),
                });
                this.loadPaths();
            }, this),
        });
    },

    /**
     * Get the updated module list ( without the already selected modules )
     */
    _getFilteredModuleList: function() {
        let selectedModules = this.paths.map((cPath) => cPath.path_module).filter((module) => module !== undefined);
        this.filteredModuleList = {};

        for (let module of selectedModules) {
            this.filteredModuleList[module] = this.modules;
            let moduleToExclude = selectedModules.filter((currentModule) => currentModule !== module);
            this.filteredModuleList[module] = _.omit(this.filteredModuleList[module],moduleToExclude);
        }

        this.moduleList = _.omit(this.modules,selectedModules);
    },
});
