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
 * @class View.Fields.Base.AdministrationButtonField
 * @alias SUGAR.App.view.fields.BaseAdministrationButtonField
 * @extends View.Fields.Base.ButtonField
 */
({
    extendsFrom: 'ButtonField',

    /**
     * @inheritdoc
     */
    initialize: function() {
        this._super('initialize', arguments);

        this.hiddenIcon = !this.view.formatting && (this.def.name === 'dropdown_format');
    },
})
