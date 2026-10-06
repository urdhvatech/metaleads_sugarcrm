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
 * @class View.Views.Base.AdministrationPackageBuilderProgressAlertView
 * @alias SUGAR.App.view.views.BaseAdministrationPackageBuilderProgressAlertView
 * @extends View.View
 */
({
    /**
     * Initial title
     */
    initialTitle: 'Processing',

    /**
     * Success title
     */
    successTitle: 'Success',

    /**
     * Error title
     */
    errorTitle: 'Error',

    /**
     * @inheritdoc
     */
    initialize: function(options) {
        this._super('initialize', [options]);

        this.initialTitle = options.initialTitle ? options.initialTitle : 'Processing';
        this.successTitle = options.successTitle ? options.successTitle : 'Success';
        this.errorTitle = options.errorTitle ? options.errorTitle : 'Error';
    },

    /**
     * Set progress bar percentage
     * @param {string}
     */
    setProgress: function(percentage) {
        this.$el.find('.progress .bar').css('width', percentage);
        this.$el.find('h5').html(percentage);
    },

    /**
     * Set seccess message
     * @param {string}
     */
    processSuccessful: function(message) {
        message = '<br/>' + message;

        this.$el.find('#ci-progress-lbl').toggle();
        this.$el.find('#ci-progress-success-lbl').toggle();
        this.$el.find('.alert-info').removeClass('alert-info').addClass('alert-success');
        this.$el.find('button').toggle();
        this.$el.find('.progress .bar').css('width', '100%');
        this.$el.find('h5').html('100%');
        this.$el.find('pre').append('<b>' + message + '</b>')
            .scrollTop(function getScHeight() {
                return this.scrollHeight;
            });
    },

    /**
     * Set error message
     * @param {string}
     */
    processFailed: function(errorMessage) {
        errorMessage = '<br/>' + errorMessage;

        this.$el.find('pre').append('<b style="color: red">' + errorMessage + '</b>')
            .scrollTop(function getScHeight() {
                return this.scrollHeight;
            });
        this.$el.find('#ci-progress-lbl').toggle();
        this.$el.find('#ci-progress-error-lbl').toggle();
        this.$el.find('.alert-info').removeClass('alert-info').addClass('alert-danger');
        this.$el.find('button').toggle();
        this.$el.find('.sicon-chevron-down').parent().find('a').click();
    },

    /**
     * Log message
     * @param {string}
     */
    logMessage: function(messageText) {
        // Logger container
        let preEl = this.$el.find('pre');
        messageText = messageText + '<br/>';

        preEl.scrollTop(function getScHeight() {
            return this.scrollHeight;
        });
        preEl.append(messageText);
    },
});
