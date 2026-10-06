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

const mobileUtils = {

    /**
     * Device types for mobile login options.
     * Used to propose users to download the mobile app for their device.
     * @type {Object}
     */
    DeviceType: {
        ANDROID: 'android',
        IOS: 'ios',
        OTHER: 'other',
    },

    /**
     * Checks the user agent to determine the type of device.
     * Currently supports Android and iOS detection.
     * @return {string} The type of device based on the user agent.
     */
    detectDeviceType: function() {
        const ua = navigator.userAgent.toLowerCase();

        if (ua.includes('android')) {
            return this.DeviceType.ANDROID;
        }

        if (ua.includes('iphone') || ua.includes('ipad')) {
            return this.DeviceType.IOS;
        }

        // iPad with safari browser should be treated as iOS
        if (ua.includes('macintosh') && ua.includes('safari')) {
            const hasTouch = navigator.maxTouchPoints > 0;
            if (hasTouch) {
                return this.DeviceType.IOS;
            }
        }

        return this.DeviceType.OTHER;
    },

    /**
     * Navigates to the app store based on the device type.
     * @param {string} device - The type of device ('android' or 'ios').
     */
    navigateToAppStore: function(device) {
        const app = SUGAR.App;
        const config = app.config.mobileAppLinks;
        device = device || mobileUtils.detectDeviceType();
        let url = null;

        switch (device) {
            case this.DeviceType.ANDROID:
                url = config.androidAppUrl;
                break;
            case this.DeviceType.IOS:
                url = config.iosAppUrl;
                break;
            default:
                url = null;
        }

        if (url) {
            window.location.replace(url);
        }
    },
};

(function(app) {
    const deviceType = mobileUtils.detectDeviceType();
    if (deviceType === mobileUtils.DeviceType.ANDROID) {
        mobileUtils.deferredBeforeInstallPrompt = null;
        window.addEventListener('beforeinstallprompt', function(e) {
            e.preventDefault();
            mobileUtils.deferredBeforeInstallPrompt = e;
        });
    }
    app.augment('mobileUtils', mobileUtils, true);
})(SUGAR.App);
