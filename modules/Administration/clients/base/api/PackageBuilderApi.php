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

use Sugarcrm\Sugarcrm\PackageBuilder\PackageBuilder;
use Sugarcrm\Sugarcrm\PackageManager\PackageManager;
use Sugarcrm\Sugarcrm\Security\HttpClient\ExternalResourceClient;
use Sugarcrm\Sugarcrm\Security\Validator\Constraints\File;
use Sugarcrm\Sugarcrm\Security\Validator\Validator;

/**
 *
 * PackageBuilder API
 *
 */
class PackageBuilderApi extends SugarApi
{
    /**
     * Register endpoints
     * @return array
     */
    public function registerApiRest()
    {
        return [
            'extractCustomizations' => [
                'reqType' => 'POST',
                'path' => ['Administration', 'package', 'customizations'],
                'pathVars' => ['', ''],
                'method' => 'extractCustomizations',
                'shortHelp' => 'Extract customizations',
                'longHelp' => 'include/api/help/administration_package_customizations_post_help.html',
                'exceptions' => [
                    'SugarApiExceptionInvalidParameter',
                    'SugarApiExceptionMissingParameter',
                    'SugarApiExceptionNotAuthorized',
                ],
            ],
            'createPackage' => [
                'reqType' => 'POST',
                'path' => ['Administration', 'package'],
                'pathVars' => ['', ''],
                'method' => 'createPackage',
                'shortHelp' => 'Create a package',
                'longHelp' => 'include/api/help/administration_package_post_help.html',
                'exceptions' => [
                    'SugarApiExceptionRequestMethodFailure',
                    'SugarApiExceptionInvalidParameter',
                    'SugarApiExceptionMissingParameter',
                    'SugarApiExceptionNotAuthorized',
                ],
            ],
            'getPackage' => [
                'reqType' => 'GET',
                'path' => ['Administration', 'package', '?'],
                'pathVars' => ['', '', 'id'],
                'method' => 'getPackage',
                'shortHelp' => 'Get the content of a package',
                'longHelp' => 'include/api/help/administration_package_get_help.html',
                'exceptions' => [
                    'SugarApiExceptionNotFound',
                    'SugarApiExceptionInvalidParameter',
                    'SugarApiExceptionMissingParameter',
                    'SugarApiExceptionNotAuthorized',
                ],
            ],
            'uploadRemotePackage' => [
                'reqType' => 'POST',
                'path' => ['Administration', 'package', 'remote'],
                'pathVars' => ['', '', ''],
                'method' => 'uploadRemotePackage',
                'shortHelp' => 'Upload the given remote package to local instance',
                'longHelp' => 'include/api/help/administration_package_remote_post_help.html',
                'exceptions' => [
                    'PackageExistsException',
                    'SugarApiExceptionNotFound',
                    'SugarApiExceptionInvalidParameter',
                    'SugarApiExceptionMissingParameter',
                    'SugarApiExceptionNotAuthorized',
                ],
            ],
            'fetchCustomizationsData' => [
                'reqType' => 'POST',
                'path' => ['Administration', 'package', 'data'],
                'pathVars'  => ['', '', ''],
                'method' => 'fetchCustomizationsData',
                'shortHelp' => 'Fetch customizations\' data',
                'longHelp' => 'include/api/help/administration_customizations_data_post_help.html',
                'exceptions' => [
                    'SugarApiExceptionInvalidParameter',
                    'SugarApiExceptionNotAuthorized',
                ],
            ],
            'getRemotePackages' => [
                'reqType' => 'POST',
                'path' => ['Administration', 'package', 'getRemotePackages'],
                'pathVars' => ['', '', ''],
                'method' => 'getRemotePackages',
                'shortHelp' => 'Load packages data from remote instance',
                'longHelp' => 'include/api/help/administration_package_connection_post_help.html',
                'exceptions' => [
                    'SugarApiExceptionInvalidParameter',
                    'SugarApiExceptionNotAuthorized',
                ],
            ],
        ];
    }

    /**
     * Get PackageBuilder instance
     * @return PackageBuilder
     */
    protected function getPackageBuilder()
    {
        return new PackageBuilder();
    }

    /**
     * Ensure current user has admin permissions
     * @throws SugarApiExceptionNotAuthorized
     */
    protected function ensureAdminUser()
    {
        if (empty($GLOBALS['current_user']) || !$GLOBALS['current_user']->isAdmin()) {
            throw new SugarApiExceptionNotAuthorized(
                $GLOBALS['app_strings']['EXCEPTION_NOT_AUTHORIZED']
            );
        }
    }

    /**
     * Get access token from target system
     * @param array $connectionInfo
     * @return string
     */
    protected function getAccessToken(array $connectionInfo): string
    {
        global $sugar_config;

        $accessToken = '';
        $baseUrl = $connectionInfo['url'];
        $user = $connectionInfo['user'];
        $password = $connectionInfo['pass'];

        $getTokenUrl = $baseUrl . '/rest/v11/oauth2/token?platform=base';
        $postParams = [
            'grant_type' => 'password',
            'client_id' => 'sugar',
            'username' => $user,
            'password' => $password,
            'platform' => 'base',
            'client_secret' => '',
            'current_language' => 'en_us',
        ];

        $this->validateScheme($baseUrl);

        try {
            $client = $this->getExternalResourceClient();
            $result = $client->post($getTokenUrl, json_encode($postParams), ['Content-Type' => 'application/json']);

            $response = json_decode($result->getBody()->getContents(), true); // Request content
        } catch (Exception $e) {
            $GLOBALS['log']->fatal('Access token request failed with error for Package Builder: ' . $e->getMessage());
            throw new Exception(translate('LBL_PACKAGE_BUILDER_CONNECTION_FAILED', 'Administration'));
        }

        if (is_array($response) && isset($response['error'])) {
            $GLOBALS['log']->warning('Error response from token request for Package Builder: ' . $response['error']);
            throw new SugarApiExceptionNotAuthorized(translate('LBL_PACKAGE_BUILDER_ACCESS_DENIED', 'Administration'));
        }

        if (is_array($response) && isset($response['access_token'])) {
            $accessToken = $response['access_token'];
        } else {
            $GLOBALS['log']->fatal('Invalid token from token request for Package Builder');
            throw new Exception(translate('LBL_PACKAGE_BUILDER_CONNECTION_FAILED', 'Administration'));
        }

        return $accessToken;
    }

    /**
     * Throw exception if scheme is not valid
     * @param $url
     * @return void
     * @throws SugarApiExceptionInvalidParameter
     */
    protected function validateScheme($url): void
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);

        if ($scheme !== 'https' || !isHTTPS()) {
            throw new \SugarApiExceptionInvalidParameter(translate('LBL_PACKAGE_BUILDER_INVALID_SCHEME', 'Administration'));
        }
    }

    /**
     * Return ExternalResourceClient object
     *
     * @param float $timeout Read timeout in seconds, specified by a float (e.g. 10.5)
     */
    protected function getExternalResourceClient($timeout = 10): ExternalResourceClient
    {
        global $sugar_config;

        $configTimeout = $sugar_config['max_external_request_time'] ?? 600;
        $requestTimeout = min($configTimeout, $timeout);

        return new ExternalResourceClient($requestTimeout);
    }

    /**
     * Validate file path
     *
     * @param string $initPath
     * @return void
     * @throws ViolationException
     */
    protected function validateFilePath(string $initPath): void
    {
        $filePath = clean_path($initPath);

        $packageManagerDir = (new PackageManager())->getBaseUpgradeDir();
        $fileConstraint = new File([
            'baseDirs' => [realpath($packageManagerDir)],
        ]);
        $violations = Validator::getService()->validate($filePath, $fileConstraint);

        if ($violations->count()) {
            throw new ViolationException('Invalid file path', $violations);
        }
    }

    /**
     * Extract customizations in one or more categories
     * @param ServiceBase $api
     * @param array $args
     * @return array
     * @throws SugarApiExceptionNotAuthorized
     * @throws SugarApiExceptionInvalidParameter
     */
    public function extractCustomizations(ServiceBase $api, array $args): array
    {
        $this->ensureAdminUser();
        $this->requireArgs($args, ['elementsToFetch']);
        if (!is_array($args['elementsToFetch'])) {
            throw new SugarApiExceptionInvalidParameter('Array elementsToFetch should contain categories');
        }
        $packageBuilder = $this->getPackageBuilder();
        return $packageBuilder->extract($args['elementsToFetch']);
    }

    /**
     * Create package
     * @param ServiceBase $api
     * @param array $args
     * @return array|bool
     * @throws SugarApiExceptionNotAuthorized
     * @throws SugarApiExceptionInvalidParameter
     */
    public function createPackage(ServiceBase $api, array $args)
    {
        $this->ensureAdminUser();
        $this->requireArgs($args, ['customizations', 'packageName']);
        if (!is_array($args['customizations'])) {
            throw new \SugarApiExceptionInvalidParameter('Require array for customizations');
        }
        if (!is_string($args['packageName'])) {
            throw new \SugarApiExceptionInvalidParameter('Require string for packageName');
        }
        $packageBuilder = $this->getPackageBuilder();
        return $packageBuilder->create($args['customizations'], $args['packageName']);
    }

    /**
     * Retrieve data for customizations
     * @param ServiceBase $api
     * @param array $args
     * @return array
     * @throws SugarApiExceptionNotAuthorized
     * @throws SugarApiExceptionInvalidParameter
     */
    public function fetchCustomizationsData(ServiceBase $api, array $args): array
    {
        $this->ensureAdminUser();
        $packageBuilder = $this->getPackageBuilder();
        $data = [];
        if (!empty($args['dashboards'])) {
            if (!is_array($args['dashboards'])) {
                throw new SugarApiExceptionInvalidParameter('Array is expected for dashboards');
            }
            $packageBuilder->getDashboardsData($args['dashboards']);
            $data['dashboards'] = $args['dashboards'];
        }
        if (!empty($args['advanced_workflows'])) {
            if (!is_array($args['advanced_workflows'])) {
                throw new SugarApiExceptionInvalidParameter('Array is expected for advanced_workflows');
            }
            $packageBuilder->getAdvancedWorkflowsData($args['advanced_workflows']);
            $data['advanced_workflows'] = $args['advanced_workflows'];
        }
        return $data;
    }

    /**
     * This function will test if the connection info are correct,
     * If correct, will also retrieve the installed packges from the other instance
     *
     * @param ServiceBase $api
     * @param array $args
     * @return array
     * @throws SugarApiExceptionNotAuthorized
     * @throws SugarApiExceptionInvalidParameter
     * @throws LogicException
     * @throws RuntimeException
     */
    public function getRemotePackages(ServiceBase $api, array $args): array
    {
        $this->ensureAdminUser();
        $this->requireArgs($args, ['connectionInfo']);
        // url, user, password
        $connectionInfo = $args['connectionInfo'];
        $this->requireArgs($connectionInfo, ['url', 'user', 'pass']);
        $connectionResponse = [
            'access' => false,
            'otherInstancePackages' => [],
        ];

        $accessToken = $this->getAccessToken($connectionInfo);
        // If access token is not returned, connection failed
        if (empty($accessToken)) {
            return $connectionResponse;
        }
        // Connection info were correct, we have access to the other instance
        $connectionResponse['access'] = true;

        // If connection was successful, retrieve the installed packages from the other instance
        $connectionResponse['otherInstancePackages'] = $this->getOtherInstancePackages($connectionInfo['url'], $accessToken);
        return $connectionResponse;
    }

    /**
     * Return the installed packages from the given instance
     *
     * @param string $url
     * @param string $accessToken
     * @return array
     * @throws RuntimeException
     */
    protected function getOtherInstancePackages(string $url, string $accessToken): array
    {
        $packages = [];
        // Build request options
        $otherInstanceUrl = $url . '/rest/v11/Administration/packages';
        $options = [
            'OAuth-Token' => $accessToken,
        ];

        $client = $this->getExternalResourceClient();
        $result = $client->get($otherInstanceUrl, $options);
        $response = json_decode($result->getBody()->getContents(), true);

        if (is_array($response) && isset($response['packages'])) {
            $packages = $response['packages'];
        }

        return $packages;
    }

    /**
     * Get a package.
     *
     * @param ServiceBase $api
     * @param array $args
     * @return array
     * @throws SugarApiExceptionNotAuthorized
     * @throws SugarApiExceptionInvalidParameter
     */
    public function getPackage(ServiceBase $api, array $args)
    {
        $this->ensureAdminUser();
        $this->requireArgs($args, ['id']);
        if (!is_string($args['id']) || empty($args['id'])) {
            throw new SugarApiExceptionInvalidParameter();
        }
        $filePath = $this->getFilePath($args['id']);

        if ($filePath && ($content = $this->getFileContent($filePath)) !== false) {
            return [
                'name' => basename($filePath),
                'content' => base64_encode($content),
            ];
        } else {
            throw new SugarApiExceptionNotFound('Package not found');
        }
    }

    /**
     * Get file content
     * @param string $filename
     * @return string|false
     */
    public function getFileContent(string $filename)
    {
        return @file_get_contents($filename);
    }

    /**
     * Upload a package from remote instance to local instance
     *
     * @param ServiceBase $api
     * @param array $args
     * @return array
     * @throws SugarApiExceptionNotAuthorized
     * @throws SugarApiExceptionInvalidParameter
     */
    public function uploadRemotePackage(ServiceBase $api, array $args)
    {
        $this->ensureAdminUser();
        $this->requireArgs($args, ['id', 'connectionInfo']);

        // url, user, password
        $connectionInfo = $args['connectionInfo'];
        $this->requireArgs($connectionInfo, ['url', 'user', 'pass']);
        $uploadResponse = [
            'access' => false,
            'fileInstallId' => false,
        ];

        // Access token required for the upload request
        $accessToken = $this->getAccessToken($connectionInfo);

        // If access token is not returned, connection failed
        if (empty($accessToken)) {
            throw new SugarApiExceptionNotAuthorized();
        }

        // Connection info were correct, we have access to the other instance
        $uploadResponse['access'] = true;

        // Remove staged packages
        if (!empty($args['stagedVersions'])) {
            $this->removeStagedVersions($args['stagedVersions']);
        }

        $package = $this->getRemotePackage($connectionInfo['url'], $accessToken, $args['id']);

        if (is_array($package) && isset($package['content'])) {
            $content = base64_decode($package['content']);
            $name = $package['name'] ?? '';
            $packageData = $this->addPackage($name, $content);

            if (is_array($packageData) && isset($packageData['status']) &&
                $packageData['status'] === 'staged') {
                $uploadResponse['fileInstallId'] = $packageData['file_install'] ?? '';
            }
        }
        if (!$uploadResponse['fileInstallId']) {
            throw new SugarApiExceptionNotFound();
        }

        return $uploadResponse;
    }

    /**
     * Get the path to the zip file for a given package
     *
     * @param string $fileId
     * @return string
     * @throws Exception
     */
    protected function getFilePath(string $fileId): string
    {
        global $db;
        $qb = $db->getConnection()->createQueryBuilder();
        $qb->select('filename')
            ->from('upgrade_history')
            ->where($qb->expr()->eq('id', $qb->expr()->literal($fileId)))
            ->andWhere('deleted = 0');
        $stmt = $qb->executeQuery();
        $result = $stmt->fetchAssociative();
        return is_array($result) && isset($result['filename']) ? $result['filename'] : '';
    }

    /**
     * When we try to upload a zip, we must be sure there are no other versions of this package uploaded/staged there
     *
     * @param array $stagedVersions
     * @return void
     */
    protected function removeStagedVersions(array $stagedVersions): void
    {
        foreach ($stagedVersions as $fileId) {
            $this->deleteZip($fileId);
        }
    }

    /**
     * Get a package from remote instance
     *
     * @param string $url
     * @param string $accessToken
     * @param string $id
     * @return array
     */
    protected function getRemotePackage(string $url, string $accessToken, string $id): array
    {
        $options = [
            'OAuth-Token' => $accessToken,
        ];

        $instanceUrl = "$url/rest/v11/Administration/package/$id";
        $client = $this->getExternalResourceClient(600);
        $result = $client->get($instanceUrl, $options);
        return json_decode($result->getBody()->getContents(), true);
    }

    /**
     * Add a package to local instance
     *
     * @param string $name
     * @param string $content
     * @return array
     */
    protected function addPackage(string $name, string $content): array
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'API');
        file_put_contents($tempFile, $content);
        // Mock a $_FILES array member, adding in _SUGAR_API_UPLOAD to allow file uploads
        $_FILES['upgrade_zip'] = [
            'name' => $name,
            'type' => get_file_mime_type($tempFile),
            'tmp_name' => $tempFile,
            'error' => 0,
            'size' => filesize($tempFile),
            '_SUGAR_API_UPLOAD' => true,
        ];
        $packageApi = new PackageApiRest();
        return $packageApi->uploadPackage(new RestService(), []);
    }

    /**
     * Delete given zip file from upload
     *
     * @param string $fileInstallId
     * @return void
     * @throws SugarApiException
     */
    protected function deleteZip(string $fileInstallId): void
    {
        try {
            $upgradeHistory = $this->getUpgradeHistoryByIdOrFail($fileInstallId);
            $packageManager = new PackageManager();
            $packageManager->deletePackage($upgradeHistory);
        } catch (SugarException $e) {
            throw $this->getSugarApiException($e, 'delete_package_error');
        }
    }

    /**
     * Convert SugarException into SugarApiException
     * @param SugarException $sugarException
     * @param string $errorLabel
     * @return SugarApiException
     */
    protected function getSugarApiException(SugarException $sugarException, string $errorLabel): SugarApiException
    {
        $apiException = new SugarApiException($sugarException->getMessage(), null, 'Administration', 0, $errorLabel);
        $apiException->extraData = $sugarException->extraData;
        return $apiException;
    }

    /**
     * Return UpgradeHistory by ID or throw no found exception
     * @param string $id
     * @return UpgradeHistory
     * @throws SugarApiExceptionNotFound
     */
    protected function getUpgradeHistoryByIdOrFail(string $id): UpgradeHistory
    {
        $upgradeHistory = BeanFactory::retrieveBean('UpgradeHistory', $id);

        if (is_null($upgradeHistory) || empty($upgradeHistory->id)) {
            throw new SugarApiExceptionNotFound('ERR_UW_NO_PACKAGE', null, 'Administration');
        }

        return $upgradeHistory;
    }
}
