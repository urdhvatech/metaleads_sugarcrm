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
 * @class View.Views.Base.pmse_Business_Rules.DashablerecordView
 * @alias SUGAR.App.view.views.pmse_Business_RulesCreateView
 * @extends View.Views.Base.DashablerecordView
 */
({
    extendsFrom: 'DashableRecordView',

    /**
     * @inheritdoc
     */
    _dispose: function() {
        $(document).off('click', '[data-event="button:edit_businessrules:click"]', this._handleEdit);
        this._super('_dispose');
    },

    /**
     * @inheritdoc
     */
    _render: function() {
        this._super('_render');
        $(document).off('click', '[data-event="button:edit_businessrules:click"]', this._handleEdit);
        $(document).on('click', '[data-event="button:edit_businessrules:click"]', _.bind(this._handleEdit, this));
    },

    /**
     * Handles the click event for the edit button.
     * @param {Event} e
     * @private
     */
    _handleEdit: function(e) {
        e.preventDefault();
        e.stopPropagation();
        let model = this.model;
        if (model) {
            this._warnEditBusinessRules(model);
        } else {
            this._editRecord();
        }
    },

    /**
     * Warns the user if the business rule to be edited is already in use.
     * @param model
     * @private
     */
    _warnEditBusinessRules: function(model) {
        let verifyURL = app.api.buildURL(
            'pmse_Project',
            'verify',
            {id: model.get('id')},
            {baseModule: this.module});
        let self = this;
        app.api.call('read', verifyURL, null, {
            success: function(data) {
                if (!data) {
                    self._editRecord();
                } else {
                    app.alert.show('business-rule-design-confirmation', {
                        level: 'confirmation',
                        messages: App.lang.get('LBL_PMSE_PROCESS_BUSINESS_RULES_EDIT', model.module),
                        onConfirm: function() {
                            self._editRecord();
                        },
                        onCancel: $.noop
                    });
                }
            },
            error: function() {
                self._editRecord();
            }
        });
    },

    /**
     * Handles the actual record editing.
     * @private
     */
    _editRecord: function() {
        if (!this.model) {
            return;
        }
        this.editRecord();
    },
});
