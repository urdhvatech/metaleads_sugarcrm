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

namespace Sugarcrm\Sugarcrm\GAI;

use TimeDate;
use SugarQuery;
use DateTime;
use SugarBean;
use BeanFactory;
use Exception;
use SugarApiException;
use DBManagerFactory;
use GuzzleHttp\Exception\GuzzleException;
use Sugarcrm\Sugarcrm\GAI\Constants\GAIConstants;
use Sugarcrm\Sugarcrm\GAI\Client\Constants\GAIType;
use Sugarcrm\Sugarcrm\GAI\Adapter\AdapterFactory;

class Helper
{
    /**
     * Get the configuration from the GAI service.
     *
     * @param array $payload The payload to send to the GAI service.
     * @param mixed $config The configuration to use, if available.
     *
     * @return array The configuration data from the GAI service.
     */
    public static function getConfig(array $payload, $config)
    {
        if ($config) {
            return $config;
        }

        try {
            $service = new Service();
            $response = $service->getConfig($payload);

            $responseData = \GuzzleHttp\json_decode($response->getBody(), true);

            return $responseData;
        } catch (GuzzleException $e) {
            throw $e;
        }
    }

    /**
     * Get the configuration for bulk processing from the GAI service.
     * @param array $payload The payload to send to the GAI service.
     *
     * @return array The configuration data from the GAI service.
     */
    public static function getConfigBulk(array $payload)
    {
        try {
            $service = new Service();
            $response = $service->getConfigBulk($payload);

            $responseData = \GuzzleHttp\json_decode($response->getBody(), true);

            return $responseData;
        } catch (GuzzleException $e) {
            throw $e;
        }
    }

    /**
     * Resolve the configuration for a specific use case type.
     *
     * @param array $data The data received from the GAI service.
     * @param string $usecaseType The type of use case for which to resolve the configuration.
     *
     * @return array The resolved configuration containing module data and related data configurations.
     */
    public static function resolveConfig(array $data, string $usecaseType): array
    {
        $log = \LoggerManager::getLogger();

        if (isset($data['status']) && $data['status'] === 'error') {
            $log->error('GAI: invalid config from GAI Service' . json_encode($data));

            throw new SugarApiException('Invalid response from GAI Service [Config]');
        }

        if (!isset($data['body'])) {
            $log->error('GAI: invalid config from GAI Service, body is not present on response');

            throw new SugarApiException('Invalid response from GAI Service [Config]');
        }

        $body = $data['body'];
        $usecaseTypes = $body['usecaseTypes'] ?? [];

        if (!isset($usecaseTypes[$usecaseType])) {
            throw new Exception("Invalid usecaseType: '$usecaseType' not found.");
        }

        $config = $usecaseTypes[$usecaseType];

        $moduleDataConfig = [];
        $relatedDataConfig = [];

        foreach ($config as $item) {
            self::traverseConfig($item, $moduleDataConfig, $relatedDataConfig);
        }

        return ['moduleDataConfig' => $moduleDataConfig, 'relatedDataConfig' => $relatedDataConfig];
    }

    /**
     * Resolve the configuration for bulk processing.
     *
     * @param string $parentModule The parent module for which to resolve the configuration.
     *
     * @return array The resolved configuration containing use case types and related data configurations.
     */
    public static function resolveConfigBulk(string $parentModule): array
    {
        try {
            $configData = [
                'parentObjectType' => $parentModule,
                'usecaseType' => 'gai_summary',
                'adapterType' => GAIType::GAI_CONFIG,
            ];

            $configAdapter = AdapterFactory::getDataAdapterInstance($configData);
            $configPayload = $configAdapter->getConfigData();

            $configDataResp = self::getConfigBulk($configPayload);

            $log = \LoggerManager::getLogger();

            if (isset($configDataResp['status']) && $configDataResp['status'] === 'error') {
                $log->error('GAI: invalid config from GAI Service' . json_encode($configDataResp));

                throw new SugarApiException('Invalid response from GAI Service [ConfigBulk]');
            }

            if (!isset($configDataResp['body'])) {
                $log->error('GAI: invalid config from GAI Service, body is not present on response');

                throw new SugarApiException('Invalid response from GAI Service [ConfigBulk]');
            }

            $body = $configDataResp['body'];
            $usecaseTypes = $body['usecaseTypes'] ?? [];

            if (!isset($configDataResp['body']['usecaseTypes'])) {
                $log->error('GAI: invalid config from GAI Service, usecaseTypes is not present on response');

                throw new SugarApiException('Invalid response from GAI Service [ConfigBulk]');
            }

            if (!isset($configDataResp['body']['usecaseTypes']['module']) || !isset($configDataResp['body']['usecaseTypes']['relatedDataConfig'])) {
                $log->error('GAI: invalid config from GAI Service, relatedDataConfig and module is not present on response');

                throw new SugarApiException('Invalid response from GAI Service [ConfigBulk]');
            }

            return $usecaseTypes;
        } catch (GuzzleException $e) {
            throw $e;
        }
    }

    /**
     * Recursively traverse the configuration array to extract module data and related data configurations.
     *
     * @param array $config The configuration array to traverse.
     * @param array &$moduleDataConfig The array to store module data configurations.
     * @param array &$relatedDataConfig The array to store related data configurations.
     *
     * @return void
     */
    private static function traverseConfig(array $config, array &$moduleDataConfig, array &$relatedDataConfig): void
    {
        foreach ($config as $key => $value) {
            if ($key === 'fields' && is_array($value)) {
                $moduleDataConfig = array_merge($moduleDataConfig, $value);
            } elseif ($key === 'relatedModules' && is_array($value)) {
                foreach ($value as $module) {
                    $relatedDataConfig[] = [
                        'module' => $module['name'],
                        'relationship' => $module['relationship'],
                        'fields' => $module['fields'] ?? [],
                        'maxDataRange' => strval($config['maxDataTimeRange'] ?? ''),
                        'maxRecordCount' => $config['maxRecordsPerModule'] ?? 20,
                    ];
                }
            } elseif (is_array($value)) {
                self::traverseConfig($value, $moduleDataConfig, $relatedDataConfig);
            }
        }
    }

    /**
     * Get the token usage of the vendor from the GAI service
     */
    public static function getTokenUsage()
    {
        $payload = [
            'adapterType' => GAIType::GAI_TOKEN_USAGE,
        ];

        try {
            $tokenAdapter = AdapterFactory::getDataAdapterInstance($payload);
            $tokenPayload = $tokenAdapter->getTokenUsageData($payload);

            $service = new Service();
            $tokenResponse = $service->getTokenUsage($tokenPayload);

            $response = \GuzzleHttp\json_decode($tokenResponse->getBody(), true);

            $log = \LoggerManager::getLogger();

            $exceptionMessage = 'Invalid retrieve token from GAI Service ';

            if (!array_key_exists('status', $response) || $response['status'] !== 'completed') {
                $log->error('GAI: ' . $exceptionMessage . json_encode($response));

                throw new SugarApiException($exceptionMessage . '[Token Usage][Status]');
            }

            if (array_key_exists('body', $response) && is_array($response['body'])) {
                $responseBody = $response['body'];

                if (array_key_exists('utilization', $responseBody)) {
                    $utilization = $responseBody['utilization'];

                    return $utilization;
                } else {
                    $log->error('GAI: ' . $exceptionMessage . json_encode($response));

                    throw new SugarApiException($exceptionMessage . '[Token Usage][Utilization]');
                }
            } else {
                $log->error('GAI: ' . $exceptionMessage . json_encode($response));

                throw new SugarApiException($exceptionMessage . '[Token Usage][Body]');
            }
        } catch (GuzzleException $e) {
            throw $e;
        }
    }

    /**
     * Get the user preferred language
     *
     * @return string
     */
    public static function getCurrentUserPreferredLanguage()
    {
        global $current_user;
        global $sugar_config;

        $defaultLanguage = 'en_us';
        $userLanguage = $current_user->preferred_language;

        $preferredLanguage = isset($userLanguage) && is_string($userLanguage) && !empty($userLanguage) ?
            $userLanguage : $sugar_config['default_language'];

        if (isset($preferredLanguage) && is_string($preferredLanguage) && !empty($preferredLanguage)) {
            return $preferredLanguage;
        }

        $exceptionMessage = 'GAI: Invalid user preferred language. English language will be used.';

        $log = \LoggerManager::getLogger();
        $log->error($exceptionMessage);

        return $defaultLanguage;
    }

    /**
     * Get the status code from the error
     * @param \Throwable $error
     *
     * @return int
     */
    public static function getStatusCodeFromError(\Throwable $error): int
    {
        $statusCode = 0;

        if (method_exists($error, 'getHttpCode')) {
            $statusCode = $error->getHttpCode();
        } elseif (method_exists($error, 'getStatusCode')) {
            $statusCode = $error->getStatusCode();
        } elseif (method_exists($error, 'getResponse')) {
            $response = $error->getResponse();
            $statusCode = $response->getStatusCode();
        } else {
            $statusCode = $error->getCode();
        }

        return $statusCode;
    }

    /**
     * Format the date modified to be displayed
     *
     * @param SugarBean $bean
     * @param string $value
     * @param string $fieldName
     *
     * @return string
     */
    public static function formatDateAsIso(SugarBean $bean, string $fieldName)
    {
        global $timedate;
        $dateModifiedDef = $bean->field_defs[$fieldName];
        $dbType = DBManagerFactory::getInstance()->getFieldType($dateModifiedDef);

        $date = $timedate->fromDbType($bean->{$fieldName}, $dbType);

        if (!($date instanceof DateTime)) {
            $date = new DateTime();
        }

        $dateModified = $timedate->asIso($date);

        return $dateModified;
    }

    /**
     * Check if first date is greater than second date
     *
     * @param array $firstDate  [date, modify]   ex: ['date' => '2021-01-01', 'modify' => '+1 day']
     * @param array $secondDate [date, modify]   ex: ['date' => '2022-01-01', 'modify' => '-1 day']
     *
     * @return bool
     */
    public static function dateCompare(array $firstDate, array $secondDate): bool
    {
        $date1 = $firstDate['date'];
        $date2 = $secondDate['date'];

        $modifyDate1 = null;
        $modifyDate2 = null;

        if (array_key_exists('modify', $firstDate)) {
            $modifyDate1 = $firstDate['modify'];
        }

        if (array_key_exists('modify', $secondDate)) {
            $modifyDate2 = $secondDate['modify'];
        }

        $timeDate = TimeDate::getInstance();

        $firstDateObj = $timeDate->fromDb($date1);

        if ($modifyDate1) {
            $firstDateObj->modify($modifyDate1);
        }

        $firstDateValue = $timeDate->asDb($firstDateObj);

        $secondDateObj = $timeDate->fromDb($date2);

        if ($modifyDate2) {
            $secondDateObj->modify($modifyDate2);
        }

        $secondDateValue = $timeDate->asDb($secondDateObj);

        return $firstDateValue > $secondDateValue;
    }

    /**
     * Compress the data for the GAI service
     *
     * @param array $data
     *
     * @return string
     */
    public static function compressData(array $data): string
    {
        return base64_encode(gzcompress(json_encode($data)));
    }

    /**
     * Check if the bean is blocked for whatever reason
     *
     * @param SugarBean $summaryBean
     * @param string $status
     * @param string $maxExceededDate
     * @return bool
     */
    public static function isBeanInStalledState(SugarBean $summaryBean, string $status, string $maxExceededDate): bool
    {
        $isStalledState = false;

        if ($summaryBean->status === $status) {
            $isStalledState = true;
        }

        if (!$isStalledState) {
            return false;
        }

        $maxDate = ['date'=> TimeDate::getInstance()->nowDb()];

        $modifiedDate = ['date' => $summaryBean->date_modified, 'modify' => $maxExceededDate];

        $isStalled = self::dateCompare($maxDate, $modifiedDate);

        return $isStalled;
    }

     /**
     * Get error message from error
     *
     * @param $error
     *
     * @return string
     */
    public static function getErrorMessageFromError($error)
    {
        if (!($error instanceof \Throwable)) {
            return '';
        }

        $response = method_exists($error, 'getResponse') ? $error->getResponse() : null;

        $errorMessage = 'An unknown error occurred.';

        if ($response) {
            try {
                $error = $response->getBody()->getContents();
                $message = json_decode($error, true);
                if ((json_last_error() === JSON_ERROR_NONE) && isset($message['errorMessage'])) {
                    $errorMessage =  $message['errorMessage'];
                }
            } catch (\Throwable $err) {
                $errorMessage = $err->getMessage();
            }
        } else {
            $errorMessage = $error->getMessage();
        }

        $errorMessage = is_string($errorMessage) ? $errorMessage : 'An unknown error occurred.';

        return $errorMessage;
    }

    /**
     * Cleanup old success summary
     *
     * @param string $parentId
     * @param string $parentModule
     * @param string $currentRecordId
     * @param string $language
     *
     * @return void
     */
    public static function cleanupOldSuccessSummary($parentId, $parentModule, $currentRecordId, $language)
    {
        try {
            $bean = BeanFactory::newBean(GAIConstants::GAI_MODULE_NAME);
            $query = new SugarQuery();

            $query->select('id');
            $query->from($bean);
            $query->where()->equals('parent_id', $parentId);
            $query->where()->equals('parent_module', $parentModule);
            $query->where()->equals('status', GAIConstants::SUMZ_STATUS_COMPLETED);
            $query->where()->equals('deleted', 0);
            $query->where()->equals('language', $language);
            $query->limit(10);
            $query->orderBy('date_modified', 'ASC');

            $result = $query->execute();

            if (!$result || safeCount($result) < 1) {
                return;
            }

            foreach ($result as $row) {
                if ($row['id'] === $currentRecordId) {
                    continue; // Skip delete if the ID matches the current record
                }
                $bean->mark_deleted($row['id']);
            }
        } catch (\Exception $e) {
            $GLOBALS['log']->error('GAI: Error while cleaning up old success summary: ' . $e->getMessage());
        }
    }

    /**
     * Get the module icons and colors for needed modules.
     *
     * @return array An associative array containing module icons and colors.
     */
    private static function getModulesIconAndColor():array
    {
        $module = ["Calls", 'Meetings', 'Emails', 'Contacts'];

        $defaultModuleIcons = [
            "Calls" => "sicon-phone-lg",
            "Meetings" => "sicon-meetings-lg",
            "Emails" => "sicon-email-lg",
            "Contacts" => "sicon-contact-lg",
        ];
        $defaultModuleColors = [
            "Calls" => "label-module-color-pacific",
            "Meetings" => "label-module-color-teal",
            "Emails" => "label-module-color-ocean",
            "Contacts" => "label-module-color-pink",
        ];

        $meta = new \MetaDataManager();
        $moduleMeta = [];
        $customModuleIcons = [];
        $customModuleColors = [];

        foreach ($module as $module) {
            $moduleMeta = $meta->getModuleData($module);
            $customModuleIcons[$module] = $moduleMeta['icon'] ?? $defaultModuleIcons[$module];
            $customModuleColors[$module] = 'label-module-color-' . $moduleMeta['color'] ?? $defaultModuleColors[$module];
        }

        return [
            "moduleIcons" => $customModuleIcons,
            "moduleColors" => $customModuleColors,
        ];
    }

    /**
     * Prepare additional data
     *
     * @param string $scope engaged_contacts or needed_followup
     * @param array $data The data to prepare, expected to be an associative array with module names as keys.
     * @return string|false data
     */
    public static function prepareAdditionalData($scope, $data): string
    {
        $moduleData = self::getModulesIconAndColor();
        $moduleColors = $moduleData["moduleColors"];
        $moduleIcons = $moduleData["moduleIcons"];

        $firstKey = array_key_first($data);

        if (is_array($data[$firstKey]) && safeCount($data[$firstKey]) === 1 && is_string($data[$firstKey][0])) {
            return json_encode([$firstKey => $data[$firstKey][0]]);
        }

        $result = [$firstKey => []];

        if ($scope === "engaged_contacts") {
            $moduleNameKey = "objectType";
            $nameKey = "fullName";
        } else {
            $moduleNameKey = "moduleName";
            $nameKey = "name";
        }

        foreach ($data[$firstKey] as $item) {
            $moduleName = $item[$moduleNameKey];
            $objectId = $item["objectId"];

            $newItem = [
                "moduleName" => $moduleName,
                "objectId" => $objectId,
                "name" => $item[$nameKey],
                "icon" => $moduleIcons[$moduleName],
                "iconColor" => $moduleColors[$moduleName],
                "link" => "#$moduleName/$objectId",
            ];

            if ($scope === "engaged_contacts") {
                if (!array_key_exists('lastActivity', $item) || empty($item['lastActivity'])) {
                    continue;
                }
                if (array_key_exists("title", $item) && $item["title"] !== "not_found_in_file") {
                    $newItem["title"] = $item["title"];
                }
            } else {
                $newItem["followUp"] = $item["followUp"];
            }
            $result[$firstKey][] = $newItem;
        }

        if (safeCount($result[$firstKey]) === 0) {
            return json_encode([$firstKey => "None"]);
        }
        return json_encode($result);
    }

    /**
     * Fetches the connector properties from the source config.
     * @param string $sourceId
     * @return array The connector properties.
     */
    public static function getConnectorProperties(string $sourceId): array
    {
        $source = \SourceFactory::getSource($sourceId);
        $source->loadConfig();
        $properties = $source->getConfig()['properties'] ?? [];

        return $properties;
    }
}
