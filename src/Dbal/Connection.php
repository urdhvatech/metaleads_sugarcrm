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

namespace Sugarcrm\Sugarcrm\Dbal;

use Doctrine\DBAL\Cache\QueryCacheProfile;
use Doctrine\DBAL\Connection as BaseConnection;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Result;
use DBManagerFactory;
use LoggerManager;
use Sugarcrm\Sugarcrm\Dbal\Query\QueryBuilder;
use Sugarcrm\Sugarcrm\Dbal\ReadReplica\WriteTracker;

/**
 * {@inheritDoc}
 */
class Connection extends BaseConnection
{
    /**
     * @var int
     */
    private $retryCount = 0;

    /**
     * @var int
     */
    private $maxRetryCount = 1;

    /**
     * {@inheritDoc}
     *
     * @return \Sugarcrm\Sugarcrm\Dbal\Query\QueryBuilder
     */
    public function createQueryBuilder()
    {
        return new QueryBuilder($this);
    }

    /**
     * {@inheritDoc}
     */
    public function executeQuery($query, array $params = [], $types = [], ?QueryCacheProfile $qcp = null): Result
    {
        try {
            $result = parent::executeQuery($query, $params, $types, $qcp);
            $this->retryCount = 0;
            return $result;
        } catch (DBALException\ConnectionLost $e) {
            if ($this->retryCount < $this->maxRetryCount) {
                $this->retryCount++;
                $this->reconnect();
                return $this->executeQuery($query, $params, $types, $qcp);
            }
            $this->logException($e);
            throw $e;
        } catch (DBALException $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function executeUpdate($query, array $params = [], array $types = []): int
    {
        return $this->executeStatement($query, $params, $types);
    }

    /**
     * {@inheritDoc}
     */
    public function executeStatement($query, array $params = [], array $types = []): int
    {
        // Track write operations (INSERT/UPDATE/DELETE/etc)
        WriteTracker::trackWrite();

        try {
            $result = parent::executeStatement($query, $params, $types);
            $this->retryCount = 0;
            return $result;
        } catch (DBALException\ConnectionLost $e) {
            if ($this->retryCount < $this->maxRetryCount) {
                $this->retryCount++;
                $this->reconnect();
                return $this->executeStatement($query, $params, $types);
            }
            $this->logException($e);
            throw $e;
        } catch (DBALException\UniqueConstraintViolationException $e) {
            throw $e;
        } catch (DBALException $e) {
            $this->logException($e);
            throw $e;
        }
    }

    /**
     * Logs DBAL exception
     *
     * @param DBALException $e Exception
     */
    protected function logException(DBALException $e)
    {
        LoggerManager::getLogger()->fatal($this->formatExceptionMessage($e));
    }

    /**
     * @param DBALException $e
     * @return string
     */
    protected function formatExceptionMessage(DBALException $e): string
    {
        $message = $e->getMessage();
        if ($e instanceof DBALException\DriverException && $e->getQuery() !== null) {
            $message .= '; Query: ' . $e->getQuery()->getSQL();
            $params = $e->getQuery()->getParams();
            if (safeCount($params) > 0) {
                $message .= '; Params: ' . var_export($params, true);
            }
        }
        return $message;
    }

    /**
     * Reconnect to the database
     */
    private function reconnect(): void
    {
        foreach (DBManagerFactory::$instances as $instance) {
            if ($instance->getConnection() === $this) {
                // Store the connection options before disconnecting
                $connectOptions = $instance->connectOptions;

                // Disconnect will close the connection and set conn to null in DBManager
                $instance->disconnect();

                // Connect creates a new mysqli connection in DBManager
                // Pass the stored connection options to preserve instance configuration
                $instance->connect($connectOptions);

                // Now we need to update this Doctrine Connection object's internal
                // driver connection with the new mysqli object from DBManager
                // We do this by calling the driver to create a new connection wrapper
                $driver = $this->getDriver();
                $params = $this->getParams();
                $params['connection'] = $instance->getDatabase();
                $this->_conn = $driver->connect($params);

                return;
            }
        }
    }
}
