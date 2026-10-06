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
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Identifier;

/**
 * Check if the given actionId exists
 *
 * @param string $actionId
 *
 * @return bool
 *
 * @throws Exception
 */
function actionIdExists(string $actionId): bool
{
    global $db;

    $qb = $db->getConnection()->createQueryBuilder();
    $qb->select('id')
        ->from('acl_actions')
        ->where($qb->expr()->eq('id', $qb->expr()->literal($actionId)));
    $stmt = $qb->executeQuery();
    $result = $stmt->fetchAssociative();
    return (is_countable($result) && count($result) > 0);
}

/**
 * This function will get the right action_id, by simply searching after name, category and acltype
 * This logic is used for the cases when the customizations are exported to a different instance (not a copy)
 * and we need to get the according action id for this current instance
 *
 * @param array $roleActionOverride
 * @param array $aclActions
 *
 * @return string
 *
 * @throws Exception
 */
function getRightActionId(array $roleActionOverride, array $aclActions): string
{
    global $db;

    $actionId = '';

    // Get the exported action_id
    foreach ($aclActions as $action) {
        if ($action['id'] === $roleActionOverride['action_id']) {
            // When found it, build action params (based on this paramas we're gonna get the coresponding acl_action)
            $qb = $db->getConnection()->createQueryBuilder();
            $qb->select('id')
                ->from('acl_actions')
                ->where($qb->expr()->eq('name', $qb->expr()->literal($action['name'])))
                ->andWhere($qb->expr()->eq('category', $qb->expr()->literal($action['category'])))
                ->andWhere($qb->expr()->eq('acltype', $qb->expr()->literal($action['acltype'])));
            $stmt = $qb->executeQuery();
            $result = $stmt->fetchAssociative();

            if ($result && is_string($result['id'])) {
                $actionId = $result['id'];
            }
        }
    }

    return $actionId; // This action_id will be used for the given acl_roles_action
}

/**
 * Insert a row into a table if it doesn't exist, otherwise update it
 * @param string $tableName
 * @param array $row
 * @param ?bool $rowExists True, if the row exists in the db, null if a look up is required to determine it
 */
function upsertRow(string $tableName, array $row, ?bool $rowExists = null): void
{
    if (empty($row['id'])) {
        $GLOBALS['log']->fatal('[PB] - upsertRow: $row[\'id\'] is empty');
        return;
    }
    $rowId = $row['id'];

    try {
        global $db;

        if ($rowExists === null) {
            $qb = $db->getConnection()->createQueryBuilder();
            $qb->select('id')
                ->from($tableName)
                ->where($qb->expr()->eq('id', $qb->createPositionalParameter($rowId)));
            $stmt = $qb->executeQuery();
            $rowExists = !empty($stmt->fetchAssociative());
        }
    
        if ($rowExists) {
            $qb = $db->getConnection()->createQueryBuilder();
            $qb->update($tableName);
            foreach ($row as $key => $value) {
                if ($key === 'id') {
                    continue;
                }
                $qb->set($key, $qb->createPositionalParameter($value));
            }
            $qb->where($qb->expr()->eq('id', $qb->createPositionalParameter($rowId)));
            $qb->executeQuery();
        } else {
            $qb = $db->getConnection()->createQueryBuilder();
            foreach ($row as $key => $value) {
                $row[$key] = $qb->createPositionalParameter($value);
            }
            $qb->insert($tableName)->values($row)->executeQuery();
        }
    } catch (Exception $e) {
        $GLOBALS['log']->fatal('[PB] - upsertRow - ERROR MESSAGE: ' . $e->getMessage());
    }
}

/**
 * Get info about whether rows exist in the db
 * @param string $tableName
 * @param array $rowIds
 * @return array<id, bool> Info about existing rows
 */
function getExistingRows(string $tableName, array $rowIds): array
{
    $rowExists = [];

    global $db;
    $qb = $db->getConnection()->createQueryBuilder();
    $idsEscaped = [];
    foreach ($rowIds as $rowId) {
        $idsEscaped[] = $qb->expr()->literal($rowId);
    }
    $qb->select('id')
        ->from($tableName)
        ->where($qb->expr()->in('id', $idsEscaped));
    $stmt = $qb->executeQuery();
    while ($row = $stmt->fetchAssociative()) {
        $rowExists[$row['id']] = true;
    }

    return $rowExists;
}

/**
 * Insert dataset rows into a table if don't exist, otherwise update them
 * @param string $tableName
 * @param array $dataset
 * @param array<id, bool> $existingRows Info about existing rows
 * Inserts are being performed in bulk, define $sugar_config['pb_bulk_insert’] = false for single rows instead.
 */
function upsertBulk(string $tableName, array $dataset, array $existingRows): void
{
    $insertRows = [];
    foreach ($dataset as $row) {
        if (!empty($existingRows[$row['id']])) {
            upsertRow($tableName, $row, true);
        } else {
            if ($GLOBALS['sugar_config']['pb_bulk_insert'] ?? true) {
                $insertRows[] = $row;
            } else {
                upsertRow($tableName, $row, false);
            }
        }
    }

    insertBulk($tableName, $insertRows);
}

/**
 * Insert dataset rows into a table by chunks of multi-row inserts
 * @param string $tableName
 * @param array $dataset
 * The chunk size depends on the maximum number of positional placeholders ( ? ) in a single prepared statement,
 * define $sugar_config['pb_max_placeholders’] = int for a specific value.
 */
function insertBulk(string $tableName, array $dataset): void
{
    if (empty($dataset)) {
        return;
    }

    try {
        global $db;
        $connection = $db->getConnection();
        $platform = $connection->getDatabasePlatform();
        $tableSql = (new Identifier($tableName))->getQuotedName($platform);
        $datasetColumns = array_keys($dataset[0]);
        $columns = [];
        foreach ($datasetColumns as $column) {
            $columns[] = (new Identifier($column))->getQuotedName($platform);
        };
        $columnsSql = '(' . implode(', ', $columns) . ')';
        $placeholders = '(' . implode(', ', array_fill(0, count($datasetColumns), '?')) . ')'; // (?, ?)

        // define $chunkSize - the number of rows, being processed in one chunk insert
        $maxPlaceholders = $GLOBALS['sugar_config']['pb_max_placeholders'] ??
            // although 2099, 32766 or 65535 should work, reduce it a bit
            ['mssql' => 2000, 'ibm_db2' => 32666][$db->dbType] ?? 65435;
        $chunkSize = min(
            intdiv($maxPlaceholders, count($datasetColumns)),
            $GLOBALS['sugar_config']['pb_max_chunk_size'] ?? 1000
        );

        foreach (array_chunk($dataset, $chunkSize) as $chunk) {
            // prepare bulk insert query
            $chunkPlaceholders = implode(', ', array_fill(0, count($chunk), $placeholders)); // (?, ?), (?, ?)
            $sql = "INSERT INTO $tableSql $columnsSql VALUES $chunkPlaceholders;";

            // flatten chunk values
            $params = [];
            foreach ($chunk as $row) {
                $params = array_merge($params, array_values($row));
            }

            $connection->executeUpdate($sql, $params);
        }
    } catch (Exception $e) {
        $GLOBALS['log']->fatal('[PB] - insertBulk - ERROR MESSAGE: ' . $e->getMessage());
    }
}
