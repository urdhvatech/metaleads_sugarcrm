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

namespace Sugarcrm\Sugarcrm\CloudDrive\Paths\Model\Types;

use CloudDrivePath;
use Doctrine\DBAL\Exception;
use Sugarcrm\Sugarcrm\CloudDrive\Paths\Model\Types\CloudDrivePathBase;
use SugarException;
use SugarQueryException;

class CloudDrivePathOnedrive extends CloudDrivePathBase
{
    /**
     * Get the onedrive path
     *
     * @param array $options
     * @return array
     */
    public function getDrivePath(array $options): array
    {
        $result = ['root' => 'root'];
        $paths = [];
        $record = $this->getRecord($options);
        $path = $this->findRoot($options['type']);
        $searchFolder = null;

        if ($options['layoutName'] === 'record') {
            $paths = $this->getPaths($options);
        }

        if (safeCount($paths) > 0) {
            $path = $paths[0];
        }

        if (is_array($path)) {
            $result = [
                'root' => $path['folder_id'] ?? 'root',
                'driveId' => $path['drive_id'] ?? null,
                'siteId' => $path['site_id'] ?? null,
                'path' => $path['path'] ?? null,
                'isShared' => $path['is_shared'] ?? null,
                'variablePath' => $path['variable_path'] ?? null,
                'type' => $path['type'] ?? null,
            ];
        }

        if ($path instanceof CloudDrivePath) {
            $result = [
                'root' => $path->folder_id ?? 'root',
                'driveId' => $path->drive_id ?? null,
                'siteId' => $path->site_id ?? null,
                'path' => $path->path ?? null,
                'isShared' => $path->is_shared ?? null,
                'variablePath' => $path->variable_path ?? null,
                'type' => $path->type ?? null,
            ];
        }

        $variablePath = $result['variablePath'] ?? null;
        if ($variablePath) {
            // we only search inside folders, not drives or sites, so it needs to have a folder_id
            $folderId = $result['root'] ?? null;
            if (!$folderId ||
                ($folderId === $result['driveId']  && $options['type'] !== 'sharepoint') ||
                $folderId === $result['siteId']
                ) {
                throw new SugarException(translate('LBL_CLOUD_DRIVE_PATHS_MISSING_FOLDER_ID', 'Administration'));
            }

            $searchFolder = $this->parsePath($variablePath, $record);
            // if searchFolder is Array we need to get the name result
            if (is_array($searchFolder)) {
                $searchFolder = $searchFolder[0];
                if (isset($searchFolder['name'])) {
                    $searchFolder = $searchFolder['name'];
                }
            }

            // now that we have a searchFolder we can get the path
            $data = $this->listFoldersUtils([
                'folderName' => $searchFolder,
                'folderParent' => $folderId,
                'sharedWithMe' => $result['isShared'],
                'parentId' => $folderId,
                'type' => $result['type'],
                'driveId' => $result['driveId'],
                'siteId' => $result['siteId'],
            ]);

            if (isset($data['success']) && !$data['success']) {
                return [
                    'success' => false,
                    'message' => $data['message'],
                ];
            }

            $path = $result['path'];

            try {
                $path = json_decode($path, true);
            } catch (SugarException $e) {
                throw new SugarException('LBL_CLOUD_DRIVE_PATHS_INVALID_PATH');
            }

            if (is_array($data['files']) && safeCount($data['files']) > 0) {
                $driveItem = $data['files'][0];
                $result['folderId'] = $driveItem->id;
                $result['root'] = $driveItem->id;
                $result['parentId'] = $folderId;
                $path [] = [
                    'name' => $searchFolder,
                    'folderId' => $driveItem->id,
                    'driveId' => $result['driveId'],
                ];

                $result['path'] = json_encode($path);
            } else {
                $path [] = [
                    'name' => $searchFolder,
                    'parentId' => $folderId,
                    'type' => $result['type'],
                    'driveId' => $result['driveId'],
                ];
                $result['path'] = json_encode($path);
                $result['parentId'] = $folderId;
                $result['folderId'] = null;
                $result['root'] = null;
                $result['pathCreateIndex'] = safeCount($path) - 1;
            }
        }
        return $result;
    }
}
