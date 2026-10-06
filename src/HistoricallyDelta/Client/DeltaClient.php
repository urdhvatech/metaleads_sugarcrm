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

namespace Sugarcrm\Sugarcrm\HistoricallyDelta\Client;

use Administration;
use BeanFactory;
use TimeDate;
use DateTime;
use GuzzleHttp\Utils;
use InvalidArgumentException;
use Sugarcrm\Sugarcrm\HistoricallyDelta\Client\Client;
use Sugarcrm\Sugarcrm\HistoricallyDelta\Logger;
use LoggerManager;
use SugarApiException;
use Sugarcrm\Sugarcrm\Security\HttpClient\RequestException;
use RuntimeException;
use Sugarcrm\Sugarcrm\HistoricallyDelta\Helper;
use Psr\Log\InvalidArgumentException as LogInvalidArgumentException;
use Random\RandomException;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;

final class DeltaClient
{
    /**
     * @var Logger
     */
    protected $logger;
    private string $module;
    private array $recordIds;
    private array $fields;
    private string $deltaDate;
    private int $maxRetries;
    private float $requestTimeout;

    private Client $client;

    /**
     * DeltaClient constructor.
     *
     * @param string $module
     * @param array $recordIds
     * @param array $fields
     * @param string $deltaDate
     * @return void
     */
    public function __construct(string $module, array $recordIds, array $fields, string $deltaDate)
    {
        $this->module = $module;
        $this->recordIds = $recordIds;
        $this->fields = $fields;
        $this->deltaDate = $deltaDate;
        $this->maxRetries = 2;
        $this->requestTimeout = 300;

        $this->client = new Client($this->requestTimeout, $this->maxRetries);
        $this->logger = new Logger(LoggerManager::getLogger());
    }

    /**
     * Returns a past date based on the provided interval key using the user's timezone.
     *
     * Supported keys:
     * - '7_days'      => Now minus 7 days
     * - '14_days'     => Now minus 14 days
     * - '30_days'     => Now minus 30 days
     *
     * The returned date is in ISO 8601 format (e.g., 2025-04-24T13:00:00+00:00).
     *
     * @param string $intervalKey One of: '7_days', '14_days', '30_days'
     * @return string ISO 8601 formatted date string
     * @throws InvalidArgumentException If an invalid key is provided
     */
    private function getPastDateISO8601(string $intervalKey): string
    {
        $timedate = TimeDate::getInstance();
        $userDate = $timedate->getNow(true); // true = user timezone

        $interval = Helper::getInterval($intervalKey);

        $userDate->sub($interval);

        return $userDate->format(DateTime::ATOM); // ISO 8601
    }

    /**
     * Retrieve the delta of the opportunities.
     *
     * @return array
     *
     * @throws InvalidArgumentException
     * @throws SugarApiException
     * @throws RequestException
     * @throws RuntimeException
     * @throws LogInvalidArgumentException
     */
    public function retrieveDelta(): array
    {
        $values = $this->getHistoricalValues($this->module, $this->recordIds, $this->fields, $this->deltaDate);

        return $values;
    }

    /**
     * Retrieve the historical values for the given module, record IDs, fields, and delta date.
     *
     * @param string $module
     * @param array $recordsId
     * @param array $fields
     * @param string $deltaDate
     *
     * @return array
     *
     * @throws InvalidArgumentException
     * @throws SugarApiException
     * @throws RequestException
     * @throws RandomException
     * @throws RuntimeException
     * @throws LogInvalidArgumentException
     */
    protected function getHistoricalValues(string $module, array $recordsId, array $fields, string $deltaDate): array
    {
        $data = [];
        $payload = $this->buildPayload($module, $recordsId, $fields, $deltaDate);

        $url = $this->getUrl();

        $response = $this->client->retrieveDelta($url, $payload);

        try {
            $data = $this->parseResponse($response);
        } catch (\InvalidArgumentException $e) {
            $this->logger->alert('Invalid Discovery response', ['data' => $data]);
        }

        return $data;
    }

    /**
     * Parse the response from the API.
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     * @return array
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    private function parseResponse(\Psr\Http\Message\ResponseInterface $response): array
    {
        $statusCode = $response->getStatusCode();
        $data = $response->getBody()->getContents();

        switch ($statusCode) {
            case 200:
            case 201:
                $data = Utils::jsonDecode($data, true);
                if (!is_array($data)) {
                    throw new RuntimeException('Invalid response format: expected JSON array');
                }
                return $data;

            case 401:
                throw new RuntimeException('Unauthorized: Invalid or expired credentials (401)');

            case 403:
                throw new RuntimeException('Forbidden: Access denied (403)');

            case 404:
                throw new RuntimeException('Not Found: Invalid endpoint or resource (404)');

            case 500:
                throw new RuntimeException('Server Error: Internal server error (500)');

            default:
                throw new RuntimeException("Unexpected response status: $statusCode");
        }
    }

    /**
     *
     * @param string $module
     * @param array $recordsId
     * @param array $fields
     * @param string $deltaDate
     * @return array
     */
    private function buildPayload(string $module, array $recordsId, array $fields, string $deltaDate): array
    {
        $payload = [
            'module' => $module,
            'recordIds' => $recordsId,
            'fields' => $fields,
            'deltaDate' => $this->getPastDateISO8601($deltaDate),
        ];

        return $payload;
    }

    /**
     * Get the URL for the Delta API.
     */
    private function getUrl(): string
    {
        $administration = new Administration();
        $administration->retrieveSettings('delta', false);

        $deltaUrl = '';

        if (!empty($administration->settings['delta_url'])) {
            $deltaUrl = $administration->settings['delta_url'];
        }

        if (empty($deltaUrl)) {
            $deltaUrl = $this->client->getDeltaUrl();

            $admin = BeanFactory::newBean('Administration');
            $admin->saveSetting('delta', 'url', $deltaUrl, 'base');
        }

        if (empty($deltaUrl)) {
            throw new RuntimeException('Historically Delta: unable to retrieve URL');
        }

        return $deltaUrl;
    }

    /**
     * Get the module to retrieve.
     *
     * @return string
     */
    public function getModule(): string
    {
        return $this->module;
    }

    /**
     * Set the module to retrieve.
     *
     * @param string $module
     * @return void
     */
    public function setModule(string $module): void
    {
        $this->module = $module;
    }

    /**
     * Get the record IDs to retrieve.
     *
     * @return array
     */
    public function getRecordIds(): array
    {
        return $this->recordIds;
    }

    /**
     * Set the record IDs to retrieve.
     *
     * @param array $recordIds
     * @return void
     */
    public function setRecordIds(array $recordIds): void
    {
        $this->recordIds = $recordIds;
    }

    /**
     * Get the fields to retrieve.
     *
     * @return array
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * Set the fields to retrieve.
     *
     * @param array $fields
     * @return void
     */
    public function setFields(array $fields): void
    {
        $this->fields = $fields;
    }

    /**
     * Get the delta date.
     *
     * @return string
     */
    public function getDeltaDate(): string
    {
        return $this->deltaDate;
    }

    /**
     * Set the delta date.
     *
     * @param string $deltaDate
     * @return void
     */
    public function setDeltaDate(string $deltaDate): void
    {
        $this->deltaDate = $deltaDate;
    }

    /**
     * Get the maximum number of retries for the request.
     *
     * @return int
     */
    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    /**
     * Set the maximum number of retries for the request.
     *
     * @param int $maxRetries
     * @return void
     */
    public function setMaxRetries(int $maxRetries): void
    {
        $this->maxRetries = $maxRetries;
    }

    /**
     * Get the request timeout in seconds.
     *
     * @return float
     */
    public function getRequestTimeout(): float
    {
        return $this->requestTimeout;
    }

    /**
     * Set the request timeout in seconds.
     *
     * @param float $requestTimeout
     * @return void
     */
    public function setRequestTimeout(float $requestTimeout): void
    {
        $this->requestTimeout = $requestTimeout;
    }
}
