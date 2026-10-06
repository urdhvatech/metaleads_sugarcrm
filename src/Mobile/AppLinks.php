<?php
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

declare(strict_types=1);

namespace Sugarcrm\Sugarcrm\Mobile;

use SugarConfig;
use Sugarcrm\Sugarcrm\CSP\Nonce;

class AppLinks
{
    public static function isEnabled(SugarConfig $sugarConfig): bool
    {
        $host_name = $sugarConfig->get('host_name', '');

        if ($host_name === 'localhost') {
            return true; // Enable for local development
        }
  
        if (empty($host_name)) {
            return false;
        }

        $mobile_app_links_domains = $sugarConfig->get('mobile_app_links_domains', []);

        foreach ($mobile_app_links_domains as $domain) {
            // domain name pattern should start with a dot
            if ($domain[0] === '.' && str_ends_with($host_name, $domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks whether device is in list of compatible devices of Nomad (mobile js app)
     *
     * @return 'ios'|'android'|null
     */
    public static function getMobilePlatform(): ?string
    {
        $platform = self::getAppPlatform();

        if (in_array($platform, ['ios', 'android'])) {
            return $platform;
        }

        return null;
    }

    /**
     * Returns the device platform based on the app_platform cookie.
     *
     * @return 'ios'|'android' | 'desktop' | null
     */
    private static function getAppPlatform(): ?string
    {
        $platformCookieName = 'app_platform';
        if (isset($_COOKIE[$platformCookieName])) {
            return $_COOKIE[$platformCookieName];
        }
        return null;
    }

    /**
     * Determines the platform based on the user agent.
     * Returns 'ios', 'android', 'macintosh' or 'desktop' if not recognized.
     *
     * @return 'ios' | 'android' | 'macintosh' | 'desktop'
     */
    private static function getPlatformBasedOnUA()
    {
        if (empty($_SERVER['HTTP_USER_AGENT'])) {
            return null;
        }

        $ua = strtolower((string)$_SERVER['HTTP_USER_AGENT']);

        $isIosDevice = preg_match('/(iphone|ipod|ipad)/i', $ua);
        if ($isIosDevice) {
            return 'ios';
        }

        // check for Chrome in Android
        $isAndroid = preg_match('/android/i', $ua);
        if ($isAndroid) {
            return 'android';
        }

        // Safari on iPad has the same user agent as Safari on MacOS, so we need to check for it separately
        $isMac = preg_match('/macintosh/i', $ua);
        if ($isMac) {
            return 'macintosh';
        }

        return 'desktop';
    }

    /**
     * Sets the app platform cookie based on the user agent.
     * If the cookie is already set, it does nothing.
     * If the user agent is not recognized, it defaults to 'desktop'.
     * For Windows and Macintosh, it checks for touch support to determine if it's a mobile device.
     */
    public static function detectDevicePlatform()
    {
        $platformCookieName = 'app_platform';
        if (isset($_COOKIE[$platformCookieName])) {
            return;
        }

        $uaAgentPlatform = self::getPlatformBasedOnUA();

        $cookieTime = time() + 3600 * 24 * 365 * 1; // 1 year
        $cookiePath = '/';

        if ($uaAgentPlatform === 'ios' || $uaAgentPlatform === 'android' ||  $uaAgentPlatform === 'desktop') {
            \SugarApplication::setCookie($platformCookieName, $uaAgentPlatform, $cookieTime, $cookiePath);
            return;
        }

        // macintosh handling
        $isMac = $uaAgentPlatform === 'macintosh';

        if (!$isMac) { // safeguard against unexpected values
            \SugarApplication::setCookie($platformCookieName, 'desktop', $cookieTime, $cookiePath);
            return;
        }

        $nonce = Nonce::create();
        // for macintosh we can distinguish mobile device only by checking touch functionality support on the client side
        echo <<<EOF
        <script type="text/javascript" nonce="{$nonce}">
            (function getMobileDevicePlatform() {

            const hasTouch = window.navigator.maxTouchPoints > 0;
            const platform = "$platformCookieName=" + (hasTouch ? "ios" : "desktop");
            const expires = "expires=" + new Date(Number($cookieTime) * 1000).toUTCString();
            const path = "path=/";
            
            document.cookie = [platform, expires, path].join(";");
            location.reload();
            })();
        </script>
EOF;
        exit();
    }
}
