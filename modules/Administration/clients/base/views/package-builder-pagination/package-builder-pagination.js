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
 * @class View.Views.Base.AdministrationPackageBuilderPaginationView
 * @alias SUGAR.App.view.views.BaseAdministrationPackageBuilderPaginationView
 * @extends View.Views.Base.ListPaginationView
 */
({
    extendsFrom: 'ListPaginationView',

    /**
     * Number of rows to display
     */
    limit: 50,

    /**
     * @inheritdoc
     */
    initialize: function(options) {
        this._super('initialize', [options]);
        this.template = app.template.getView('list-pagination');
        this.tableData = options.tableData;

        this.initCollection();
    },

    /**
     * Initialize empty collection
     */
    initCollection: function() {
        this.collection = app.data.createBeanCollection('package-builder-content');
        this.collection.dataFetched = true;
        this.collection.setOption('limit', this.limit);
        this.context.set('collection', this.collection);
    },

    /**
     * @inheritdoc
     */
    bindLayoutEvents: function() {},

    /**
     * @inheritdoc
     */
    getPage: function(page) {
        this.page = page;

        const limit = this.collection.getOption('limit');
        const from = (page - 1) * limit;
        const to = page * limit;

        const indexes = this.context.get('matchingRows').slice(from, to);
        let newList = [];

        indexes.map((index) => {
            newList.push(this.tableData[index]);
        });

        this.collection.reset(newList);
        this.context.trigger('list:paginate', page);
        this.render();
    },

    /**
     * @inheritdoc
     */
    getPageCount: function() {
        this.pageTotalFetched(this.context.get('matchingRows').length);
    },

    /**
     * @inheritdoc
     */
    handleListFilter: function() {
        this.getPageCount();
        this.getFirstPage(true);
    },
})
