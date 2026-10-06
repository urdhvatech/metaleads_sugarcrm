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
 * @class View.Layouts.Reports.ReportSideDrawerPreviewLayout
 * @alias SUGAR.App.view.layouts.ReportsReportSideDrawerPreviewLayout
 */
({
    /**
     * @inheritdoc
     */
    render: function() {
        this._super('render');

        this.renderPreview();

        this.$('.preview-headerbar h1').toggleClass('ml-5', true);
        this.$('.preview-headerbar').toggleClass('justify-between', true);
    },

    /**
     * Renders the preview dialog with the data from the current model and collection
     * @param model Model for the object to preview
     * @param newCollection Collection of related objects to the current model
     */
    renderPreview: function(model, newCollection) {
        const listComponent = this._getListComponent();

        let context = new app.Context();
        context.set('model', this.model);
        context.set('module', listComponent.module);
        context.set('collection', listComponent.collection);

        const previewLayout = app.view.createLayout({
            type: 'preview',
            name: 'preview',
            context: context,
            layout: this.layout,
            meta: {
                'components': [
                    {
                        view: 'preview-header',
                        context: context,
                    },
                    {
                        view: 'preview',
                        context: context,
                    },
                ],
                'editable': true,
            }
        });

        previewLayout.initComponents();
        previewLayout.render();

        this.$el.html(previewLayout.$el);
        this._disposeEvents();
    },

    /**
     * Retrieves the list component from the layout
     * @returns {View.View}
     */
    _getListComponent: function() {
        const previewError = app.lang.get('LBL_PREVIEW_ERROR');
        let listComponent = null;

        if (this.layout && this.layout.layout) {
            const listSide = this.layout.layout.getComponent('list-side');
            if (listSide) {
                listComponent = listSide.getComponent('drillthrough-list');
            }
        }

        if (!listComponent) {
            app.alert.show('preview_error', {
                level: 'error',
                messages: previewError
            });
        }

        return listComponent;
    },


    /**
     * Disposes of the events
     */
    _disposeEvents: function() {
        app.events.off('preview:render', this.renderPreview, this);
    },

    /**
     * @inheritdoc
     */
    _dispose: function() {
        this._disposeEvents();
        this._super('_dispose');
    }
});
