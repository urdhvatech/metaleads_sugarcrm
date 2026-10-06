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
 * @class View.Views.Base.GaiSummarizationView
 * @alias SUGAR.App.view.views.BaseGaiSummarizationView
 * @extends View.View
 */
({
    plugins: ['SummaryRequests', 'IngestSummaryRequests', 'EmailClientLaunch'],

    className: 'h-full',
    /**
     * @inheritdoc
     */
    initialize: function(options) {
        this._super('initialize', [options]);

        this._initProperties(options);
    },

    /**
     * Initialize Properties
     */
    _initProperties: function(options) {
        this.initConstantValues();
        this.setInitialState(options);
        this.defineStatuses();
        this.configureLoadingMessages();

        this.showGeneralLoadingMessage();

        if (this.generateOnInit) {
            this.generateSummarization();
        }
    },

    /**
     * Define statuses and constants used in the summarization process.
     * This includes use case types, summary process statuses, and HTTP error codes.
     */
    defineStatuses: function() {
        this.USE_CASE_TYPE = 'gai_summary';

        this.COMPLETED = 'completed';
        this.SUCCESS = 'success';
        this.PROCESSING = 'inProgress';
        this.INGEST_SUCCESS = 'ingestSuccess';
        this.ON_HOLD = 'onHold';
        this.PENDING = 'pending';
        this.FAILED = 'failed';
        this.READY_FOR_INGEST = 'readyForIngest';
        this.NO_TRANSLATION_FOUND = 'noTranslationFound';
        this.NOT_FOUND = 'notFound';

        this.httpErrorCode = {
            NOT_ENOUGH_DATA: 422,
            MAX_TOKEN_LIMIT_REACHED: 402,
            BACKEND_NOT_CONFIGURED: 401,
            NOT_FOUND: 404,
            NOT_ALLOWED: 403,
        };
    },

    /**
     * Configure loading messages for the summarization process.
     */
    configureLoadingMessages: function() {
        const loadingMessageDuration = 15000;
        const ellipsis = '…';
        this.loadingMessages = [
            [
                {message: app.lang.get('LBL_GAI_LOADING_GENERATING_1') + ellipsis, duration: loadingMessageDuration},
                {message: app.lang.get('LBL_GAI_LOADING_GENERATING_2') + ellipsis, duration: loadingMessageDuration},
                {message: app.lang.get('LBL_GAI_LOADING_3') + ellipsis, duration: loadingMessageDuration},
                {message: app.lang.get('LBL_GAI_LOADING_GENERATING_4') + ellipsis, duration: 0}
            ],
            [
                {message: app.lang.get('LBL_GAI_LOADING_UPDATING_1') + ellipsis, duration: loadingMessageDuration},
                {message: app.lang.get('LBL_GAI_LOADING_UPDATING_2') + ellipsis, duration: loadingMessageDuration},
                {message: app.lang.get('LBL_GAI_LOADING_3') + ellipsis, duration: loadingMessageDuration},
                {message: app.lang.get('LBL_GAI_LOADING_UPDATING_4') + ellipsis, duration: 0}
            ],
        ];

        this.loadingMessage = '';
        this.currentMessageIndex = 0;

        const moduleName = app.lang.getModuleName(this.module, {plural: false});
        const waitingLabel = app.lang.get('LBL_GAI_WAITING_FOR_INGEST');
        this.waitingMessage = app.utils.formatString(waitingLabel, [app.utils.capitalize(moduleName)]);
    },

    /**
     * Set initial state of the view.
     * @param {Object} options
     */
    setInitialState: function(options) {
        this.setMessageState();
        this.hasBulletPoints = _.includes(this.simpleSummaryModules, this.module);
        this.isDarkMode = app.utils.isDarkMode();
        this.generateOnInit = options.generateOnInit || false;

        this.loading = true;

        this.currentLanguage = null;
        this.summary = false;
        this.hasOldSummary = false;
        this.oldSummary = null;
        this.neededFollowup = null;
        this.engagedContacts = null;
        this.dateModified = null;
        this.oldTranslationSummaryVerified = false;
        this.forceRetry = false;
        this.triedRetrievingMainSummary = false;

        this.toggleShowingGeneralLoading = false;
        this.showedSummaryOnFirstProcessingResponse = false;
        this.showedWaitingMessageOnFirstIngestResponse = false;

        this.debounceIngestSuccessCallback = null;
        this.debounceIngestSuccessCallbackFromTranslate = null;
        this.debounceFetchIngestSummary = null;
    },

    /**
     * Initialize constant values used in the view.
     */
    initConstantValues: function() {
        this.simpleSummaryModules = ['Cases', 'Opportunities'];
        this.ingestSummaryModules = ['Accounts'];

        this.categoryActions = [
            {
                id: 'Emails',
                text: app.lang.get('LBL_COMPOSE_EMAIL_BUTTON_LABEL2', 'Emails'),
                action: 'createRecord',
                module: 'Emails',
            },
            {
                id: 'Calls',
                text: app.lang.get('LBL_MODULE_NAME_SINGULAR', 'Calls'),
                action: 'createRecord',
                module: 'Calls',
            },
            {
                id: 'Meetings',
                text: app.lang.get('LBL_MODULE_NAME_SINGULAR', 'Meetings'),
                action: 'createRecord',
                module: 'Meetings',
            },
            {
                id: 'Notes',
                text: app.lang.get('LBL_MODULE_NAME_SINGULAR', 'Notes'),
                action: 'createRecord',
                module: 'Notes',
            },
            {
                id: 'Tasks',
                text: app.lang.get('LBL_MODULE_NAME_SINGULAR', 'Tasks'),
                action: 'createRecord',
                module: 'Tasks',
            },
        ];
    },

    /**
     * Render select2 dropdown
     * @private
     */
    _renderDropdown: function() {
        const self = this;

        this.filterNode = this.$('.category-action');

        this.filterNode.select2({
            data: this.categoryActions,
            minimumResultsForSearch: -1,
            dropdownCssClass: 'search-related-dropdown category-action-dropdown',
            formatResult: function(item) {
                return item.text;
            },
            formatSelection: function() {
                return '';
            }
        });

        this._injectCustomIcon();

        this.filterNode.on('select2-selecting', function(e) {
            const data = e.object;

            e.preventDefault();

            self.createRecord(data);
            self.filterNode.select2('close');
            self.filterNode.select2('val', '');
        });
    },

    /**
     * Inject custom icon into the select2 dropdown from the category-action
     */
    _injectCustomIcon: function() {
        const $choice = this.$('.select2-container.category-action .select2-choice');

        if (!$choice.find('.category-action-icon').length) {
            const textColor = this.isDarkMode ? 'text-slate-500' : 'text-slate-400';

            $choice.append(
                `<i class="sicon sicon-add-line-lg absolute pointer-events-none category-action-icon ${textColor}"></i>`
            );
        }
    },

    /**
     * Create record
     *
     * @param {Object} e
     */
    createRecord: function(e) {
        const module = e.module;

        _.defer(_.bind(this._openCreateDrawer, this), module);
    },

    /**
     * Opens the drawer for creating a new record
     *
     * @param {string} module
     */
    _openCreateDrawer: function(module) {
        const model = app.data.createBean(module);
        const nextStepsCategoryIdentifier = 'Next Steps';
        const suggestedActionsCategoryIdentifier = 'Suggested Actions';

        if (module === 'Emails' && !this.useSugarEmailClient()) {
            this._launchExternalEmail();
            return;
        }
        let newRecordDesc = '';
        _.each(this.summary, function(desc, key) {
            if (key.toLowerCase().includes(nextStepsCategoryIdentifier.toLowerCase())) {
                newRecordDesc = desc;
            }
            if (key.toLowerCase().includes(suggestedActionsCategoryIdentifier.toLowerCase())) {
                newRecordDesc = desc;
            }
        });

        const noneArray = _.isArray(newRecordDesc) && newRecordDesc.length === 1 && newRecordDesc[0] === 'None';
        if (!_.isEmpty(newRecordDesc) && newRecordDesc !== 'None' && !noneArray) {
            if (_.isArray(newRecordDesc)) {
                newRecordDesc = newRecordDesc.join('\n');
            }

            model.set('description', newRecordDesc);

            if (module === 'Emails') {
                model.set('description_html', newRecordDesc);
            }
        }

        const context = this.context || {};
        const parentContext = context.parent || null;
        if (parentContext && parentContext.get('layout') === 'focus') {
            model.set('parent_id', this.model.get('id'));
            model.set('parent_type', this.module);
            model.set('parent_name', this.model.get('name'));
        }

        const createContext = {
            module: module,
            model: model,
            create: true,
        };

        const layout = module === 'Emails' ? 'compose-email' : 'create';

        app.drawer.open({
            layout: layout,
            context: createContext,
        });
    },

    /**
     * Launches the external email client
     */
    _launchExternalEmail: function() {
        window.open(this._buildMailToURL({}), '_blank');
    },

    /**
     * Parse and display summarization
     */
    showSummarization: function(summary, hasOldSummary) {
        summary = JSON.parse(summary);

        if (!summary) {
            this.showErrorMessage();

            return;
        }

        this.loading = hasOldSummary ? true : false;
        this.summary = summary;

        this.render();
        this._renderDropdown();

        if (!_.isEmpty(this.neededFollowup)) {
            this.handleOpenFocusDrawer('follow-up');
        }
        if (!_.isEmpty(this.engagedContacts)) {
            this.handleOpenFocusDrawer('engaged');
        }
    },

    /**
     * Handle clicking on a pill
     *
     * @param {string} icon
     */
    handleOpenFocusDrawer: function(icon) {
        const pillSelector = `.${icon}-pill`;

        this.$(pillSelector).click(_.bind(function(event) {
            event.preventDefault();

            const $target = $(event.currentTarget);
            const module = $target.data('module');
            const objectId = $target.data('id');
            const name = $target.data('name');

            const dataTitle = app.sideDrawer.getDataTitle(
                module,
                'LBL_FOCUS_DRAWER_DASHBOARD',
                name
            );

            const recordContext = {
                layout: 'row-model-data',
                dashboardName: name,
                context: {
                    layout: 'focus',
                    contentType: 'dashboard',
                    module: module,
                    modelId: objectId,
                    dataTitle: dataTitle,
                    fieldDefs: app.metadata.getModule(module, 'fields').name,
                    baseModelId: objectId,
                    evtSource: $target
                }
            };

            app.sideDrawer.open(recordContext, null, true);
        }, this));
    },

    /**
     * Display No Data
     */
    showNoData: function() {
        this.loading = false;

        this.render();
    },

    /**
     * Show an error, warning, or notice message based on the error status
     *
     * @param {Object} error - The error object
     */
    showErrorMessage: function(error) {
        if (!_.isUndefined(error)) {
            switch (error.status) {
                case this.httpErrorCode.MAX_TOKEN_LIMIT_REACHED:
                    this.setMessageState('warning');
                    break;
                case this.httpErrorCode.NOT_ENOUGH_DATA:
                    this.setMessageState('notice');
                    break;
                case this.httpErrorCode.BACKEND_NOT_CONFIGURED:
                    this.setMessageState('error');
                    this.errorMessage = app.lang.get('LBL_GAI_ERROR_BACKEND_NOT_CONFIGURED');
                    break;
                case this.httpErrorCode.NOT_ALLOWED:
                    this.setMessageState('info');
                    this.infoMessage = app.lang.get(error.message);
                    break;
                default:
                    this.setMessageState('error');
                    this.errorMessage = app.lang.get('LBL_GAI_ERROR_SUMMARIZATION_MESSAGE');
                    break;
            }
        } else {
            this.setMessageState('error');
            this.errorMessage = app.lang.get('LBL_GAI_ERROR_SUMMARIZATION_MESSAGE');
        }

        this.showNoData();
    },

    /**
     * Set the message state based on the provided message type.
     * If no type is provided, all message types (error, warning, notice, waiting) will be set to false.
     *
     * @param {string|null} type - The message type
     */
    setMessageState: function(type = null) {
        this.error = type === 'error';
        this.warning = type === 'warning';
        this.notice = type === 'notice';
        this.waiting = type === 'waiting';
        this.info = type === 'info';
    },

    /**
     * Show general loading message for fetching the summary from DB
     */
    showGeneralLoadingMessage: function() {
        this.loadingMessage = app.lang.get('LBL_GAI_LOADING');
        this.$('[data-identifier=loadingMessage]').text(this.loadingMessage);
    },

    /**
     * Show loading messages after the initial general loading message
     */
    showLoadingMessages: function() {
        if (!this.loading) {
            return;
        }
        const generatingMessages = 0;
        const updatingMessages = 1;

        const messages = this.hasOldSummary ?
            this.loadingMessages[updatingMessages] :
            this.loadingMessages[generatingMessages];

        if (this.currentMessageIndex >= messages.length) {
            return;
        }

        const currentMessage = messages[this.currentMessageIndex];
        this.loadingMessage = currentMessage.message;
        this.$('[data-identifier=loadingMessage]').text(this.loadingMessage);

        setTimeout(function() {
            this.currentMessageIndex++;
            this.showLoadingMessages();
        }.bind(this), currentMessage.duration);

    },

    /**
     * Start the summarization flow
     */
    generateSummarization: function() {
        if (_.includes(this.simpleSummaryModules, this.module)) {
            this.fetchInference();
        } else {
            this.ingestData();
        }
    },

    /**
     * @inheritdoc
     */
    _dispose: function() {
        if (this.debounceIngestSuccessCallback && this.debounceIngestSuccessCallback.cancel) {
            this.debounceIngestSuccessCallback.cancel();
        }

        if (this.debounceIngestSuccessCallbackFromTranslate && this.debounceIngestSuccessCallbackFromTranslate.cancel) {
            this.debounceIngestSuccessCallbackFromTranslate.cancel();
        }

        if (this.debounceFetchIngestSummary && this.debounceFetchIngestSummary.cancel) {
            this.debounceFetchIngestSummary.cancel();
        }

        this._super('_dispose');
    }
});
