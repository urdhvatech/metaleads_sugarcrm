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
 * @class View.Views.Base.AuditView
 * @alias SUGAR.App.view.views.BaseAuditView
 * @augments View.Views.Base.FilteredListView
 */
({
    extendsFrom: 'FilteredListView',

    fallbackFieldTemplate: 'list',

    /**
     * @inheritdoc
     * Assign base module and record id.
     * Override the new Audit collection
     * in order to fetch correct audit end-point.
     */
    initialize: function(options) {
        // in order to render the 'list' template on each field
        this.action = 'list';
        // populating metadata for audit module
        if (options.context.parent) {
            this.baseModule = options.context.parent.get('module');
            this.baseRecord = options.context.parent.get('modelId');
        }
        this._super('initialize', [options]);

        // Always override orderBy: audit sorts by date_created, not date_modified
        this.orderBy = {field: 'date_created', direction: 'desc'};

        if (!this.collection) {
            this._initCollection();
            this.context.set('collection', this.collection);
            // Audit doesn't use collection-count field, so use custom fetchTotal
            this.context.set('noCollectionField', true);
        }
    },

    /**
     * Override the collection set up by new audit REST end-point.
     * @private
     */
    _initCollection: function() {
        var self = this;
        var AuditCollection = app.BeanCollection.extend({
            module: 'audit',
            baseModule: this.baseModule,
            baseRecordId: this.baseRecord,

            // FIXME PX-46: remove this function
            buildURL: function(params) {
                params = params || {};

                const parts = [];
                let url;
                parts.push(app.api.serverUrl);
                parts.push(this.baseModule);
                parts.push(this.baseRecordId);
                parts.push(this.module);
                url = parts.join('/');
                params = $.param(params);
                if (params.length > 0) {
                    url += '?' + params;
                }
                return url;
            },
            sync: function(method, model, options) {
                var auditedModel = self.context.get('model');

                // Build query parameters for pagination
                const {
                    offset = 0,
                    limit = app.config.maxQueryResult || 50,
                } = options ?? {};
                const params = {
                    offset,
                    max_num: limit,
                    search: this.searchTerm ?? '',
                    audit_order_by: self.orderBy?.field ?? null,
                    audit_order_direction: self.orderBy?.direction ?? '',
                };

                const url = this.buildURL(params);
                const callbacks = app.data.getSyncCallbacks(method, model, options);
                const defaultSuccessCallback = app.data.getSyncSuccessCallback(method, model, options);
                callbacks.success = (data, request) => {
                    self._applyModelDataOnRecords(auditedModel, data.records);
                    // Mark collection as fetched for list-pagination to display
                    this.dataFetched = true;
                    return defaultSuccessCallback(data, request);
                };
                app.api.call(method, url, options.attributes, callbacks);
            },
            parse: function(data) {
                // Extract records array from {records: [...], next_offset: X} response
                return data?.records ?? data;
            },
            fetchTotal: function(options) {
                options = options || {};

                // If total is already set, return it
                if (!_.isNull(this.total) && _.isFunction(options.success)) {
                    options.success.call(this, this.total);
                    return;
                }

                const countParams = {
                    search: this.searchTerm || '',
                };
                const url = app.api.buildURL(
                    this.baseModule + '/' + this.baseRecordId + '/' + this.module + '/count',
                    null,
                    null,
                    countParams,
                );

                const callbacks = {
                    success: (data) => {
                        this.total = parseInt(data?.record_count, 10) || 0;
                        options.success?.(this.total);
                    },
                    complete: options.complete,
                    error: options.error,
                };

                return app.api.call('read', url, null, callbacks);
            },
        });
        this.collection = new AuditCollection();

        // Set default limit for pagination
        const limit = app.config.maxQueryResult || 50;
        this.collection.setOption('limit', limit);
    },

    /**
     * Filter the metadata in order to initiate the searchable fields.
     * @protected
     */
    _initFilter: function() {
        var filter = this._filter || _.chain(this.getFields())
            .filter(function(field) {
                return field.filter;
            })
            .map(function(field) {
                return {
                    name: field.name,
                    label: app.lang.get(field.label, this.module),
                    filter: field.filter,
                    type: field.type,
                };
            }, this)
            .value();
        this.context.trigger('filteredlist:filter:set', _.pluck(filter, 'label'));

        if (_.isEmpty(filter)) {
            return;
        }
        this._filter = filter;
    },

    /**
     * @override
     *
     * The audit view delegates all filtering to the server via
     * {@link setSearchTerm} + {@link collection.fetch}.  The server uses
     * LIKE '%term%' across all searchable columns, which is both more
     * accurate and more permissive than the base class's per-field regex
     * patterns (e.g. 'startsWith').  Re-applying those patterns here would
     * incorrectly prune records that the server legitimately matched —
     * for example, searching "ast" would eliminate rows whose field label
     * is "Last Name" because the label does not *start with* "ast".
     *
     * No-op: _renderData already sets filteredCollection = collection.models
     * (all server-filtered results) before calling this method.
     */
    filterCollection: function() {
        // intentional no-op — filtering is handled server-side
    },

    /**
     * Apply erased field information from the model to records.
     *
     * @private
     */
    _applyModelDataOnRecords: function(model, records) {
        var erasedFields = model.get('_erased_fields');
        _.each(erasedFields, function(erasedField) {
            // Apply erased fields only for records that are marked
            var erasedFieldName = erasedField.field_name || erasedField;

            var properties;
            var recordsRequiringErasedFields;
            if (erasedField.field_name) {
                // email and other non-scalar erased fields
                // check both the before and after fields
                // of each record to see if it matches up with
                // an erased email's ID, and if so mark that field as erased
                var fieldsToCheck = ['before', 'after'];
                _.each(fieldsToCheck, function(fieldToCheck) {
                    properties = {field_name: erasedFieldName};
                    properties[fieldToCheck] = erasedField.id;
                    recordsRequiringErasedFields = _.where(records, properties);
                    _.each(recordsRequiringErasedFields, function(record) {
                        record._erased_fields = record._erased_fields || [];
                        record._erased_fields.push(fieldToCheck);
                    });
                });
            } else {
                properties = {field_name: erasedFieldName};
                recordsRequiringErasedFields = _.where(records, properties);
                _.each(recordsRequiringErasedFields, function(record) {
                    record._erased_fields = ['before', 'after'];
                });
            }
        });
    },

    /**
     * @override
     * Override client-side sort with server-side sort via re-fetch.
     * Triggers 'list:sort:fire' on the layout so list-pagination clears its
     * cache and resets to page 1 before the fetch result arrives.
     */
    setOrderBy: function(event) {
        var orderBy = this.$(event.currentTarget).data('fieldname');
        if (!orderBy) {
            return;
        }
        if (orderBy === this.orderBy.field) {
            this.orderBy.direction = this.orderBy.direction === 'desc' ? 'asc' : 'desc';
        } else {
            this.orderBy.field = orderBy;
            this.orderBy.direction = 'desc';
        }
        this.collection.orderBy = this.orderBy;
        this.collection.total = null;
        this.collection.dataFetched = false;
        if (_.isFunction(this.collection.resetPagination)) {
            this.collection.resetPagination();
        }
        if (this.layout) {
            this.layout.trigger('list:sort:fire');
        }
        this.collection.fetch();
    },

    /**
     * Re-fetch the collection with the given search term applied server-side.
     * Called when the user types in the filtered-search input.
     * Calls collection.resetPagination() (when available) and triggers
     * 'list:pagination:reset' (not 'list:sort:fire') to reset the
     * list-pagination view's cache without firing the sort event, which
     * would cause unrelated side effects such as closing the preview panel.
     * @param {string} term
     */
    setSearchTerm: function(term) {
        this.searchTerm = term;
        this.collection.searchTerm = term;
        this.collection.total = null;
        if (_.isFunction(this.collection.resetPagination)) {
            this.collection.resetPagination();
        }
        if (this.layout) {
            this.layout.trigger('list:pagination:reset');
        }
        this.collection.dataFetched = false;
        this.collection.fetch();
    },

    /**
     * @inheritdoc
     * Instead of fetching context, it fetches the collection directly.
     * Guard against double-fetch: the parent layout's loadData() already calls
     * context.loadData() (which sets _fetchCalled synchronously) before
     * iterating its components. Checking context.isDataFetched() catches both
     * the in-flight case (_fetchCalled) and the already-done case (dataFetched).
     */
    loadData: function() {
        if (this.context.isDataFetched()) {
            return;
        }
        this.collection.fetch();
    },

    /**
     * @inheritdoc
     *
     * Patch audit models `before` and `after` fields with information of
     * original field available within parent model, in order to render
     * properly.
     */
    _renderData: function() {
        var parentModule = this.context.parent.get('module');
        var fields = app.metadata.getModule(parentModule).fields;

        _.each(this.collection.models, function(model) {
            model.fields = app.utils.deepCopy(this.metaFields);

            var before = _.findWhere(model.fields, {name: 'before'});
            _.extend(before, fields[model.get('field_name')], {name: 'before'});

            var after = _.findWhere(model.fields, {name: 'after'});
            _.extend(after, fields[model.get('field_name')], {name: 'after'});

            // relate fields can be stored in the audit log as id, relate, or varchar.
            // Make sure they get rendered as relate.
            var baseField = fields[model.get('field_name')];
            if (baseField && _.contains(['id', 'relate'], baseField.type)) {
                before.type = 'relate';
                after.type = 'relate';
            }

            // FIXME: Temporary fix due to time constraints, proper fix will be addressed in TY-359
            // We can check just `before` since `before` and `after` refer to same field
            if (_.contains(['multienum', 'enum'], before['type']) && before['function']) {
                before['type'] = 'base';
                after['type'] = 'base';
            }

            // FIXME: This method should not be used as a public method (though
            // it's being used everywhere in the app) this should be reviewed
            // when SC-3607 gets in
            model.fields = app.metadata._patchFields(
                this.module,
                app.metadata.getModule(this.module),
                model.fields,
            );
        }, this);

        this._super('_renderData');
    },

    /**
     * @inheritdoc
     *
     * Reset the context's load flag and remove the collection from the context
     * so that reopening the audit drawer always creates a fresh collection
     * (via _initCollection) with a valid view reference in its sync closure.
     * Without this, the same context is reused on reopen, the stale collection
     * is picked up from the context, and its sync closure holds a reference to
     * the disposed view (self.context === null), causing a null-dereference.
     */
    _dispose: function() {
        this.context.resetLoadFlag({recursive: false});
        this.context.unset('collection');
        this._super('_dispose');
    },
})
