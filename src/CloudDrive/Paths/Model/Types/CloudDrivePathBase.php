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

use Google\Exception;
use Sugarcrm\Sugarcrm\CloudDrive\Paths\CloudDrivePathUtils;

class CloudDrivePathBase
{
    use CloudDrivePathUtils;

    /**
     * Get the base path
     *
     * @param array|object $pathDetails
     * @param array $path
     * @param array $options
     * @return array
     */
    public function getBasePath($pathDetails, array $path, array $options): array
    {
        if ($pathDetails['folder_id'] && empty($pathDetails['variable_path'])) {
            $file = $this->getFileUtils([
                'fileId' => $pathDetails['folder_id'],
                'driveId' => $pathDetails['drive_id'],
                'type' => $options['type'],
                'hasSharedDriveAccess' => $options['hasSharedDriveAccess'] ?? false,
            ]);

            if (is_array($file) && !$file['success']) {
                return $file;
            }

            if (property_exists($file, 'parents')) {
                return [
                    'root' => $pathDetails['folder_id'],
                    'path' => $pathDetails['path'],
                    'parentId' => $file->parents[0],
                    'isShared' => $pathDetails['is_shared'],
                    'driveId' => $file->driveId,
                    'isSharedDrive' => $pathDetails['is_shared_drive'] ?? false,
                    'hasSharedDrive' => $options['hasSharedDriveAccess'] ?? false,
                ];
            }
        }

        if ($path[0]['name'] === 'My files' || $path[0]['name'] === 'Shared') {
            $path[0]['folderId'] = 'root';
        }

        if (safeCount((array)$path) > 1) {
            $approximatePath = $this->approximatePath($path, $pathDetails, $options['type']);

            if (is_null($approximatePath)) {
                $approximatePath = [
                    'root' => false,
                    'path' => $path,
                    'isShared' => $pathDetails['is_shared'],
                    'driveId' => $pathDetails['drive_id'],
                    'parentId' => 'root',
                    'pathCreateIndex' => 1,
                ];
            }
            return $approximatePath;
        } else {
            $root = $path[0]['folderId'] ?? false;
            return [
                'root' => $root,
                'path' => $path,
                'isShared' => $pathDetails['is_shared'],
                'driveId' => $pathDetails['drive_id'],
                'hasSharedDrive' => $options['hasSharedDriveAccess'] ?? false,
            ];
        }

        return [
            'root' => false,
            'path' => $path,
            'isShared' => $pathDetails->is_shared,
        ];
    }

    /**
     * Returns the root path
     *
     * @param object $rootPath
     * @param array $options
     * @return array
     * @throws Exception
     */
    protected function getRootPath(?object $rootPath, array $options): array
    {
        $defaultPath = ['root' => 'root'];
        if (empty($rootPath) && isset($options['hasSharedDriveAccess']) && $options['hasSharedDriveAccess']) {
            //this is the default case when no path is selected but has acces to shared drives
                $defaultPath['hasSharedDrive'] = $options['hasSharedDriveAccess'];
                return $defaultPath;
        } elseif ($rootPath && $rootPath->folder_id) {
            $file = $this->getFileUtils([
                'fileId' => $rootPath->folder_id,
                'type' => $options['type'],
                'driveId' => $rootPath->drive_id,
            ]);

            return [
                'root' => $rootPath->folder_id,
                'driveId' => $file->driveId,
                'path' => $rootPath->path,
                'parentId' => is_array($file->parents) && safeCount($file->parents) > 0 ? $file->parents[0] : '',
                'isShared' => $rootPath->is_shared,
                'isSharedDrive' => $rootPath->is_shared_drive,
                'hasSharedDrive' => $options['hasSharedDriveAccess'] ?? false,
            ];
        }

        return $defaultPath;
    }
}
