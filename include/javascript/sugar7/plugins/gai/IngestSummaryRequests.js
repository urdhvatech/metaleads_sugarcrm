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
(function(app) {
    app.events.on('app:init', function() {
        app.plugins.register('IngestSummaryRequests', 'view', {
            /**
             * Run when the plugin is attached.
             */
            onAttach: function() {
                this.BULK_REFETCH_TIMER = 2000;
                this.INGEST_REFETCH_TIMER = 4000;
                this.INGEST_REFETCH_SCHEDULER_TIMER = 10000;
            },

            /**
             * API call to start the ingest process for a batch ingestion process for the account module
             */
            ingestData: function() {
                const success = _.bind(this.ingestSuccessCallback, this);

                const error = _.bind(this.handleErrorCallback, this);

                const apiCallbacks = {
                    success,
                    error,
                };

                const requestMeta = {
                    module: this.module,
                    id: this.model.id,
                    force: false
                };

                const apiPath = app.api.buildURL(this.module, 'intelligence/summary/ingest', requestMeta);

                app.api.call('create', apiPath, requestMeta, apiCallbacks);
            },

            /**
             * Force Ingest for delta when the ingest process takes too long and gives timeout
             */
            forceIngest: function() {
                const success = _.bind(this.ingestSuccessCallback, this);

                const error = _.bind(this.handleErrorCallback, this);

                const apiCallbacks = {
                    success,
                    error,
                };

                const requestMeta = {
                    module: this.module,
                    id: this.model.id,
                    force: true
                };

                const apiPath = app.api.buildURL(this.module, 'intelligence/summary/ingest', requestMeta);

                app.api.call('create', apiPath, requestMeta, apiCallbacks);
            },

            /**
             * Fetch Ingest Summarization
             *
             * @param {string} evalId
             */
            fetchIngestSummary: function(evalId) {
                if (!evalId) {
                    this.showErrorMessage();
                    return;
                }

                const success = _.bind(this.fetchSuccessCallback, this);

                const error = _.bind(this.fetchErrorCallback, this);

                const apiCallbacks = {success, error};

                const pathParams = {
                    module: this.module,
                    id: this.model.id
                };

                const apiPath = app.api.buildURL(
                    this.module,
                    'intelligence/summary/retrieve' + '/' + evalId,
                    pathParams
                );

                app.api.call('read', apiPath, null, apiCallbacks);
            },

            /**
             * Get the current completed  translation if present and start another ingest when necessary
             * @param {string} module
             * @param {string} id
             * @param {boolean} shouldIngest
             */
            getAsyncCurrentSummaryTranslate: function(module, id, shouldIngest) {
                const success = (response) => {
                    if (response.summary) {
                        this.hasOldSummary = true;
                        this.oldSummary = response.summary;
                        this.dateModified = response.dateModified;
                        this.neededFollowup = response.neededFollowup ? JSON.parse(response.neededFollowup) : null;
                        this.engagedContacts = response.engagedContacts ? JSON.parse(response.engagedContacts) : null;

                        this.showSummarization(this.oldSummary, true);
                    }

                    this.oldTranslationSummaryVerified = true;

                    if (shouldIngest) {
                        this.ingestData();
                    }

                    return false;
                };

                const error = () => {
                    this.oldTranslationSummaryVerified = true;

                    if (shouldIngest) {
                        this.ingestData();
                    }

                    return false;
                };

                const apiCallbacks = {
                    success,
                    error,
                };

                const pathParams = {module, id};

                const apiPath = app.api.buildURL(this.module, 'intelligence/summary/retrieve', pathParams);

                app.api.call('read', apiPath, null, apiCallbacks);
            },

            /**
             * Check for the up-to-date translation and fetch it if it's not present
             * @param {string} module - The module name
             * @param {string} id - The ID of the translation
             * @param {string} language - The language code
             */
            getAsyncSummaryTranslate: function(module, id, language) {
                const success = _.bind(this.fetchAsyncTranslationSuccessCallback, this);

                const error = _.bind(this.handleErrorCallback, this);

                const apiCallbacks = {
                    success,
                    error,
                };

                const requestMeta = {
                    module,
                    id,
                    language
                };

                const apiPath = app.api.buildURL(this.module, 'intelligence/summary/translate', requestMeta);

                app.api.call('create', apiPath, requestMeta, apiCallbacks);
            },

            /**
             * Fetchs the saved summary created in the last summarization for this record
             */
            fetchSavedSummary: function() {
                const apiCallbacks = {
                    success: (response) => {
                        if (this.disposed) {
                            return;
                        }

                        this.hasOldSummary = response.summary ?  true : false;
                        this.oldSummary = response.summary || null;
                        this.dateModified = response.dateModified || null;
                        this.neededFollowup = response.neededFollowup ? JSON.parse(response.neededFollowup) : null;
                        this.engagedContacts = response.engagedContacts ? JSON.parse(response.engagedContacts) : null;

                        this.hasOldSummary &&  this.showSummarization(this.oldSummary, false);
                    },
                    error: (error) => {
                        if (this.disposed) {
                            return;
                        }

                        this.showErrorMessage(error);
                    },
                };

                const pathParams = {
                    module: this.module,
                    id: this.model.id
                };

                const apiPath = app.api.buildURL(this.module, 'intelligence/summary/retrieve', pathParams);

                app.api.call('read', apiPath, null, apiCallbacks);
            },

            /**
             * Ingest Success Callback
             *
             * @param {Object} ingestResp
             */
            ingestSuccessCallback: function(ingestResp) {
                if (this.disposed) {
                    return;
                }

                const status = ingestResp.status;
                this.prepareDataForUI(ingestResp);

                if (status === this.FAILED) {
                    this.handleFailedIngest(ingestResp);
                    return;
                }

                if (status === this.PENDING || status === this.READY_FOR_INGEST) {
                    this.handlePendingOrReadyForIngest(ingestResp, status);
                    return;
                }

                if (status === this.ON_HOLD || status === this.COMPLETED) {
                    this.handleOnHoldOrCompleted(ingestResp);
                }

                if (status === this.INGEST_SUCCESS) {
                    this.handleIngestSuccess(ingestResp);
                }
            },

            /**
             * Error callback during the API calls
             * @param error - The error object returned from the API call
             */
            handleErrorCallback: function(error) {
                if (this.disposed) {
                    return;
                }

                if (error && error.textStatus === 'timeout') {
                    return this.forceIngest();
                }

                this.fetchSavedSummary();
                this.showErrorMessage(error);
            },

            /**
             * Fetch success callback for the summarization API
             * @param {Object} summarizationResp
             */
            fetchSuccessCallback: function(summarizationResp) {
                if (this.disposed) {
                    return;
                }

                const status = summarizationResp.status;
                const fetchedEvalId = summarizationResp.evalId;

                if (status === this.FAILED) {
                    this.showErrorMessage(errorMessage);

                    return;
                }

                if (status === this.PENDING || status === this.PROCESSING) {
                    this.debounceFetchIngestSummary = _.debounce(() => {
                        if (this.disposed) {
                            return;
                        }
                        this.fetchIngestSummary(fetchedEvalId);
                    }, this.BULK_REFETCH_TIMER)();

                    return;
                }

                const summary = summarizationResp.data.summary;
                const errorMessage = summarizationResp.errorMessage;
                this.dateModified = summarizationResp.data.date_modified;
                this.neededFollowup = JSON.parse(summarizationResp.data.needed_followup);
                this.engagedContacts = _.has(summarizationResp.data, 'engaged_contacts') ?
                    JSON.parse(summarizationResp.data.engaged_contacts) :
                    null;

                if (status === this.COMPLETED) {
                    const response = {
                        currentLanguage: summarizationResp.currentLanguage,
                        summaryLanguage: summarizationResp.data.language,
                    };

                    if (this.shouldTranslate(response)) {
                        this.ingestData();
                    } else {
                        this.showSummarization(summary, false);
                    }
                }
            },

            /**
             * Error callback for the fetch summarization API
             * @param {*} error
             */
            fetchErrorCallback: function(error) {
                if (this.disposed) {
                    return;
                }

                if (this.forceRetry === false && (error && error.status === this.httpErrorCode.NOT_FOUND)) {
                    this.forceRetry = true;
                    this.ingestData();
                    return;
                }

                if (this.triedRetrievingMainSummary && error) {
                    this.getAsyncSummaryTranslate(this.module, this.model.id, this.currentLanguage);
                    return;
                }

                this.hasOldSummary &&  this.showSummarization(this.oldSummary, false);

                if (error && error.status === this.httpErrorCode.NOT_ENOUGH_DATA && this.hasOldSummary) {
                    return;
                }

                this.showErrorMessage(error);
            },

            /**
             * Handle the success callback for fetching the async translation
             * @param {Object} ingestResp
             */
            fetchAsyncTranslationSuccessCallback: function(ingestResp) {
                if (this.disposed) {
                    return;
                }

                const status = ingestResp.status;
                this.prepareDataForUI(ingestResp);

                if (status === this.NOT_FOUND) {
                    this.ingestData();

                    return;
                }

                if (status === this.FAILED) {
                    this.handleFailedErrorMessage(ingestResp);

                    if (ingestResp.summary) {
                        this.showSummarization(ingestResp.summary, false);
                    }

                    return;
                }

                if (status === this.ON_HOLD || status === this.COMPLETED) {
                    this.showSummarization(ingestResp.summary, false);
                    return;
                }

                if (status === this.INGEST_SUCCESS) {
                    this.handleIngestSuccessNoTranslationNeeded(ingestResp);
                }
            },

            /**
             * Prepare the necessary data for the UI
             * @param response
             */
            prepareDataForUI: function(response) {
                const shouldTranslate = this.shouldTranslate(response);
                this.currentLanguage = response.currentLanguage ? response.currentLanguage : null;

                if (!shouldTranslate) {
                    this.hasOldSummary = response.summary ?  true : false;
                    this.oldSummary = response.summary || null;
                    this.dateModified = response.lastSyncDate || null;
                    this.neededFollowup = response.neededFollowup ? JSON.parse(response.neededFollowup) : null;
                    this.engagedContacts = response.engagedContacts ? JSON.parse(response.engagedContacts) : null;
                } else {
                    this.hasOldSummary = false;
                    this.oldSummary = null;
                    this.dateModified = null;
                    this.neededFollowup = null;
                    this.engagedContacts = null;
                }
            },

            /**
             * Show the waiting message for the ingest process to start
             * This is rendered only the for the first time
             */
            showWaitingMessage: function() {
                this.setMessageState('waiting');
                this.waitingForIngest = true;

                this.render();
            },

            /**
             * Handle the initial summary display if available
             * This is rendered only the for the first time
             * @param {Object} ingestResp
             */
            displayInitialSummaryIfAvailable: function(ingestResp) {
                if (ingestResp.summary  && !this.showedSummaryOnFirstProcessingResponse) {
                    this.showedSummaryOnFirstProcessingResponse = true;
                    this.showSummarization(ingestResp.summary, true);
                }
            },

            /**
             * Handles showing the waiting message if any older summary is not available
             * This is shown only once when the first ingest response is received.
             * @param {Object} ingestResp
             */
            displayWaitingForInitialSummaryMessage: function(ingestResp) {
                if (_.isUndefined(ingestResp.summary) && !this.showedWaitingMessageOnFirstIngestResponse) {
                    this.showedWaitingMessageOnFirstIngestResponse = true;
                    this.showWaitingMessage();
                    this.waiting = false;
                }
            },

            /**
             * Display the loading messages after the process of receiving a new one starts
             */
            displayLoadingMessages: function() {
                if (this.toggleShowingGeneralLoading) {
                    this.toggleShowingGeneralLoading = false;
                    this.showLoadingMessages();
                }
            },

            /**
             * Handle the debounced ingest process for the given status
             * @param {string} status
             * @param {string} callbackName
             */
            handleDebouncedIngest: function(status, callbackName) {
                const timer = status === this.PENDING ? this.INGEST_REFETCH_TIMER : this.INGEST_REFETCH_SCHEDULER_TIMER;

                this[callbackName] = _.debounce(() => {
                    if (this.disposed) {
                        return;
                    }

                    this.ingestData();
                }, timer)();
            },

            /**
             * Handle the failed ingest response
             * @param {Object} ingestResp
             */
            handleFailedIngest: function(ingestResp) {
                this.handleFailedErrorMessage(ingestResp);

                if (ingestResp.summary) {
                    if (!this.shouldTranslate(ingestResp)) {
                        this.showSummarization(ingestResp.summary, false);
                    } else {
                        this.getAsyncCurrentSummaryTranslate(this.module, this.model.id, false);
                    }
                }
            },

            /**
             * Handle the error message when the ingest process fails
             * @param {Object} ingestResp
             */
            handleFailedErrorMessage: function(ingestResp) {
                const error = {
                    message: ingestResp.errorMessage,
                    status: ingestResp.statusCode,
                };

                this.showErrorMessage(error);
            },

            /**
             * Handle the PENDING or READY_FOR_INGEST status for the infest process
             * @param {Object} ingestResp
             * @param {string} status
             */
            handlePendingOrReadyForIngest: function(ingestResp, status) {
                if (!this.shouldTranslate(ingestResp)) {
                    this.displayInitialSummaryIfAvailable(ingestResp);
                    this.displayWaitingForInitialSummaryMessage(ingestResp);
                    this.handleDebouncedIngest(status, 'debounceIngestSuccessCallback');

                    return;
                } else if (this.oldTranslationSummaryVerified) {
                    this.handleDebouncedIngest(status, 'debounceIngestSuccessCallbackFromTranslate');
                } else {
                    this.getAsyncCurrentSummaryTranslate(this.module, this.model.id, true);
                }
            },

            /**
             * Handle the ON_HOLD or COMPLETED status for the ingest process
             * @param {Object} ingestResp
             */
            handleOnHoldOrCompleted: function(ingestResp) {
                if (this.shouldTranslate(ingestResp)) {
                    this.getAsyncSummaryTranslate(this.module, this.model.id, ingestResp.currentLanguage);
                } else {
                    this.showSummarization(ingestResp.summary, false);

                    return;
                }
            },

            /**
             * Handle the INGEST_SUCCESS status for the ingest process
             * @param {Object} ingestResp
             */
            handleIngestSuccess: function(ingestResp) {
                if (!this.shouldTranslate(ingestResp)) {
                    this.handleIngestSuccessNoTranslationNeeded(ingestResp);
                } else {
                    if (this.oldTranslationSummaryVerified) {
                        //before translating we must resolve the main summary which is in INGEST_SUCCESS
                        if (this.triedRetrievingMainSummary) {
                            this.getAsyncSummaryTranslate(this.module, this.model.id, ingestResp.currentLanguage);
                        } else {
                            this.triedRetrievingMainSummary = true;
                            this.fetchIngestSummary(ingestResp.evalId);
                        }
                    } else { //check and display old translation
                        this.getAsyncCurrentSummaryTranslate(this.module, this.model.id, true);
                    }
                }
            },

            /**
             * Handle the INGEST_SUCCESS status when no translation is needed
             * @param {Object} ingestResp
             */
            handleIngestSuccessNoTranslationNeeded: function(ingestResp) {
                this.toggleShowingGeneralLoading = true;
                this.displayInitialSummaryIfAvailable(ingestResp);
                this.displayLoadingMessages();
                this.fetchIngestSummary(ingestResp.evalId);
            },

            /**
             * Check if the current language is the same as the summary language
             * @param {Object} response
             */
            sameLanguage: function(response) {
                return response.currentLanguage === response.summaryLanguage;
            },

            /**
             * Determines whether the summary should be translated
             * @param {Object} response - The response object containing language data.
             * @return {boolean}
             */
            shouldTranslate(response) {
                const summaryLanguageExists = !_.isEmpty(response.summaryLanguage);
                return summaryLanguageExists && !this.sameLanguage(response);
            }
        });
    });
})(SUGAR.App);
