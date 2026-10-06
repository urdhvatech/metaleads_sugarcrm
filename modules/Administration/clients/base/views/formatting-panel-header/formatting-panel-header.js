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
 * @class View.Views.Base.AdministrationFormattingPanelHeaderView
 * @alias SUGAR.App.view.views.BaseAdministrationFormattingPanelHeaderView
 * @extends View.View
 */
({
    events: {
        'click [data-direction]': 'triggerPagination',
        'click .closeSubdetail': 'triggerClose'
    },

    /*
     * Lets the rest of the application know that the formatting panel is closed
     */
    triggerClose: function() {
        this.context.trigger('formatting-panel:close');
    },

    /*
     * Lets the rest of the application know that the pagination
     * button was clicked and passes the direction of the pagination
     *
     * @param {Object} e - The click event object
     */
    triggerPagination: function(e) {
        let direction = this.$(e.currentTarget).data();
        this.context.trigger('formatting-panel:pagination:fire', direction);
    }
})
