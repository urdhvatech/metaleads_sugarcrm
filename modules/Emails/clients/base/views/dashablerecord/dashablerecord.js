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
 * Emails-specific DashableRecord view.
 *
 * @class View.Views.Base.Emails.DashablerecordView
 * @alias SUGAR.App.view.views.EmailsDashablerecordView
 * @augments View.Views.Base.DashablerecordView
 */
({
    extendsFrom: 'DashablerecordView',

    /**
     * @inheritdoc
     *
     * Copies `_acl` from the source model and establishes a correct synced baseline.
     */
    _cloneModel: function(model) {
        const clonedModel = this._super('_cloneModel', [model]);

        if (model.has('_acl')) {
            clonedModel.set('_acl', app.utils.deepCopy(model.get('_acl')));
        }
        clonedModel.setSyncedAttributes(clonedModel.attributes);

        return clonedModel;
    },

    /**
     * @inheritdoc
     *
     * It prevents readonly fields from appearing in the PUT body without touching `model.attributes`.
     */
    getCustomSaveOptions: function(options) {
        const parentOptions = this._super('getCustomSaveOptions', [options]);
        const readonlyFieldNames = _.chain(this.fields)
            .filter((field) =>
                field.def && field.def.readonly === true)
            .pluck('name')
            .value();
        const allowedFields = _.difference(_.keys(this.model.attributes), readonlyFieldNames);

        return _.extend({}, parentOptions, {fields: allowedFields});
    },
})
