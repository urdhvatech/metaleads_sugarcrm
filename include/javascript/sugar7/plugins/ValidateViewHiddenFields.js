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
(function(app) {
    app.events.on('app:init', function() {
        app.plugins.register('ValidateViewHiddenFields', 'field', {

            /**
             * Validate view hidden fields.
             * @param {View} view
             * @param {Bean} model
             * @param {Function} callback Function to call with the result of the validation
             */
            validateViewHiddenFields: function(view, model, callback) {
                if (!view._thisListViewFieldList || _.isEmpty(view._thisListViewFieldList.hidden)) {
                    callback(true); // valid
                    return;
                }

                const hiddenFields = view._thisListViewFieldList.hidden;
                const fieldsToValidate = _.pick(model.fields, hiddenFields);

                model.isValidAsync(fieldsToValidate, function(isValid) {
                    if (!isValid) {
                        app.alert.show('hidden-fields-validation-error', {
                            level: 'error',
                            messages: 'ERR_RESOLVE_HIDDEN_FIELDS'
                        });
                    }

                    callback(isValid);
                });
            }
        });
    });
})(SUGAR.App);
