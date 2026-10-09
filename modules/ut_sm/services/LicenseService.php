<?php
/**
 * This file is part of the "Meta Leads" package.
 *
 * @package Meta Leads
 * @author Urdhva Tech <contact@urdhva-tech.com>
 * @link https://www.urdhva-tech.com
 * @copyright Urdhva Tech
 * @license As specified in the License Agreement supplied with this package.
 */
if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

/**
 * License gate for Meta Leads module features.
 */
class UTSMLicenseService
{
    const MODULE = 'ut_sm';

    /**
     * @return bool
     */
    public static function hasLicenseKey()
    {
        require_once 'modules/' . self::MODULE . '/license/SugarAILicense.php';

        $key = trim((string) UT_SM_SugarAILicense::getKey(self::MODULE));

        return $key !== '';
    }

    /**
     * True only when a prior validation was stored with validated=true.
     *
     * @return bool
     */
    public static function hasPassedValidation()
    {
        require 'modules/' . self::MODULE . '/license/config.php';
        require_once 'modules/Administration/Administration.php';

        $administration = new Administration();
        $administration->retrieveSettings();

        if (empty($sugarai_config['shortname'])) {
            return false;
        }

        $settingKey = 'SugarAI_' . $sugarai_config['shortname'];
        $raw = !empty($administration->settings[$settingKey])
            ? trim((string) $administration->settings[$settingKey])
            : '';

        if ($raw === '') {
            return false;
        }

        $stored = @unserialize(base64_decode($raw));
        if (!is_array($stored) || empty($stored['last_result']) || !is_array($stored['last_result'])) {
            return false;
        }

        $lastResult = $stored['last_result'];
        if (empty($lastResult['success']) || $lastResult['success'] !== true) {
            return false;
        }

        if (empty($lastResult['result']) || !is_array($lastResult['result'])) {
            return false;
        }

        return !empty($lastResult['result']['validated']);
    }

    /**
     * @return bool
     */
    public static function isValid()
    {
        //return false;
        if (!self::hasLicenseKey()) {
            return false;
        }

        if (!self::hasPassedValidation()) {
            return false;
        }

        require_once 'modules/' . self::MODULE . '/license/SugarAILicense.php';

        return UT_SM_SugarAILicense::isValid(self::MODULE) === true;
    }

    /**
     * Send the browser to a sidecar route.
     *
     * @param string $route Hash route without a leading #
     */
    public static function redirectToSidecar($route)
    {
        $route = ltrim((string) $route, '#');
        $url = 'index.php#' . $route;
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Meta Leads</title></head><body>'
            . '<script>window.location.replace('
            . json_encode($url, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)
            . ');</script></body></html>';
        sugar_cleanup(true);
    }

    /**
     * Redirect browser requests to the license screen when validation fails.
     */
    public static function redirectToLicenseIfInvalid()
    {
        if (self::isValid()) {
            return;
        }

        self::redirectToSidecar('bwc/index.php?module=ut_sm&action=license');
    }

    /**
     * @return array{ok:bool,status:string,error?:string}
     */
    public static function importBlockedResult()
    {
        $GLOBALS['log']->warn('ut_sm: lead import skipped because license is not valid');

        return array(
            'ok' => false,
            'status' => 'skipped',
            'error' => 'License is not valid',
        );
    }
}
