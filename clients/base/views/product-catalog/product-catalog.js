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
 * @class View.Views.Base.ProductCatalogView
 * @alias SUGAR.App.view.views.BaseProductCatalogView
 * @augments View.View
 */
({
    events: {
        'keyup .product-catalog-search-term': 'onSearchTermChange',
    },

    /**
     * @inheritdoc
     */
    initialize: function(options) {
        this._super('initialize', [options]);

        this.activeFetchCt = 0;
        this.searchText = this.getSearchTextPlaceholder();
        this.dataLoaded = false;
        this.isDarkMode = app.utils.isDarkMode();

        this.initializeProviderModules();
        this.hasAccess = this._checkAccess();
    },

    /**
     * Check whether the user has access to ProductCategories and ProductTemplates
     * @returns boolean true if the user has access
     * @private
     */
    _checkAccess: function() {
        return app.acl.hasAccess('list', 'ProductCategories') && app.acl.hasAccess('list', 'ProductTemplates');
    },

    /**
     * Returns the placeholder string for the Search text input
     * @returns {string}
     */
    getSearchTextPlaceholder: function() {
        return app.lang.get('LBL_SEARCH_CATALOG_PLACEHOLDER', 'ProductTemplates');
    },

    /**
     * Initializes any modules needed for data fetching
     */
    initializeProviderModules: function() {
        this.treeModule = 'ProductTemplates';
    },

    /**
     * @inheritdoc
     */
    bindDataChange: function() {
        this._super('bindDataChange');

        // adding PC Dashlet just return
        if (this.isConfig) {
            return;
        }

        const closestComp = this._getClosestComponent();
        if (closestComp) {
            // need to trigger on app.controller.context because of contexts changing between
            // the PCDashlet, and Opps create being in a Drawer, or as its own standalone page
            // app.controller.context is the only consistent context to use
            app.controller.context.on(closestComp.cid + ':productCatalogDashlet:add:complete',
                this._onProductDashletAddComplete, this);
        }
    },

    /**
     * Gets the search term from the text input
     */
    onSearchTermChange: _.debounce(function(evt) {
        var term = $(evt.target).val().trim();

        if (term !== this.previousSearchTerm) {
            this.previousSearchTerm = term;
            this.loadData({
                searchTerm: term,
            });
        }
    }, 500),

    /**
     * Fetches tree data from the API
     * @param {object} options Configuration options
     * @param {string} options.searchTerm Optional search filter term
     * @param {string} options.parentId Optional parent category ID for subfolder loading
     * @param {number} options.offset Optional offset for pagination
     * @param {boolean} options.includeFilter Whether to include currentFilterTerm in payload
     * @param {Function} options.success Callback on success
     */
    _fetchTreeData: function(options) {
        if (!this.hasAccess) {
            return;
        }

        var callbacks;
        var method = 'create';
        var payload = {};
        var term = options && options.searchTerm;
        var parentId = options && options.parentId;
        var offset = options && options.offset;
        var includeFilter = options && options.includeFilter;
        var successCallback = (options && options.success) || this._onCatalogFetchSuccess;
        var isRootLevelFetch = !parentId;

        // Update filter term if this is a root-level search
        if (isRootLevelFetch) {
            if (term) {
                payload.filter = term;
                this.currentFilterTerm = term;
            } else {
                this.currentFilterTerm = undefined;
            }
            this.$('.product-catalog-no-results').addClass('hidden');
        }

        // Add parent ID if fetching subfolders
        if (parentId) {
            payload.root = parentId;
        }

        // Add offset if provided
        if (!_.isUndefined(offset)) {
            payload.offset = offset;
        }

        // Include search filter for subfolder loads if applicable
        if (includeFilter && !_.isUndefined(this.currentFilterTerm)) {
            payload.filter = this.currentFilterTerm;
        }

        var url = app.api.buildURL(this.treeModule + '/tree', method);

        this.toggleLoading(true);

        callbacks = {
            context: this,
            success: successCallback,
            complete: _.bind(function() {
                this.activeFetchCt--;
                if (this.disposed) {
                    return;
                }
                // when complete, remove the spinning refresh icon from the cog
                // and add back the cog icon
                this.toggleLoading(false);
            }, this),
        };

        this.activeFetchCt++;
        app.api.call(method, url, payload, null, callbacks);
    },

    /**
     * @inheritdoc
     */
    loadData: function(options) {
        this._fetchTreeData(options);
    },

    /**
     * Handles the ProductTemplates/tree endpoint response
     * and parses data to be used by the tree
     *
     * @param response
     * @protected
     */
    _onCatalogFetchSuccess: function(response) {
        this.jsTreeData = response;
        this.activeFetchCt--;

        if (this.disposed) {
            return;
        }

        if (this.activeFetchCt <= 0) {
            if (this.jsTreeData.records.length === 0) {
                this.$('.product-catalog-no-results').removeClass('hidden');
            } else {
                this.$('.product-catalog-no-results').addClass('hidden');
                this.$('.product-catalog-search-term').removeClass('hidden');
            }
            this.activeFetchCt = 0;
        }

        this.dataLoaded = true;

        const data = this.processResponse(response);
        this.renderTree(data);
    },

    processResponse(response) {
        const data = (response?.records || []).map((record) => {
            const isCategory = record.type === 'category';

            return {
                title: record.data,
                lazy: isCategory,
                children: null,
                expanded: false,
                record,
                category: record.type,
            };
        });

        return data;
    },

    renderTree(data, node) {
        this.plantTree();

        if (!node) {
            node = this.tree.root;
        }

        node.removeChildren();
        node.addChildren(data);
    },

    plantTree() {
        if (this.tree) {
            return this.tree;
        }

        const element = this.$el.find(`.product-catalog-container-${this.cid}`)[0];
        const theme = this.isDarkMode ? 'dark' : 'light';

        this.tree = new mar10.Wunderbaum({
            element,
            activate: this.onNodeActivate.bind(this),
            lazyLoad: (e) => this.loadFolder(e.node).then(() => false),
            render: this.onNodeRender.bind(this),
            iconMap: {
                expanderExpanded: 'sicon sicon-chevron-down',
                expanderCollapsed: 'sicon sicon-chevron-right',
                expanderLazy: 'sicon sicon-expand-right',
                folder: 'sicon sicon-folder',
                folderOpen: 'sicon sicon-folder-open',
                folderLazy: 'sicon sicon-folder',
                doc: 'sicon sicon-document-lg',
                loading: 'sicon sicon-clock',

                error: 'sicon sicon-multiply-line-lg',
                noData: 'sicon sicon-asterisk',
            },
            keyboard: false,

        });

        element.setAttribute('data-theme', theme);

        return this.tree;
    },

    onNodeActivate(e) {
        const {node} = e;
        const {record} = node.data;

        const target = e.event && e.event.target;

        // Disable keyboard interactions - only allow mouse/pointer events
        if (!e.event || !e.event.type || e.event.type.includes('key')) {
            return;
        }

        const isExpander = !!(target && target.classList && target.classList.contains('wb-expander'));

        if (isExpander) {
            return;
        }

        let {type} = record;

        if (target && target.classList && target.classList.contains('wb-preview-icon')) {
            type = 'preview';
        }

        switch (type) {
        case 'category':
            e.node.setExpanded(!e.node.isExpanded());
            break;
        case 'product':
            this._onTreeNodeNameClicked(record, false);
            break;
        case 'showMore':
            console.log('Show more clicked for ', record);
            break;
        case 'preview':
            this._onTreeNodePreviewClicked(record);
            break;
        default:
            break;
        }
    },

    onNodeRender(e) {
        // Add preview icon for non-category nodes
        const {node, nodeElem} = e;
        const isCategory = node.data.record?.type === 'category';

        if (!isCategory && nodeElem) {
            const titleSpan = nodeElem.querySelector('.wb-title');
            if (titleSpan && !titleSpan.querySelector('.wb-preview-icon')) {
                const previewIcon = document.createElement('span');
                previewIcon.className = 'wb-preview-icon sicon sicon-preview';
                previewIcon.title = app.lang.get('LBL_PREVIEW', 'ProductTemplates');
                titleSpan.appendChild(previewIcon);
            }
        }
    },

    loadFolder(node) {
        const categoryId = node.data.record.id;

        return new Promise((resolve) => {
            this._fetchTreeData({
                parentId: categoryId,
                offset: 0,
                includeFilter: false,
                success: (response) => {
                    const childData = this.processResponse(response);
                    node.addChildren(childData);
                    resolve(childData);
                },
            });
        });
    },

    /**
     * Toggles the spinning Loading icon on the header bar
     *
     * @param {boolean} startLoading If we should start the spinning icon or hide it
     */
    toggleLoading: function(startLoading) {
        if (startLoading) {
            this.$('.loading-icon').show();
        } else {
            this.$('.loading-icon').hide();
        }
    },

    /**
     * @inheritdoc
     *
     * Hides the view if the user does not have access to the necessary modules
     * @override
     */
    render: function() {
        if (!this.hasAccess) {
            this.template = app.template.get(this.name + '.noaccess');
        }
        this._super('render');
    },

    /**
     * When a tree item's name gets clicked
     *
     * @param {object} record The product record that was clicked
     * @protected
     */
    _onTreeNodeNameClicked: function(record) {
        // We could show a loading message here, but not all views do something with the PC data. Trigger an event to
        // let individual views decide what to do when the tree name is clicked
        var closestComp = this._getClosestComponent();
        if (!_.isUndefined(closestComp)) {
            app.controller.context.trigger(closestComp.cid + ':productCatalogDashlet:add:loading');
        }

        // Fetch the record data and send it to the applicable context
        this._fetchRecord(record.id, {
            success: _.bind(this._sendItemToRecord, this),
            complete: _.bind(function() {
                if (!_.isUndefined(closestComp)) {
                    app.controller.context.trigger(`${closestComp.cid}:productCatalogDashlet:add:loaded`);
                }
            }, this),
        });
    },

    /**
     * When a tree item's preview icon gets clicked
     *
     * @param {object} record The product record to preview
     * @private
     */
    _onTreeNodePreviewClicked: function(record) {
        // Show loading alert
        app.alert.show('fetching_product_catalog_preview', {
            level: 'process',
            title: app.lang.get('LBL_LOADING'),
            autoClose: false,
        });

        // Fetch the record data and display it in a drawer
        this._fetchRecord(record.id, {
            success: _.bind(function(data) {
                this._openItemInDrawer(data);
            }, this),
            complete: _.bind(function() {
                app.alert.dismiss('fetching_product_catalog_preview');
            }, this),
        });
    },

    /**
     * Fetchs a Record given the ID, and sends the response data to `callbacks.success`
     *
     * @param {string} id The ProductTemplate ID Hash to fetch
     * @param {object} callbacks The callback object with any success/error/complete handler functions
     * @protected
     */
    _fetchRecord: function(id, callbacks) {
        var module = this.getFetchRecordModule();
        var url = app.api.buildURL(module + '/' + id, 'read');
        app.api.call('read', url, null, null, callbacks);
    },

    /**
     * Returns the module name to use for fetching records
     * before sending them to the drawer or record
     *
     * @returns {string}
     */
    getFetchRecordModule: function() {
        return this.treeModule;
    },

    /**
     * Gets the closest component to the dashlet
     * @returns {object | null}
     * @private
     */
    _getClosestComponent: function() {
        const componentNames = [
            'record',
            'create',
            'convert',
            'records',
            'side-drawer',
            'omnichannel-dashboard',
            'dashlet-preview',
        ];
        for (const componentName of componentNames) {
            const component = this.closestComponent(componentName);
            if (component) {
                return component;
            }
        }
        return null;
    },

    /**
     * Sends the ProductTemplate data item to the record
     *
     * @param {object} data The ProductTemplate data
     * @protected
     */
    _sendItemToRecord: function(data) {
        this._massageDataBeforeSendingToRecord(data);

        const closestComp = this._getClosestComponent();
        // need to trigger on app.controller.context because of contexts changing between
        // the PCDashlet, and Opps create being in a Drawer, or as its own standalone page
        // app.controller.context is the only consistent context to use
        if (closestComp && closestComp.triggerBefore('productCatalogDashlet:add:allow')) {
            app.controller.context.trigger(closestComp.cid + ':productCatalogDashlet:add', data);
        }
    },

    /**
     * Allows `data` to be manipulated and updated before sending to the record
     *
     * @param {object} data The data we're sending to the Record
     * @protected
     */
    _massageDataBeforeSendingToRecord: function(data) {
        // copy Template's id and name to where the QLI expects them
        data.product_template_id = data.id;
        data.product_template_name = data.name;
        data.assigned_user_id = app.user.id;

        // remove ID/etc since we dont want Template ID to be the record id
        delete data.id;
        delete data.status;
        delete data.date_entered;
        delete data.date_modified;
        delete data.pricing_formula;
        delete data.my_favorite;
        delete data.sync_key;
        delete data.team_count;
        delete data.team_count_link;
        delete data.team_name;
        delete data.team_id;
        delete data.team_set_id;
    },

    /**
     * Sends the ProductTemplate data item to a Drawer layout
     *
     * @param {object} data The ProductTemplate data
     * @protected
     */
    _openItemInDrawer: function(data) {
        var model = app.data.createBean('ProductTemplates', data);
        const closestComp = this._getClosestComponent();
        model.viewId = closestComp ? closestComp.cid : null;
        app.drawer.open({
            layout: 'product-catalog-dashlet-drawer-record',
            context: {
                module: 'ProductTemplates',
                model: model,
                closestComponent: closestComp,
            },
        }, _.noop);
    },

    /**
     * Handles when sending ProductTemplate data has been complete and we can enable the tree again
     *
     * @protected
     */
    _onProductDashletAddComplete: function() {
        this.isFetchActive = false;
        this.$('#product-catalog-container-' + this.cid).removeClass('disabled');
    },

    /**
     * @inheritdoc
     */
    _dispose: function() {
        if (app.controller && app.controller.context) {
            if (this.isConfig) {
                this._super('_dispose');
                return;
            }

            const closestComp = this._getClosestComponent();
            if (closestComp) {
                app.controller.context.off(closestComp.cid + ':productCatalogDashlet:add:complete', null, this);
            }
        }
        this._super('_dispose');
    },
})
