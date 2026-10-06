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
 * @class View.Views.Base.BusinessCenters.DashablerecordView
 * @alias SUGAR.App.view.views.BusinessCentersDashablerecordView
 * @extends View.Views.Base.DashablerecordView
 */
({
    /**
     * @inheritdoc
     */
    hasUnsavedChanges: function() {
        const changedAttributes = this.model.changedAttributes(this.model.getSynced());

        if (!changedAttributes) {
            return false;
        }

        let castModelData = {};
        for (key in changedAttributes) {
            let modelValue = this.model.get(key);

            castModelData[key] = typeof changedAttributes[key] === 'boolean' ? Boolean(modelValue) : String(modelValue);
        }

        if (_.isEqual(changedAttributes, castModelData)) {
            return false;
        }

        return this._super('hasUnsavedChanges');
    }
})
