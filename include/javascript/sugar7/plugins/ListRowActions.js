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
 * Utility functions for adding row actions to list views.
 */
(function(app) {
    app.events.on('app:init', function() {
        app.plugins.register('ListRowActions', ['view'], {
            /**
             * Name of the row edit event.
             *
             * @property {string}
             */
            editEventName: 'list:editrow:fire',

            onAttach: function(component, plugin) {
                this.on('init', function() {
                    if (this._bindEvents) {
                        this._bindEvents();
                    }
                }, this);
            },

            onDetach: function(component, plugin) {
                // Stop listening to all plugin-related events
                this.stopListening();

                // Clean up any lingering jQuery event listeners
                this.$('.main-pane').off('scroll.right-actions');
                this.$el.off('.right-actions');

                // Clear helper references if needed
                if (this.$helper) {
                    this.$helper.remove();
                    this.$helper = null;
                }

                // Clear references to avoid memory leaks
                this.rowFields = null;
                this._modelToDelete = null;
                this._targetUrl = null;
                this._currentUrl = null;

                // Optionally remove alert in case it’s still open
                app.alert.dismiss('locked_field_inline_edit');
                app.alert.dismiss('delete_confirmation');
            },

            /**
             * A utility method to determine when a dropdown menu is going to collide with the bottom of the screen.
             */
            needsDropupClass: function($b) {
                const menuHeight = $b.height() + $b.children('ul').first().height();
                // TODO fix (SS-1078) | height of window less padding
                let windowHeight = document.documentElement.clientHeight - 65;
                // The total displacement needed for dropdown to expand + distance from top of screen
                const dropdownDisplacement = $b.offset().top + menuHeight;

                /**
                 * Special handling for Safari as the menu cannot overlay table content due to -webkit-sticky
                 * positioning and associated z-index behaviour.
                 */
                if (this._isSafariBrowser && this.collection.length > 3) {
                    // Height of visible viewport (page height inside browser window)
                    windowHeight = document.documentElement.clientHeight;
                    const table = $b.parent().closest('.dataTable');
                    const tableHeight = table.height();
                    const tableOffsetTop = table.offset().top;

                    /**
                     * There are 3 cases to check here:
                     * 1. If only the first subset of records are loaded and the table is shorter
                     * than the height of the table (on page load for list view).
                     * 2. If more records have been loaded, making the table height larger
                     * than screen size, and the user scrolls to the new bottom of the table
                     * and opens the right action dropdown.
                     * 3. If more records have been loaded and the users selects the right
                     * action dropdown somewhere before the bottom of the page.
                     */
                    if (tableHeight < windowHeight && tableOffsetTop > 0) {
                        return (tableHeight + tableOffsetTop) < dropdownDisplacement;
                    } else if ((tableHeight - Math.abs(tableOffsetTop) < dropdownDisplacement)) {
                        return true;
                    }
                }

                return windowHeight < dropdownDisplacement;
            },

            resetDropdownDelegate: function(e) {
                this.$el.removeClass('no-touch-scrolling');
                this.$(e.currentTarget).off('resetDropdownDelegate.right-actions');
            },

            _toggleAria: function(e) {
                let $dropdown = this.$(e.currentTarget);
                let $button = $dropdown.find('[data-bs-toggle="dropdown"]');
                $button.attr('aria-expanded', $dropdown.hasClass('open'));
            },

            delegateDropdown: function(e) {
                let $buttonGroup = this.$(e.currentTarget).first(); // the button group

                this.$el.addClass('no-touch-scrolling');
                // add open class to parent list to elevate absolute z-index for iOS
                $buttonGroup.parent().closest('.list').addClass('open');
                // detect window bottom collision
                $buttonGroup.toggleClass('dropup', this.needsDropupClass($buttonGroup));
                // listen for delegate reset
                $buttonGroup.on('resetDropdownDelegate.right-actions', this.resetDropdownDelegate);
                // add a listener to scrolling container
                $buttonGroup.parents('.main-pane')
                    .on('scroll.right-actions', _.bind(_.debounce(function() {
                        // detect window bottom collision on scroll
                        $buttonGroup.toggleClass('dropup', this.needsDropupClass($buttonGroup));
                    }, 30), this));
            },

            /**
             * Checks if the given element is "clickable" - that is, if it is an element that always
             * performs some action, if it is a focus icon, or if it has an event associated in another way
             * @param element
             * @return {boolean}
             */
            isClickableElement: function(element) {
                let tagNames = [element.tagName, element.parentElement.tagName].map(tag => tag.toLowerCase());
                if (['a', 'button', 'input'].some(tag => tagNames.includes(tag))) {
                    return true;
                }
                if (element.classList.contains('focus-icon')) {
                    return true;
                }
                return ['data-action', 'data-clipboard', 'data-event'].some(attr => {
                    return element.getAttribute(attr) || element.parentElement.getAttribute(attr);
                });
            },

            /**
             * Handle switching a row to edit mode when double clicked
             * @param event
             */
            doubleClickEdit: function(event) {
                // Do not continue if the row cannot be edited or if the clicked element has some other
                // action associated with it
                if (!this.isRowEditable() || this.isClickableElement(event.target)) {
                    return;
                }
                event.stopPropagation();

                // Get the ID of the model from the row
                let row = this.$(event.target).parents('tr');
                let rowName = row.attr('name');
                let modelId = rowName.indexOf(`${this.module}_`) === 0 ?
                    rowName.substr(`${this.module}_`.length) : null;
                if (_.isEmpty(modelId)) {
                    return;
                }

                // Check if the row is already in edit mode
                if (!_.isEmpty(this.toggledModels[modelId])) {
                    return;
                }

                let model = this.collection.get(modelId);
                if (_.isEmpty(model)) {
                    return;
                }

                if (app.acl.hasAccessToModel('edit', model)) {
                    this.context.trigger(this.editEventName, model);

                    // Safari will also highlight the text around where the user double clicked - clear that
                    if (window.getSelection) {
                        window.getSelection().empty();
                    }
                }
            },

            /**
             * Show a warning alert about locked fields on the model. The warning will
             * link to the Sidecar record view in edit mode or BWC edit view
             *
             * @param {Backbone.Model} model the model for the row we are editing
             * @private
             */
            _showLockedFieldWarning: function(model) {
                let route = app.router.buildRoute(model.module, model.id, 'edit');
                let recordName = Handlebars.Utils.escapeExpression(app.utils.getRecordName(model));
                let message = app.lang.get(
                    'LBL_LOCKED_FIELD_INLINE_EDIT',
                    model.module,
                    {link: new Handlebars.SafeString('<a >' + recordName + '</a>')}
                );
                let module = app.metadata.getModule(model.module);
                app.alert.show('locked_field_inline_edit', {
                    level: 'warning',
                    messages: message,
                    autoClose: false,
                    onLinkClick: function() {
                        app.alert.dismiss('locked_field_inline_edit');
                        let trigger = module.isBwcEnabled;
                        if (!trigger) {
                            // We need to load the view here to add lockedFieldWarning to the context
                            // for sidecar modules
                            app.controller.loadView({
                                layout: 'record',
                                module: model.module,
                                modelId: model.id,
                                action: 'edit',
                                lockedFieldsWarning: false
                            });
                        }
                        app.router.navigate(route, {trigger: trigger});
                    }
                });
            },

            /**
             * Toggles the helper scroll bar.
             *
             * If the spy's `width` is greater than its `scrollWidth` (the screen is
             * large enough) OR if the footer is higher than the table (the table is not
             * visible on the screen), we hide the helper scrollbar.
             * Also, we hide it if the bottom of the table is higher than the footer
             * (the natural scroll bar is present).
             *
             * @private
             */
            _toggleScrollHelper: function() {
                if (this.$spy.get(0).scrollWidth <= this.$spy.width() ||
                    this.$('tbody').offset().top + this.$helper.height() > $('footer').offset().top
                ) {
                    this.$helper.toggle(false);
                    return;
                }

                this.$helper.toggle(!(this.$('.scrollbar-landmark').offset().top < $('footer').offset().top));
                if (this.$helper.css('display') !== 'none') {
                    this.$helper.scrollLeft(this.$spy.scrollLeft());
                }
            },

            /**
             * Toggle the selected model's fields when edit is clicked.
             *
             * @param {Backbone.Model} model Selected row's model.
             */
            editClickedRow: function(model, field) {
                // If a field is locked, we don't allow inline editing. Instead show an alert that links
                // to the record view or editview to make changes there.
                if (!_.isEmpty(model.get('locked_fields'))) {
                    this._showLockedFieldWarning(model);
                    return;
                }
                if (field && field.def && field.def.full_form) {
                    let parentModel = this.context.parent.get('model');
                    let link = this.context.get('link');

                    // `app.bwc.createRelatedRecord` navigates to the BWC EditView if an
                    // id is passed to it.
                    app.bwc.createRelatedRecord(this.module, parentModel, link, model.id);
                } else {
                    this.toggleRow(model.id, true);
                    //check to see if horizontal scrolling needs to be enabled
                    this.resize();
                }
                if (!_.isEqual(model.attributes, model._syncedAttributes) && _.isFunction(model.setSyncedAttributes)) {
                    model.setSyncedAttributes(model.attributes);
                }
            },

            /**
             * Set, or reset, the collection of fields that contains each row.
             *
             * This function is invoked when the view renders. It will update the row
             * fields once the `Pagination` plugin successfully fetches new records.
             *
             * @private
             */
            _setRowFields: function() {
                this.rowFields = {};
                _.each(this.fields, function(field) {
                    if (field.model && field.model.id && _.isUndefined(field.parent)) {
                        this.rowFields[field.model.id] = this.rowFields[field.model.id] || [];
                        this.rowFields[field.model.id].push(field);
                    }
                }, this);
            },

            /**
             * Popup browser dialog message to confirm delete action
             *
             * @return {string} the message to be displayed in the browser dialog
             */
            warnDeleteOnRefresh: function() {
                if (this._modelToDelete) {
                    return this.getDeleteMessages(this._modelToDelete).confirmation;
                }
            },

            /**
             * Popup dialog message to confirm delete action
             *
             * @param {Backbone.Model} model the bean to delete
             */
            warnDelete: function(model) {
                let self = this;
                this._modelToDelete = model;

                self._targetUrl = Backbone.history.getFragment();
                //Replace the url hash back to the current staying page
                if (self._targetUrl !== self._currentUrl) {
                    app.router.navigate(self._currentUrl, {trigger: false, replace: true});
                }

                app.alert.show('delete_confirmation', {
                    level: 'confirmation',
                    messages: self.getDeleteMessages(model).confirmation,
                    onConfirm: _.bind(self.deleteModel, self),
                    onCancel: function() {
                        self._modelToDelete = null;
                    }
                });
            },

            /**
             * Popup browser dialog message to confirm delete action
             *
             * @return {string} the message to be displayed in the browser dialog
             */
            warnDeleteOnRefresh: function() {
                if (this._modelToDelete) {
                    return this.getDeleteMessages(this._modelToDelete).confirmation;
                }
            },

            /**
             * Delete the model once the user confirms the action
             */
            deleteModel: function() {
                let self = this;
                let model = this._modelToDelete;

                model.destroy({
                    //Show alerts for this request
                    showAlerts: {
                        'process': true,
                        'success': {
                            messages: self.getDeleteMessages(self._modelToDelete).success
                        }
                    },
                    success: _.bind(this.deleteModelSuccessCallback, this, model),
                    error: function() {
                        self._modelToDelete = null;
                    }
                });
            },

            /**
             * Callback function for successful deletion on model
             */
            deleteModelSuccessCallback: function(model) {
                let redirect = this._targetUrl !== this._currentUrl;
                this._modelToDelete = null;
                this.collection.remove(model, {silent: redirect});
                if (redirect) {
                    this.unbindBeforeRouteDelete();
                    //Replace the url hash back to the current staying page
                    app.router.navigate(this._targetUrl, {trigger: true});
                    return;
                }
                app.events.trigger('preview:close');
                if (!this.disposed) {
                    this.render();
                }

                this.layout.trigger('list:record:deleted', model);
            },

            /**
             * Pre-event handler before current router is changed
             *
             * @return {boolean} true to continue routing, false otherwise
             */
            beforeRouteDelete: function() {
                if (this._modelToDelete) {
                    this.warnDelete(this._modelToDelete);
                    return false;
                }
                return true;
            },

            /**
             * Formats the messages to display in the alerts when deleting a record.
             *
             * @param {Data.Bean} model The model concerned.
             * @return {Object} The list of messages.
             * @return {string} return.confirmation Confirmation message.
             * @return {string} return.success Success message.
             */
            getDeleteMessages: function(model) {
                let messages = {};
                let name = Handlebars.Utils.escapeExpression(this._getNameForMessage(model)).trim();
                let context = app.lang.getModuleName(model.module).toLowerCase() + ' "' + name + '"';

                messages.confirmation = app.utils.formatString(
                    app.lang.get('NTC_DELETE_CONFIRMATION_FORMATTED', this.module),
                    [context]
                );
                messages.success = app.utils.formatString(app.lang.get('NTC_DELETE_SUCCESS'), [context]);
                return messages;
            },
        });
    });
})(SUGAR.App);
