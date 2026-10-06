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

namespace Sugarcrm\Sugarcrm\InboundEmail\Polyfill;

class ImapPolyfillManager
{
    private static ?ImapPolyfillManager $instance = null;
    private array $connections = [];
    private int $nextResourceId = 1;
    private array $errors = [];
    private array $alerts = [];
    private array $timeouts = [
        1 => 30, // IMAP_OPENTIMEOUT
        2 => 30, // IMAP_READTIMEOUT
        3 => 30, // IMAP_WRITETIMEOUT
        4 => 30, // IMAP_CLOSETIMEOUT
    ];

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function registerConnection(ImapConnection $connection): ImapResource
    {
        $resourceId = $this->nextResourceId++;
        $this->connections[$resourceId] = $connection;
        return new ImapResource($resourceId, $connection);
    }

    public function getConnection(int $resourceId): ?ImapConnection
    {
        return $this->connections[$resourceId] ?? null;
    }

    /**
     * Extract connection from ImapResource.
     */
    public function getConnectionFromResource(ImapResource $resource): ?ImapConnection
    {
        return $resource->getConnection();
    }

    public function unregisterConnection(int $resourceId): void
    {
        if (isset($this->connections[$resourceId])) {
            $this->connections[$resourceId]->disconnect();
            unset($this->connections[$resourceId]);
        }
    }

    public function addError(string $error): void
    {
        $this->errors[] = $error;
    }

    public function getLastError(): string|false
    {
        $error = end($this->errors);
        return $error !== false ? $error : false;
    }

    public function getErrors(): array|false
    {
        $errors = $this->errors;
        $this->errors = [];
        return !empty($errors) ? $errors : false;
    }

    public function clearErrors(): void
    {
        $this->errors = [];
    }

    public function addAlert(string $alert): void
    {
        $this->alerts[] = $alert;
    }

    public function getAlerts(): array|false
    {
        $alerts = $this->alerts;
        $this->alerts = [];
        return !empty($alerts) ? $alerts : false;
    }

    public function clearAlerts(): void
    {
        $this->alerts = [];
    }

    public function setTimeout(int $timeout_type, int $timeout): void
    {
        $this->timeouts[$timeout_type] = $timeout;
    }

    public function getTimeout(int $timeout_type): int
    {
        return $this->timeouts[$timeout_type] ?? 30;
    }
}
