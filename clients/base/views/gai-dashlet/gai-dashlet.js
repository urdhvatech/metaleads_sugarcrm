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
 * @class View.Views.Base.SummarizationDashletView
 * @alias SUGAR.App.view.views.BaseSummarizationDashletView
 * @extends View.View
 */
({
    plugins: ['Dashlet', 'GridBuilder'],

    /**
     * @inheritdoc
     */
    initDashlet: function(viewName) {
        this._mode = viewName;
        this.isDarkMode = app.utils.isDarkMode();
        this.usageTokensView = null;
        this.guideURL = 'https://support.sugarcrm.com/sl/ai/';
        this._contextModel = null;


        this.setRelatedModulesConfig();

        this._noAccessTemplate = app.template.get(this.name + '.noaccess');
    },

    /**
     * Set the related modules configuration for the dashlet.
     */
    setRelatedModulesConfig: function() {
        this.availableModules = ['Accounts', 'Cases', 'Opportunities'];
        this.relatedModules = {
            'Opportunities': {
                labels: ['LBL_GAI_INFO_OPP_3', 'LBL_GAI_INFO_5'],
                relatedModulesRl: ['Emails', 'Calls', 'Meetings', 'Commentlog', 'Notes']
            },
            'Cases': {
                labels: ['LBL_GAI_INFO_CASE_3', 'LBL_GAI_INFO_5'],
                relatedModulesRl: ['Emails', 'Notes', 'Calls', 'Meetings', 'Bugs', 'Commentlog', 'Escalations']
            },

            'Accounts': {
                labels: ['LBL_GAI_INFO_ACC_3', 'LBL_GAI_INFO_ACC_5'],
                relatedModulesRl: [
                    'Opportunities',
                    'RevenueLineItems',
                    'Leads',
                    'PurchasedLineItems',
                    'Quotes',
                    'Cases',
                    'Emails',
                    'Calls',
                    'Meetings',
                    'Tasks',
                    'Notes',
                ]
            },
        };
    },

    /**
     * @inheritdoc
     */
    _render: function() {
        this._super('_render');

        this._updateHeader();

        if (!app.user.hasGaiLicense()) {
            this._mapsEnabled = false;

            this._noAccess();

            _.defer(_.bind(this._disableInfoButton, this));

            return;
        }

        if (!this.meta.config && !this.meta.preview) {
            this._createAndShowSummarization();

            this._notifyUserAboutSummarizationIfAllowed();
        }
    },

    /**
     * Disables the info button in the dashlet header
     * This is used when the user does not have a GAI license
     */
    _disableInfoButton: function() {
        const dashletContainer = this.$el.closest('.dashlet-container');
        const showInfoButton = dashletContainer.find('.dashlet-info');

        showInfoButton.addClass('disabled');
    },

    /**
     * Renders the no-access template, then aborts further rendering.
     *
     * @return {boolean} Always returns `false`.
     * @private
     */
    _noAccess: function() {
        this.template = this._noAccessTemplate;

        this.$el.empty();
        this.$el.html(this.template(this));

        return false;
    },

    /**
     * If allowed, notify the user of summarization features
     */
    _notifyUserAboutSummarizationIfAllowed: function() {
        // In-app guidance can be integrated here (e.g. Gainsight PX).
    },

    /**
     * Adjust the dashlet title to account for icon, text and buttons
     */
    _updateHeader: function() {
        const dashletToolbar = this._getDashletToolbar();

        if (!dashletToolbar || !dashletToolbar._handleCustomToolbarChange) {
            return;
        }

        const headerFields = this._getHeaderFields();

        this._adjustTitleParams = [headerFields, [], this.dashModel, this];

        dashletToolbar._handleCustomToolbarChange(...this._adjustTitleParams);

        _.defer(() => this.adjustCustomDashletTitle());
    },

    /**
     * Adjust the dashlet title width to fit the toolbar taking into account the icon
     */
    adjustCustomDashletTitle: function() {
        const dashletToolbar = this._getDashletToolbar();

        if (!dashletToolbar || !dashletToolbar.adjustCustomDashletTitle) {
            return;
        }

        dashletToolbar.adjustCustomDashletTitle();
    },

    /**
     * Check if the popover is visible
     */
    _isUserTokenPopoverVisible: function() {
        if (!this.showInfoButton) {
            return false;
        }

        if (!this.showInfoButton.attr('aria-describedby')) {
            return false;
        }

        const popoverId = '#' + this.showInfoButton.attr('aria-describedby');

        return $(popoverId).is(':visible');
    },

    /**
     * Initialize and display the popover containing dashlet information.
     */
    showInfoPopup: function() {
        this.setModuleInformation();

        const dashlet = this.$el.closest('.dashlet-container');
        const showInfoButton = dashlet.find('.dashlet-info');

        this.showInfoButton = showInfoButton;

        if (this._isUserTokenPopoverVisible()) {
            this._disposePopover();

            return;
        }

        this.toggleButtonVisibility(true);

        showInfoButton.popover(this.getPopoverOptions());

        showInfoButton.one('shown.bs.popover', _.bind(this.handlePopoverShown, this));
        showInfoButton.on('hidden.bs.popover', _.bind(function() {
            this.removePopoverEvents();
            this.toggleButtonVisibility(false);
        }, this));

        showInfoButton.popover('show');

        this.handleCloseFromPopover();
    },

    /**
     * Set the module information with the related modules for the popover
     */
    setModuleInformation: function() {
        const {labels: [langKey, langKeyAsterisk], relatedModulesRl} = this.relatedModules[this.module];

        const moduleName = app.lang.getModuleName(this.module, {plural: false}).toLowerCase();
        const relatedModulesName = relatedModulesRl.map(relatedModuleRl =>
            app.lang.getModuleName(relatedModuleRl, {plural: false}).toLowerCase()
        );

        this.module_information = app.lang.get(langKey);
        this.module_information = app.utils.formatString(this.module_information, [moduleName, ...relatedModulesName]);

        this.module_information_asterisk = app.lang.get(langKeyAsterisk);
    },

    /**
     * Set the options for the popover that will be displayed when clicking on the info button.
     * This includes the content, title, and other configurations for the popover.
     * @return {Object} options - The options for the popover
     */
    getPopoverOptions: function() {
        const infoBody = app.template.getView('gai-dashlet', 'gai-info-body');
        const infoTemplate = app.template.getView('gai-dashlet', 'gai-info-template');
        const infoTitle = app.template.getView('gai-dashlet', 'gai-info-title');

        const options = {
            animation: false,
            content: infoBody({
                module_information: this.module_information,
                module_information_asterisk: this.module_information_asterisk,
                url: this.guideURL,
                darkMode: this.isDarkMode,
            }),
            html: true,
            title: infoTitle(),
            trigger: 'click',
            container: 'body',
            template: infoTemplate(),
            popperConfig: {
                placement: 'bottom-end',
                modifiers: [{
                    name: 'flip',
                    options: {
                        fallbackPlacements: ['auto', 'top-end', 'left-end', 'right-end'],
                    },
                },],
            },
        };

        return options;
    },

    /**
     * Create the token usage view for the popover
     */
    createTokenView: function() {
        const isAdmin = (app.user.get('type') === 'admin');
        const isOwner = this.dashModel.get('assigned_user_id') === app.user.id;

        if (!isAdmin && !isOwner) {
            return;
        }

        const popoverId = '#' + this.showInfoButton.attr('aria-describedby');

        const contextModel = this._getContextModel();

        const tokenView = app.view.createView({
            type: 'gai-info-token-usage',
            context: this.context,
            model: contextModel,
        });

        tokenView.render();

        const popoverEl = $(popoverId);

        if (!popoverEl.length) {
            return;
        }

        //we have to use a class instead of an attribute because
        //the popover is removing the attributes once it's added on  the DOM
        const popoverUsageTokenContainer = popoverEl.find('.gai-token-usage-container');
        popoverUsageTokenContainer.append(tokenView.$el);

        this.usageTokensView = tokenView;
    },

    /**
     * Handle popover shown event. Add event listeners for the popover,and set the position of the popover when necesary
     */
    handlePopoverShown: function() {
        this.addPopoverEvents();

        this.currentPopoverId = this.showInfoButton.attr('aria-describedby');
    },

    /**
     * Add event listeners for showing the popover
     */
    addPopoverEvents: function() {
        if (!this.usageTokensView) {
            this.createTokenView();
        }

        $('body').on(`click.${this.cid}`, _.bind(this.handleClickOutside, this));
    },

    /**
     * Add event listeners for hiding the popover
     */
    removePopoverEvents: function() {
        $('body').off(`click.${this.cid}`);
    },

    /**
     * Handle click outside of the popover
     * @param {Event} evt
     */
    handleClickOutside: function(evt) {
        const target = $(evt.target);
        const popoverContent = target.closest('.popover');
        const popoverTrigger = target.closest(`[aria-describedby="${this.currentPopoverId}"]`);

        if (!popoverContent.length && !popoverTrigger.length) {
            if (this.usageTokensView) {
                this.usageTokensView.dispose();
                this.usageTokensView = null;
            }

            this._disposePopoverElement();
        }
    },

    /**
     * Toggle the visibility of the popover button
     * @param {boolean} shouldShowButton - Flag indicating whether to show the button
     */
    toggleButtonVisibility: function(shouldShowButton) {
        if (shouldShowButton) {
            this.showInfoButton.addClass('visibleOnPopover');
        } else {
            this.showInfoButton.removeClass('visibleOnPopover');
        }
    },

    /**
     * Handle clicking on the close button inside the popover
     */
    handleCloseFromPopover: function() {
        $('.popover-close').click(_.bind(this._disposePopover, this));
    },

    /**
     * Get the header fields for the dashlet
     */
    _getHeaderFields: function() {
        const dashletTitle = app.lang.get(this.meta.label);

        const customModuleName = app.lang.getModuleName(this.module, {plural: false});
        const customDashletTitle = app.utils.formatString(dashletTitle, [customModuleName]);

        this.meta.label = app.utils.capitalize(customDashletTitle);

        return [
            {
                label: app.lang.get('LBL_PREDICT_INFO'),
                type: 'icon',
                name: 'gai-icon',
                readonly: true,
            },
            {
                type: 'label',
                formatted_value: app.utils.capitalize(customDashletTitle),
                readonly: true,
            },
        ];
    },

    /**
     * Get the dashlet toolbar
     */
    _getDashletToolbar: function() {
        return this.layout && this.layout.getComponent('dashlet-toolbar');
    },

    /**
     * Create the map and display it
     */
    _createAndShowSummarization: function() {
        this._disposeSummarizationController();

        const contextModel = this._getContextModel();

        this._summarizationController = app.view.createView({
            name: 'gai-dashlet-content',
            layout: this.layout,
            context: this.context,
            model: contextModel,
            module: this.module,
            generateOnInit: true,
        });

        this._summarizationController.render();

        this.$('.gai-dashlet-container').append(this._summarizationController.$el);
    },

    /**
     * Get the contextual model for the dashlet
     *
     * @return {Data.Bean}
     * @private
     */
    _getContextModel: function() {
        let contextModel = null;

        if (this._contextModel) {
            contextModel = this._contextModel;
        } else if (this._hasRowModel()) {
            contextModel = this._cloneModel(this._getRowModel());
        } else {
            let context = this.context;

            while (context) {
                let model = context.get('model');

                if (model && model.has('id')) {
                    let module = context.get('module');

                    if (this.options && this.options.module && module === this.options.module) {
                        contextModel = model;
                        break;
                    }
                }

                context = context.parent;
            }

            contextModel = this._cloneModel(contextModel || app.controller.context.get('model'));
        }

        return this._contextModel = contextModel;
    },

    /**
     * Create a new model with the same attributes as the passed in model.
     * Also copies the id
     *
     * @param {Data.Bean} model The model to copy
     * @return {Data.Bean}
     * @private
     */
    _cloneModel: function(model) {
        let clonedModel = app.data.createBean(model.module);
        clonedModel.copy(model);
        clonedModel.set('id', model.get('id'));
        return clonedModel;
    },

    /**
     * Determine if we have a rowModel or not.
     *
     * @return {boolean} `true` if we have a rowModel. `false` otherwise.
     * @private
     */
    _hasRowModel: function() {
        return this.context &&
            this.context.parent &&
            this.context.parent.parent &&
            this.context.parent.parent.has('rowModel');
    },

    /**
     * Get the row model from row-model-data.
     *
     * @return {Data.Bean|undefined} The rowModel, if it exists.
     * @private
     */
    _getRowModel: function() {
        const parentContext = this.context &&
                          this.context.parent &&
                          this.context.parent.parent;

        return parentContext ? parentContext.get('rowModel') : undefined;
    },

    /**
     * Dispose the wrapper
     */
    _disposeSummarizationController: function() {
        if (this._summarizationController) {
            this._summarizationController.dispose();
            this._summarizationController = null;
            this.$('.gai-dashlet-container').empty();
        }
    },

    /**
     * Dispose the popover
     */
    _disposePopover: function() {
        this._disposePopoverElement();

        if (this.usageTokensView) {
            this.usageTokensView.dispose();
            this.usageTokensView = null;
        }
    },

    /**
     * Dispose the popover element based on bootstrap version
     */
    _disposePopoverElement: function() {
        if (!this.showInfoButton) {
            return;
        }

        this.showInfoButton.popover('hide');
        this.showInfoButton.popover('dispose');
    },

    /**
     * @inheritdoc
     */
    _dispose: function() {
        $(window).off('resize.' + this.cid);
        this._disposeSummarizationController();
        this._disposePopover();
        this._super('_dispose');
    },
});
