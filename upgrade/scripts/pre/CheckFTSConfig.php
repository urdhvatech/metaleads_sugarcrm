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

use Elastica\Client as Client;
use Elastica\Request as Request;

/**
 * Check that the Sugar FTS Engine configuration is valid
 */
class SugarUpgradeCheckFTSConfig extends UpgradeScript
{
    public $order = 200;
    public $version = '7.1.5';

    /**
     * User-agent settings
     */
    public const USER_AGENT = 'SugarCRM';
    public const VERSION_UNKNOWN = 'unknown';

    /**
     * ES supported versions
     * the minimum check is the same as that in src/Elasticsearch/Adapter/Client.php.
     * don't need to check maximum version since it will be verified at runtime.
     * @var array[]
     */
    protected $supportedVersions = [
        ['version' => '5.4', 'operator' => '>='],
    ];

    /**
     * @var string, current installed opensearch version
     */
    protected $openSearchVersion;

    /**
     * indicator if server is OpenSearch
     * @var bool
     */
    protected $openSearch = false;

    /**
     * OpenSearch Version,
     * only list the minimum version, and don't check the maximum version.
     * the maximum will be checked at run time.
     * @var array
     */
    protected static array $supportedOpenSearchVersions = [
        ['version' => '1.0', 'operator' => '>='],
    ];

    public function run()
    {
        if (isMts()) {
            $this->log('ignore FTS check for MTS');
            return;
        }
        global $sugar_config;

        $ftsConfig = $sugar_config['full_text_engine'] ?? null;
        // Check that Elastic info is set (only currently supported search engine)
        if (empty($ftsConfig) || empty($ftsConfig['Elastic']) ||
            empty($ftsConfig['Elastic']['host']) || empty($ftsConfig['Elastic']['port'])
        ) {
            // error implies fail
            $this->error('Elastic Full Text Search engine needs to be configured on this Sugar instance prior to upgrade.');
            $this->error('Access Full Text Search configuration under Administration > Search.');
        } else {
            // Test Elastic FTS connection
            $ftsStatus = $this->getServerStatusElastic($ftsConfig['Elastic'] ?? []);

            if (!$ftsStatus['valid']) {
                $this->error('Connection test for Elastic Full Text Search engine failed.  Check your FTS configuration.');
                $this->error('Access Full Text Search configuration under Administration > Search.');
            }
        }
    }

    /**
     * Get the status of the Elastic server before the upgrade, using the raw Elastica library calls.
     *
     * Here we don't use src/Elasticsearch/Adapter/Client.php::verifyConnectivity() directly,
     * because the version checking there is tied to the from version of the upgrade. However, the Elastic
     * version may be changed during the upgrade. For instance, from Sugar 7.9 to 7.10, Elastic is
     * upgraded from 1.x to 5.x).
     *
     * @param array $config the Elastic server's host and port.
     * @return array
     */
    protected function getServerStatusElastic(array $config)
    {
        global $app_strings;
        $isValid = false;

        try {
            $client = new Client($config);
            $data = $client->request('', Request::GET)->getData();

            $version = $this->getServerVersion($data);
            $isValid = $this->isSupportedVersion($version);
            if ($isValid) {
                $displayText = $app_strings['LBL_EMAIL_SUCCESS'];
            } else {
                $displayText = $app_strings['ERR_ELASTIC_TEST_FAILED'];
                $this->error('Elastic version is unknown or unsupported, version:' . $version);
            }
        } catch (Exception $e) {
            $displayText = $e->getMessage();
            $this->error("Unable to get server status: $displayText");
        }

        return ['valid' => $isValid, 'status' => $displayText];
    }

    /**
     * get Elastic server version
     * @param array|null $data
     * @return string
     */
    protected function getServerVersion(?array $data): string
    {
        if (empty($data)) {
            return '';
        }

        if (isset($data['version']['distribution']) && $data['version']['distribution'] === 'opensearch') {
            $this->openSearch = true;
            $this->openSearchVersion = $data['version']['number'] ?? '';
            $version = $data['version']['minimum_index_compatibility_version'] ?? '';
        } else {
            $version = $data['version']['number'] ?? '';
        }

        return $version;
    }


    /**
     * Verify if Elasticsearch version meets the supported list.
     *
     * @param string $version Elasticsearch version to be checked
     * @return boolean
     */
    protected function isSupportedVersion(string $version) : bool
    {
        $result = true;
        if (!$this->openSearch) {
            foreach ($this->supportedVersions as $check) {
                $result = $result && version_compare($version, $check['version'], $check['operator']);
            }
        } else {
            // verify OpenSearch versions
            foreach (self::$supportedOpenSearchVersions as $check) {
                $result = $result && version_compare($this->openSearchVersion, $check['version'], $check['operator']);
            }
        }
        return $result;
    }
}
