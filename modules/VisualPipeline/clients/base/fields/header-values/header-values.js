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
 * @class View.Fields.Base.VisualPipeline.HeaderValuesField
 * @alias SUGAR.App.view.fields.BaseVisualPipelineHeaderValuesField
 * @extends View.Fields.Base.BaseField
 */
({
    extendsFrom: 'BaseField',

    /**
     * @inheritdoc
     */
    bindDataChange: function() {
        this.model.on('change:table_header', this.render, this);
    },

    /**
     * @inheritdoc
     */
    _render: function() {
        if (!_.isEmpty(this.context)) {
            this.context.set('selectedValues', {});
        }

        this.populateHeaderValues();
        this._super('_render');
        this.handleDraggableActions();
    },

    /**
     * Populates the whitelist and blacklist sections based on the hidden_values config
     */
    populateHeaderValues: function() {
        var tableHeader = this.model.get('table_header');
        var module = this.model.get('enabled_module');
        var fields = app.metadata.getModule(module, 'fields');

        var translated = app.lang.getAppListStrings((fields[tableHeader] || {}).options);
        translated = _.pick(translated, _.identity); // remove empty values

        if (!_.isEmpty(tableHeader) && _.isEmpty(translated)) {
            // call enum api
            app.api.enumOptions(module, tableHeader, {
                success: _.bind(function(data) {
                    if (!this.disposed) {
                        this._createHeaderValueLists(tableHeader, data);
                        this._super('_render');
                        this.handleDraggableActions();
                    }
                }, this)
            });
        }

        this._createHeaderValueLists(tableHeader, translated);
    },

    /**
     * Creates whitelist and blacklist of header values.
     *
     * @param {string} tableHeader Header name
     * @param {Array} translated List of options
     */
    _createHeaderValueLists: function(tableHeader, translated) {
        var whiteListedKeys = [];
        var blackListedKeys = [];
        var whiteListed = [];
        var blackListed = [];
        var availableItems = {
            [tableHeader]: {}
        };

        if (!_.isEmpty(tableHeader) && !_.isEmpty(translated)) {
            let availableSortValues = this._getAvailableColumnNames(tableHeader);
            let allAvailableValues = availableSortValues || this.model.get('available_columns') || {};
            let availableValues = allAvailableValues[tableHeader] || {};

            if (_.isArray(availableValues)) {
                /*  there are some data stored in the DB as an array with simple numeric indices,
                    so we have to work with values and not keys */
                whiteListedKeys = _.intersection(_.values(availableValues), _.values(translated));
                blackListedKeys = _.difference(_.values(translated), _.values(availableValues));
            } else {
                /*  due to the variety in the spelling of some number-like keys in DB ('0', 0, '00'),
                    the comparison functions may not work correctly, so unification is needed */
                let keysAvailabled = _.keys(availableValues).map(key => isNaN(key) ? key : Number(key));
                let keysTranslated = _.keys(translated).map(key => isNaN(key) ? key : Number(key));
                whiteListedKeys = _.intersection(keysAvailabled, keysTranslated);
                blackListedKeys = _.difference(keysTranslated, keysAvailabled);
            }

            _.each(whiteListedKeys, key => {
                let val = _.isArray(availableValues) ? key : translated[key];
                whiteListed.push({
                    key: key,
                    translatedLabel: val
                });
                availableItems[tableHeader][key] = val;
            });

            _.each(blackListedKeys, key => {
                let val = _.isArray(availableValues) ? key : translated[key];
                blackListed.push({
                    key: key,
                    translatedLabel: val
                });
            });
        }

        this.model.set('available_columns_edited', availableItems);
        this.model.set('hidden_values', blackListedKeys);

        this.model.set({
            'white_listed_header_vals': whiteListed,
            'black_listed_header_vals': blackListed
        });
    },

    /**
     * Handles the dragging of the items from the white list to the black list section
     */
    handleDraggableActions: function() {
        this.$('#pipeline-sortable-1, #pipeline-sortable-2').sortable({
            connectWith: '.connectedSortable',
            update: _.bind(function(evt, ui) {
                let whiteListed = this._getWhiteListedArray();
                let $item = $(ui.item);
                let moduleName = $item.closest('.header-values-wrapper').data('modulename');
                let model = _.find(this.collection.models, function(item) {
                    if (item.get('enabled_module') === moduleName) {
                        return item;
                    }
                });

                if (_.isArray(whiteListed)) {
                    model.set('available_values', whiteListed);
                    this._getAvailableColumnNames(model.get('table_header'));
                }
            }, this),
            receive: _.bind(function(event, ui) {
                var $item = $(ui.item);
                var movedItem = $item.data('headervalue');
                var movedInColumn = $item.parent().data('columnname');
                var moduleName = $item.closest('.header-values-wrapper').data('modulename');
                var model = _.find(this.collection.models, function(item) {
                    if (item.get('enabled_module') === moduleName) {
                        return item;
                    }
                });
                var blackListed = this.getBlackListedArray();
                let whiteListed = this._getWhiteListedArray();

                if (movedInColumn === 'black_list') {
                    blackListed.push(movedItem);
                    whiteListed = whiteListed.filter(item => item !== movedItem);
                }

                if (movedInColumn === 'white_list') {
                    whiteListed.push(movedItem);
                    var index = _.indexOf(blackListed, movedItem);
                    if (index > -1) {
                        blackListed.splice(index, 1);
                    }
                }

                if (blackListed instanceof Array) {
                    model.set('hidden_values', blackListed);
                }

                if (_.isArray(whiteListed)) {
                    model.set('available_values', whiteListed);
                    this._getAvailableColumnNames(model.get('table_header'));
                }
            }, this)
        });
    },

    /**
     * Return the list of fields that are black listed based on the hidden_value config
     * @return {Array} The black listed fields
     */
    getBlackListedArray: function() {
        var blackListed = this.model.get('hidden_values');
        if (_.isEmpty(blackListed)) {
            blackListed = [];
        }
        if (!(blackListed instanceof Array)) {
            blackListed = JSON.parse(blackListed);
        }

        return blackListed;
    },

    /**
     * Return the list of fields that are white listed
     * @return {Array} The white listed fields
     * @private
     */
    _getWhiteListedArray: function() {
        let whiteListed = [];
        let $elemList = this.$('#pipeline-sortable-1 li');

        _.each($elemList, itemElem => {
            let key = $(itemElem).data('headervalue');
            whiteListed[key] = itemElem.innerText.trim();
        });

        return whiteListed;
    },

    /**
     * Gets the list of all the available columns in the exact order
     *
     * @param {string} tableHeader Header name
     * @return {Object|null} List of available whitelisted column names
     * @private
     */
    _getAvailableColumnNames: function(tableHeader) {
        let availableColumns = this.model.get('available_values');

        if (!availableColumns) {
            return null;
        }

        let availableColumnsEdited = this._setAvailableColumnsEdited(tableHeader, availableColumns);

        return availableColumnsEdited;
    },

    /**
     * Sets in the model the initial whitelist for columns
     *
     * @param {string} tableHeader Header name
     * @param {Array} availableColumns Available columns
     * @return {Object|null} List of available whitelisted column names
     * @private
     */
    _setAvailableColumnsEdited: function(tableHeader, availableColumns) {
        let availableColumnsEdited = {
            [tableHeader]: {}
        };

        for (let key in availableColumns) {
            availableColumnsEdited[tableHeader][key] = availableColumns[key];
        }

        this.model.set('available_columns_edited', availableColumnsEdited);

        return availableColumnsEdited;
    },

    /**
     * @inheritdoc
     */
    _dispose: function() {
        this.model.off('change:table_header', null, this);

        this._super('_dispose');
    }
});
