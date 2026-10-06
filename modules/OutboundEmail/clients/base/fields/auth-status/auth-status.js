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
 * @class View.Fields.Base.OutboundEmail.AuthStatusField
 * @alias SUGAR.App.view.fields.OutboundEmailAuthStatusField
 * @extends View.Fields.Base.BaseField
 */
({
    /**
     * Stores email service connection status
     */
    isConnected: undefined,

    /**
     * @inheritdoc
     */
    initialize: function(options) {
        this._super('initialize', [options]);
        this.oauth2Types = {
            google_oauth2: {
                application: 'GoogleEmail',
                dataSource: 'googleEmailRedirect'
            },
            exchange_online: {
                application: 'MicrosoftEmail',
                dataSource: 'microsoftEmailRedirect'
            }
        };
    },

    /**
     * @inheritdoc
     *
     */
    bindDataChange: function() {
        if (this.model) {
            this.model.on('change:eapm_id change:mail_smtptype', function(model, value) {
                this._testConnection();
                this.render();
            }, this);
        }
    },

    /**
     * Uses the detail template.
     *
     * @inheritdoc
     */
    _loadTemplate: function() {
        this._super('_loadTemplate');
        this.template = app.template.getField('auth-status', 'detail', this.model.module);
    },

    /**
     * @inheritdoc
     */
    _render: function() {
        // Test connection on initial render if we have the required data
        if (_.isUndefined(this.isConnected)) {
            this._testConnection();
        }
        this.status = app.lang.get(this._getStatus());
        this._super('_render');
    },

    /**
     * Gets testConnection's result
     */
    _testConnection: function() {
        let smtpType = this.model.get('mail_smtptype');
        let eapmId = this.model.get('eapm_id');

        // Reset connection status if not an OAuth2 type
        if (!this.oauth2Types[smtpType]) {
            this.isConnected = undefined;
            return;
        }

        // Don't test if no eapm_id
        if (!eapmId) {
            this.isConnected = false;
            return;
        }

        let url = app.api.buildURL('EAPM', 'test', {}, {
            application: this.oauth2Types[smtpType].application,
            eapm_id: eapmId,
            options: {
                direction: 'outbound'
            }
        });

        let callback = {
            success: (data) => {
                if (!data.isConnected) {
                    app.alert.show('info-checking-mail-connection', {
                        level: 'info',
                        title: app.lang.get('LBL_ALERT_TITLE_NOTICE'),
                        messages: [app.lang.get('LBL_EMAIL_RE_AUTHORIZE_ACCOUNT')]
                    });
                }

                this.isConnected = data.isConnected;
            },
            error: (error) => {
                this.isConnected = false;
                app.alert.show('error-checking-mail-connection', {
                    level: 'error',
                    messages: error
                });
            },
            complete: () => {
                this.render();
            }
        };

        app.api.call('read', url, {}, callback);
    },

    /**
     * Gets current status
     */
    _getStatus: function() {
        if (this.model.get('eapm_id') && this.isConnected) {
            return 'LBL_EMAIL_AUTHORIZED';
        }

        if (this.model.get('eapm_id') && _.isUndefined(this.isConnected)) {
            return 'LBL_EMAIL_CONNECTING';
        }

        return 'LBL_EMAIL_NOT_AUTHORIZED';
    }
})
