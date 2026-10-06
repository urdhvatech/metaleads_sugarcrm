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
        app.plugins.register('SummaryRequests', 'view', {
            /**
             * Run when the plugin is attached.
             */
            onAttach: function() {
                this.REFETCH_TIMER = 2000;
            },

            /**
             * API call to start the ingest process
             */
            fetchInference: function() {
                const success = (inferenceResp) => {
                    if (this.disposed) {
                        return;
                    }

                    const status = inferenceResp.status;

                    if (status === this.FAILED) {
                        const errorMessage = inferenceResp.errorMessage;

                        this.showErrorMessage(errorMessage);

                        return;
                    }

                    const evalId = inferenceResp.evalId;

                    this.hasOldSummary = inferenceResp.summary ?  true : false;
                    this.oldSummary = inferenceResp.summary || null;
                    this.dateModified = inferenceResp.dateModified || null;

                    this.toggleShowingGeneralLoading = true;

                    status === this.COMPLETED && this.fetchSummarization(evalId);
                };

                const error = (error) => {
                    if (this.disposed) {
                        return;
                    }

                    this.fetchSavedSummary();
                    this.showErrorMessage(error);
                };

                const apiCallbacks = {
                    success,
                    error
                };

                const requestMeta = {
                    module: this.module,
                    id: this.model.id
                };

                const apiPath = app.api.buildURL(this.module, 'intelligence/summary/infer', requestMeta);

                app.api.call('create', apiPath, requestMeta, apiCallbacks);
            },

            /**
             * Fetch Summarization
             *
             * @param {string} evalId
             */
            fetchSummarization: function(evalId) {
                if (!evalId) {
                    this.showErrorMessage();
                    return;
                }

                const success = (summarizationResp) => {
                    if (this.disposed) {
                        return;
                    }

                    const status = summarizationResp.status;
                    const fetchedEvalId = summarizationResp.evalId;

                    if (status === this.FAILED) {
                        this.showErrorMessage(errorMessage);

                        return;
                    }

                    if (status === this.PROCESSING) {
                        if (this.hasOldSummary  && !this.showedSummaryOnFirstProcessingResponse) {
                            this.showedSummaryOnFirstProcessingResponse = true;
                            this.showSummarization(this.oldSummary, true);
                        }

                        if (this.toggleShowingGeneralLoading) {
                            this.toggleShowingGeneralLoading = false;
                            this.showLoadingMessages();
                        }

                        _.debounce(() => {
                            if (this.disposed) {
                                return;
                            }
                            this.fetchSummarization(fetchedEvalId);
                        }, this.REFETCH_TIMER)();

                        return;
                    }

                    const summary = summarizationResp.data.summary;
                    const errorMessage = summarizationResp.errorMessage;
                    this.dateModified = summarizationResp.data.date_modified;

                    status === this.COMPLETED && this.showSummarization(summary, false);
                };

                const error = (error) => {
                    if (this.disposed) {
                        return;
                    }

                    this.hasOldSummary &&  this.showSummarization(this.oldSummary, false);

                    if (error && error.status === this.httpErrorCode.NOT_ENOUGH_DATA && this.hasOldSummary) {
                        //we don't have enough data to process the ingest, so we will show only the old summary
                        return;
                    }

                    this.showErrorMessage(error);
                };

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
             * Fetchs the saved summary created in the last summarization for this record
             */
            fetchSavedSummary: function() {
                const apiCallbacks = {
                    success: (reponse) => {
                        if (this.disposed) {
                            return;
                        }

                        this.hasOldSummary = reponse.summary ?  true : false;
                        this.oldSummary = reponse.summary || null;
                        this.dateModified = reponse.dateModified || null;

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
        });
    });
})(SUGAR.App);
