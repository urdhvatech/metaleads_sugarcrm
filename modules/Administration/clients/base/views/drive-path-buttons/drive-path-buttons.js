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
 * @class View.Views.Base.AdministrationDrivePathButtonsView
 * @alias SUGAR.App.view.views.BaseAdminstrationDrivePathButtonsView
 * @extends View.Views.Base.View
 */
({
    /**
     * @inheritdoc
     */
    events: {
        'click [name=save_button]': 'saveCurrentPath',
        'click [name=cancel_button]': 'closeDrawer',
        'click [name=shared_button]': 'toggleCheckbox',
        'click [name=sharedDrive_button]': 'toggleCheckbox',
        'change .sharedWithMe': 'toggleCheckbox',
        'change .sharedDrives': 'toggleCheckbox'
    },

    /**
     * @inheritdoc
     */
    initialize: function(options) {
        this._super('initialize', arguments);
        this.driveType = this.context.get('driveType');
        this.driveTypeLabel = app.lang.getAppListStrings('drive_types')[this.driveType];
        this._handleSharedDriveButton();
    },

    _render: function() {
        this._super('_render');
        this._handleSharedWithMeButton();
    },

    /**
     * Save the current path
     *
     * @param {Event} evt
     */
    saveCurrentPath: function(evt) {
        let folders = this.layout.getComponent('drive-path-select').currentPathFolders;
        const folderId = this.layout.getComponent('drive-path-select').currentFolderId;
        const driveId = this.layout.getComponent('drive-path-select').driveId;
        const siteId = this.layout.getComponent('drive-path-select').siteId;

        const url = app.api.buildURL('CloudDrive', 'path');

        app.alert.show('path-processing', {
            level: 'process'
        });

        let params = {
            isRoot: this.context.get('isRoot'),
            pathModule: this.context.get('pathModule'),
            type: this.driveType,
            drivePath: JSON.stringify(folders),
            folderId: folderId,
            driveId: driveId,
            siteId: siteId,
            isShared: this.context.get('sharedWithMe'),
            pathId: this.context.get('pathId'),
            isSharedDrive: this.context.get('sharedDrives'),
        };

        if (this.driveType === 'sharepoint') {
            params.modifySiteId = true;
        }

        app.api.call('create', url, params, {
            success: function() {
                app.alert.dismiss('path-processing');
                app.drawer.close();
            },
            error: function(error) {
                app.alert.show('cloud-error', {
                    level: 'error',
                    messages: error.message,
                });
            },
        });
    },

    /**
     * Close drawer
     *
     * @param {Event} evt
     */
    closeDrawer: function(evt) {
        app.drawer.close();
    },

    /**
     * Toggle between shared and My files
     *
     * @param {Event} evt
     */
    toggleShared: function(classToToggle) {
        if (this.driveType === 'sharepoint') {
            return;
        }

        let pathView = this.layout.getComponent('drive-path-select');
        if (classToToggle === 'sharedWithMe') {
            pathView.loadFolders(null, this[classToToggle]);
        } else {
            pathView.loadFolders(null, null, null, this[classToToggle]);
        }
    },

    /**
     * Checkbox event
     *
     * @param {Event} evt
     */
    toggleCheckbox: function(evt) {
        const classToToggle = this._getSharedOption(evt);
        const checkbox = this.$('.' + classToToggle);
        const newState = !this[classToToggle];

        if (newState) {
            this.checkOtherOptionsState(classToToggle);
        }
        checkbox.prop('checked', newState);
        this[classToToggle] = newState;
        this.context.set(classToToggle, newState);
        this.toggleShared(classToToggle);
    },

    /**
     * Disable the Shared with me button for Sharepoint
     *
     */
    _handleSharedWithMeButton: function() {
        if (this.driveType === 'sharepoint') {
            this.$('[name="shared_button"]').attr('disabled', true);
            this.$('[name="shared_button"]').addClass('disabled');
        }
    },

    /**
     * Removes the shared drive button if the drive is not google
     *
     */
    _handleSharedDriveButton: function() {
        if (this.driveType !== 'google' && this.meta) {
            let fieldMeta = _.find(this.meta.buttons,function(field) {
                return field.name === 'sharedDrive_button';
            });

            if (fieldMeta) {
                fieldMeta.css_class += ' hidden';
            }
        }
    },

    /**
     * Get the class for shared option
     *
     * @param {Event} evt
     *
     * @return {string}
     */
    _getSharedOption: function(evt) {
        return evt.currentTarget.name === 'shared_button' || evt.currentTarget.className === 'sharedWithMe' ?
           'sharedWithMe' : 'sharedDrives';
    },
    /**
     * Check the other options state
     *
     * @param {string} classToToggle
     */
    checkOtherOptionsState: function(classToToggle) {
        if (classToToggle === 'sharedWithMe' && this.sharedDrives) {
            this.sharedDrives = false;
            this.$('.sharedDrives').prop('checked', false);
            this.context.set('sharedDrives', false);
        }else if (this.sharedWithMe) {
            this.sharedWithMe = false;
            this.$('.sharedWithMe').prop('checked', false);
            this.context.set('sharedWithMe', false);
        }
    }
});
