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
 * @class View.Views.Base.MobilePromptView
 * @alias SUGAR.App.view.views.BaseMobilePromptView
 * @extends View.View
 */
({
    cacheKey: 'mobile-app-links:mobile-install-prompt',
    deferredPrompt: null,
    originalDeferredPrompt: SUGAR.App.mobileUtils.deferredBeforeInstallPrompt,
    config: app.config.mobileAppLinks,
    deviceType: null,

    events: {
        'click .mobile-banner__close': 'dismissBanner',
        'click .mobile-banner__btn': 'installApp',
    },

    initialize: function(options) {
        this._super('initialize', [options]);
        this.showBanner = false;
        this.deviceType = SUGAR.App.mobileUtils.detectDeviceType();

        if (app.isSynced) {
            this.initBanner();
        } else {
            app.on('app:sync:complete', this.initBanner, this);
        }
    },

    beforeInstallHandler: function(e) {
        e.preventDefault();
        this.deferredPrompt = e;
        this.showBanner = true;
        this.render();
    },

    dismissBanner: function() {
        this.saveLastShownTs();
        this.showBanner = false;
        this.render();
    },

    installApp: function() {
        if (!this.deferredPrompt) {
            return;
        }
        this.deferredPrompt.prompt();
        this.deferredPrompt.userChoice
            .then(
                _.bind(function(choiceResult) {
                    if (choiceResult.outcome === 'accepted') {
                        this.saveLastShownTs();
                        // If the device doesn’t support the prompt natively, open the store manually.
                        if (
                            !this.originalDeferredPrompt &&
                            this.deviceType !==
                                SUGAR.App.mobileUtils.DeviceType.OTHER
                        ) {
                            SUGAR.App.mobileUtils.navigateToAppStore(
                                this.deviceType
                            );
                        }
                    }
                    this.showBanner = false;
                    this.render();
                }, this)
            )
            .catch(function(error) {
                app.logger.error(error);
            });
        this.deferredPrompt = null;
    },

    checkBannerSupport: function() {
        const userAgent =
            navigator.userAgent || navigator.vendor || window.opera;

        // Samsung Internet does not support the beforeinstallprompt event
        const isSamsungInternet = userAgent.match(
            /SAMSUNG|Samsung|SGH-[I|N|T]|GT-[I|N]|SM-[A|N|P|T|Z]|SHV-E|SCH-[I|J|R|S]|SPH-L/i
        );
        return (
            !isSamsungInternet &&
            this.deviceType === SUGAR.App.mobileUtils.DeviceType.ANDROID
        );
    },

    initBanner: function() {
        if (!this.needShowBanner()) {
            return;
        }

        // If the device supports the beforeinstallprompt event, we will use it to show the banner.
        const isBannerSupported = this.checkBannerSupport();
        if (isBannerSupported) {
            //show banner only if we catch the beforeinstallprompt event previously
            if (this.originalDeferredPrompt) {
                this.beforeInstallHandler(this.originalDeferredPrompt);
            }
        } else {
            const mockEvent = {
                preventDefault: function() {},
                prompt: function() {},
                userChoice: Promise.resolve({outcome: 'accepted'}),
            };
            this.beforeInstallHandler(mockEvent);
        }
    },

    /**
     * Determines whether our custom banner should be shown based on the device type and cache.
     * Should be shown for Android devices or iOS devices that are not using Safari.
     * @return {boolean} True if the banner should be shown, false otherwise.
     */
    needShowBanner: function() {
        //do not shown if the user dismissed it in the last 24 hours
        const timestamp = this.getLastShownTs();
        if (timestamp) {
            const config = SUGAR.App.config.mobileAppLinks;
            const now = Date.now();
            const snoozeIntervalHours = config.bannerSnoozeIntervalHrs * 60 * 60 * 1000;
            if (now - timestamp < snoozeIntervalHours) {
                return false;
            }
        }

        // Show the banner for Android devices
        if (this.deviceType === SUGAR.App.mobileUtils.DeviceType.ANDROID) {
            return true;
        }

        // Show the banner for iOS devices only if they are not using Safari
        if (this.deviceType === SUGAR.App.mobileUtils.DeviceType.IOS) {
            const {userAgent = ''} = navigator;

            const usingOtherBrowser = /Chrome|CriOS|FxiOS|EdgiOS|OPiOS|Ddg/.test(userAgent);
            return usingOtherBrowser;
        }

        return false;
    },

    saveLastShownTs: function() {
        // set cache to avoid showing the banner for 24 hours
        app.user.lastState.set(this.cacheKey, Date.now().toString());
    },

    getLastShownTs: function() {
        const dateStr = app.user.lastState.get(this.cacheKey);

        return dateStr ? parseInt(dateStr, 10) : null;
    },
});
