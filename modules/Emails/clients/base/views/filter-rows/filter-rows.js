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
({
    extendsFrom: 'FilterRowsView',

    /**
     * Flag to allow raw email addresses to be added as participants even if there are existing records found.
     * This allows to filter out email records by email addresses and by related records like Contacts.
     * @type {boolean}
     */
    includeEmailInParticipants: true,
})
