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
    app.analytics = app.analytics || {};
    app.analytics.connectors = app.analytics.connectors || {};

    let _initialized = false;
    let _envIdCache = null;
    const ENV_ID_CACHE_KEY = 'gainsight_env_id_cache_v1';

    function _loadTag(envId) {
        /* eslint-disable */
        (function(n,t,a,e,co){var i="aptrinsic";n[i]=n[i]||function(){
            (n[i].q=n[i].q||[]).push(arguments)},n[i].p=e;n[i].c=co;
            var r=t.createElement("script");r.async=!0,r.src=a+"?a="+e;
            var c=t.getElementsByTagName("script")[0];c.parentNode.insertBefore(r,c)
        })(window,document,"https://web-sdk.aptrinsic.com/api/aptrinsic.js",envId);
        /* eslint-enable */
    }

    function _getEnvId(callback) {
        const userId = app.user.get('id') || '';
        const origin = window.location.origin || '';
        const metadataHash = app.metadata.getHash() || '';
        const cacheKey = [ENV_ID_CACHE_KEY, origin, userId, metadataHash].join(':');

        if (_envIdCache) {
            callback(_envIdCache);
            return;
        }

        try {
            const cachedEnvId = window.localStorage.getItem(cacheKey);
            if (cachedEnvId) {
                _envIdCache = cachedEnvId;
                callback(cachedEnvId);
                return;
            }
        } catch {}

        const url = app.api.buildURL('gainsight/env-id');
        app.api.call('read', url, null, {
            success: function(response) {
                const envId = response && response.gainsightKey ? response.gainsightKey : null;

                if (envId) {
                    _envIdCache = envId;
                    try {
                        window.localStorage.setItem(cacheKey, envId);
                    } catch {}
                }

                callback(envId);
            },
            error: function() {
                // Keep a fallback to preserve telemetry in case the API request fails.
                callback(app.config.gainsightKey);
            },
        });
    }

    function _getPrimaryEmail(userBean) {
        const emails = userBean.get('email');
        const first = _.isArray(emails) ? _.first(emails) : null;
        return first ? first.email_address : '';
    }

    function _toList(value) {
        if (_.isArray(value)) {
            return _.map(value, function(item) {
                if (_.isObject(item)) {
                    return item.name || item.full_name || item.email_address || item.id || '';
                }
                return item;
            }).filter(Boolean).join(', ');
        }
        return value || '';
    }

    function _getSubObject(object, key) {
        if (!_.isObject(object)) {
            return '';
        }
        return object[key] || '';
    }

    function _getInstanceId(serverInfo) {
        const siteId = serverInfo.site_id;
        const siId = serverInfo.si_id;
        const siName = serverInfo.si_name;

        return siteId || siId || siName || window.location.hostname || window.location.origin;
    }

    function _identify() {
        const userBean = app.user;
        const serverInfo = app.metadata.getServerInfo();
        const instanceName = serverInfo.si_name || window.location.origin;
        const instanceId = _getInstanceId(serverInfo);
        const preferences = userBean.get('preferences') || {};
        const sugarLogicFields = userBean.get('sugar_logic_fields') || {};
        const isAdmin = userBean.get('is_admin');
        const userType = userBean.get('type') || (isAdmin ? 'Admin user' : 'Regular user');

        aptrinsic('identify',
            {
                id: userBean.get('id'),
                email: _getPrimaryEmail(userBean),
                firstName: _getSubObject(sugarLogicFields, 'first_name'),
                lastName: _getSubObject(sugarLogicFields, 'last_name'),
                fullName: userBean.get('full_name') || '',
                userName: userBean.get('user_name') || '',
                type: userType,
                status: _getSubObject(sugarLogicFields, 'status'),
                dateEntered: _getSubObject(sugarLogicFields, 'date_entered'),
                dateModified: _getSubObject(sugarLogicFields, 'date_modified'),
                lastLogin: _getSubObject(sugarLogicFields, 'last_login'),
                timeZone: _getSubObject(preferences, 'timezone'),
                tzOffset: _getSubObject(preferences, 'tz_offset'),
                datepref: _getSubObject(preferences, 'datepref'),
                timepref: _getSubObject(preferences, 'timepref'),
                language: _getSubObject(preferences, 'language'),
                defaultLocaleNameFormat: _getSubObject(preferences, 'default_locale_name_format'),
                currencyName: _getSubObject(preferences, 'currency_name'),
                defaultTeams: _toList(_getSubObject(preferences, 'default_teams')),
                myTeams: _toList(userBean.get('my_teams')),
                isManager: userBean.get('is_manager'),
                isTopLevelManager: userBean.get('is_top_level_manager'),
                reportsToId: userBean.get('reports_to_id') || '',
                reportsToName: userBean.get('reports_to_name') || '',
                roles: _toList(userBean.get('roles')),
                userEmailAddress: _getPrimaryEmail(userBean),
                addressStreet: userBean.get('address_street') || '',
                addressCity: userBean.get('address_city') || '',
                addressState: userBean.get('address_state') || '',
                addressCountry: userBean.get('address_country') || '',
                addressPostalcode: userBean.get('address_postalcode') || '',
                title: _getSubObject(sugarLogicFields, 'title'),
                department: _getSubObject(sugarLogicFields, 'department'),
                licenses: _toList(serverInfo.licenses),
                products: serverInfo.si_product_list,
                crmLicense: app.user.hasLicense('CURRENT'),
                revIntelligenceLicense: app.user.hasDiscoverLicense(),
                moduleList: _toList(userBean.get('module_list')),
                preferredLanguage: userBean.get('preferred_language') || _getSubObject(preferences, 'language'),
            },
            {
                id: instanceId,
                name: instanceName,
                instanceUrl: window.location.origin,
                flavor: serverInfo.flavor || '',
                version: serverInfo.version || '',
                env: serverInfo.env || '',
                hostEnvironment: serverInfo.host_environment || '',
                hostDesignation: serverInfo.host_designation || '',
                licenses: _toList(serverInfo.licenses),
                products: serverInfo.si_product_list,
                siProductList: _toList(serverInfo.si_product_list),
            },
        );
    }

    app.analytics.connectors.GainsightPX = {

        /*
         * Called on app:init.
         *
         * @member SUGAR.App.analytics.connectors.GainsightPX
         */
        initialize: function() {},

        /*
         * Called on app:start.
         *
         * The env ID is resolved from the server in configure(), so nothing is
         * needed here.
         *
         * @member SUGAR.App.analytics.connectors.GainsightPX
         */
        start: function() {},

        /*
         * Called on app:sync:complete. Loads the Gainsight PX tag and sends
         * user/account identity data.
         *
         * @member SUGAR.App.analytics.connectors.GainsightPX
         */
        configure: function() {
            if (_initialized) {
                return;
            }
            _initialized = true;

            _getEnvId(function(envId) {
                if (!envId) {
                    return;
                }

                _loadTag(envId);
                _identify();
            });
        },

        /*
         * Track a page view.
         *
         * Gainsight PX automatically tracks page views, so no action is
         * needed here.
         *
         * @member SUGAR.App.analytics.connectors.GainsightPX
         */
        trackPageView: function() {},

        /*
         * Track an event.
         *
         * @param {Object} event
         * @param {string} event.action Action of the event (ex. 'click').
         * @param {string} event.category Category of the event (ex. 'quick_create').
         * @member SUGAR.App.analytics.connectors.GainsightPX
         */
        trackEvent: function(event) {
            if (!event || !event.action) {
                return;
            }
            aptrinsic('track', event.action, event);
        },

        /*
         * Track an activity.
         *
         * @param {string} trackType Activity type.
         * @param {Object} trackData Activity metadata.
         * @member SUGAR.App.analytics.connectors.GainsightPX
         */
        track: function(trackType, trackData) {
            aptrinsic('track', trackType, trackData);
        },

        /*
         * Track a location change.
         *
         * Gainsight PX automatically tracks location via the browser URL, so
         * no action is needed here.
         *
         * @member SUGAR.App.analytics.connectors.GainsightPX
         */
        location: function() {},

        /*
         * Use browser URL for location tracking.
         *
         * Gainsight PX automatically uses the browser URL for location
         * tracking, so no action is needed here.
         *
         * @member SUGAR.App.analytics.connectors.GainsightPX
         */
        useBrowserUrl: function() {},

        /*
         * Set a tracker param.
         *
         * Gainsight PX does not support arbitrary key/value tracker params,
         * so no action is needed here.
         *
         * @member SUGAR.App.analytics.connectors.GainsightPX
         */
        set: function() {},
    };
})(SUGAR.App);
