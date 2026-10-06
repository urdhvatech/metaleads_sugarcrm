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
 * @class View.Views.Base.DropdownEditorHeaderView
 * @alias SUGAR.App.view.views.DropdownEditorHeaderView
 * @extends View.Views.Base.ConfigPanelView
 */
({
    title: 'LBL_DROPDOWN_EDITOR',

    /**
     * @inheritdoc
     *
     * @param options
     */
    initialize: function(options) {
        this._super('initialize', [options]);
        this.mode = (_.isUndefined(options.context.get('dropdownName'))) ? 'create' : 'edit';

        this.oldMetaButtons = _.clone(this.meta.buttons);
        this.meta.buttons = _.filter(this.meta.buttons, (button) => button.name !== 'restore_button');

        this.listenTo(this.context, 'button:cancel_button:click', this.cancelConfig);
        this.listenTo(this.context, 'button:restore_button:click', this.checkConfirmation);
        this.listenTo(this.context, 'ootb:changed', this._adjustButtonsList);
    },

    /**
     * Adjusts buttons list to remove the restore button if needed.
     */
    _adjustButtonsList: function() {
        this.meta.buttons = _.filter(this.oldMetaButtons, (button) =>
            (this.model.get('ootb') && button.name === 'restore_button') || button.name !== 'restore_button'
        );

        this.render();
    },

    /**
     * Cancel the configuration.
     */
    cancelConfig: function() {
        if (app.drawer.count()) {
            app.drawer.close();
        } else {
            // TODO: the route should be edited after moving the Dropdown list to Sidecar
            let route = app.bwc.buildRoute('ModuleBuilder', null, 'index', {
                type: 'dropdowns',
            });
            app.router.navigate('#' + route, {
                trigger: true
            });
        }
    },

    /**
     * Check confirmation before restoring the data of the dropdown list.
     */
    checkConfirmation: function() {
        let self = this;
        app.alert.show('confirm', {
            level: 'confirmation',
            messages: app.lang.get('LBL_DROPDOWN_BTN_RESTORE_WARNING', this.module),
            onConfirm: function() {
                self.context.trigger('restore:dropdown');
            }
        });
    },

    /**
     * @inheritdoc
     */
    dispose: function() {
        this.stopListening();
        this._super('dispose');
    }
})
