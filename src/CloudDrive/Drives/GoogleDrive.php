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

namespace Sugarcrm\Sugarcrm\CloudDrive\Drives;

use ExtAPIGoogle;
use Google\Exception;
use Google\Service\Drive\FileList;
use Google\Service\Drive\DriveFile;
use Google_Exception;
use InvalidArgumentException;
use Sugarcrm\Sugarcrm\CloudDrive\Drive;
use Google\Service\Drive as GDrive;
use Sugarcrm\Sugarcrm\CloudDrive\Constants\DriveType;
use Sugarcrm\Sugarcrm\CloudDrive\Model\DriveItemMapper;
use SugarException;

class GoogleDrive extends Drive
{
    public $usableMimeTypes = [
        'application/vnd.google-apps.spreadsheet' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.google-apps.document' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.google-apps.presentation' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];

    public $fileExtensions = [
        'application/vnd.google-apps.spreadsheet' => '.xlsx',
        'application/vnd.google-apps.document' => '.docx',
        'application/vnd.google-apps.presentation' => '.pptx',
    ];

    /**
     * List folders in a drive
     *
     * @param array $options
     * @return Array|bool
     */
    public function listFolders(array $options)
    {
        $client = $this->getClient();

        if (is_array($client) && !$client['success']) {
            return $client;
        }

        $orderBy = $options['orderBy'] ?? 'modifiedTime desc';
        $folderName = $options['folderName'];
        $folderParent = $options['folderParent'] ?? null;
        $parentId = $options['parentId'] ?? null;
        $sharedWithMe = $options['sharedWithMe'];
        $sharedDrive = $options['sharedDrives'] ?? false;

        if ($folderParent) {
            if (!$parentId) {
                $parents = $this->listFolders([
                    'folderName' => $folderParent,
                    'orderBy' => $orderBy,
                    'sharedWithMe' => $sharedWithMe,
                    'sharedDrives' => $sharedDrive,
                ]);
            } else {
                // recreating the parents object
                $parents = ['files' => [['id' => $parentId]]];
            }

            if (is_array($parents) && safeCount($parents['files']) > 0) {
                $parent = $parents['files'][0];
                $parentId = is_array($parent) ? ($parent['id'] ?? null) : ($parent->id ?? null);
                $folders = $this->listFolders([
                    'folderName' => $folderName,
                    'parentId' => $parentId,
                    'orderBy' => $orderBy,
                    'sharedWithMe' => $sharedWithMe,
                    'sharedDrives' => $sharedDrive,
                ]);
                $folders['parentId'] = $parentId;
                return $folders;
            } else {
                return false;
            }
        } else {
            return $this->retrieveFolders($folderName, $orderBy, $parentId, $sharedWithMe, $folderParent, $sharedDrive);
        }

        return false;
    }

    /**
     * Create a folder on the drive
     *
     * @param array $options
     * @return null|array
     */
    public function createFolder(array $options): ?array
    {
        $folderName = $options['name'];
        $parent = $options['parent'] ?? 'root';

        if (!$folderName) {
            return null;
        }

        if ($options['isSharedDrive'] && $parent === 'root') {
            $folder = $this->createNewSharedDrive($folderName);
        } elseif ($options['isSharedDrive']) {
               $folder = $this->createFolderOnSharedDrive($folderName, $parent);
        } else {
            $googleApi = new ExtAPIGoogle();
            $client = $googleApi->getClient();
            $folder = $googleApi->createFolder($folderName, $parent);
        }

        if (is_array($folder) && !$folder['success']) {
            return $folder;
        }

        return [
            'id' => $folder->id,
        ];
    }

    /**
     * List files in a folder
     *
     * @param array $options
     * @return void
     */
    public function listFiles(array $options)
    {
        global $sugar_config;

        $client = $this->getClient();

        if (is_array($client) && !$client['success']) {
            return $client;
        }

        $folderId = $options['folderId'] ?? 'root';
        $nextPageToken = $options['nextPageToken'] ?? null;
        $sortOptions = $options['sortOptions'] ?? false;
        $sharedWithMe = $options['sharedWithMe'] ?? false;
        $sharedDrive = $options['sharedDrive'] ?? false;

        $q = "'{$folderId}' in parents and trashed = false";

        if ($sharedWithMe) {
            if ($folderId !== 'root') {
                $q = "'{$folderId}' in parents and trashed = false";
            } else {
                $q = 'sharedWithMe and trashed = false';
            }
        }

        if ($sortOptions) {
            $fieldName = $sortOptions['fieldName'];
            $direction = $sortOptions['direction'];

            $orderBy = "{$fieldName} {$direction}";
        } else {
            $orderBy = 'folder,modifiedTime desc,name';
        }

        $googleApi = new ExtAPIGoogle();
        $client = $googleApi->getClient();
        $drive = new GDrive($client);

        if ($sharedDrive && $folderId === 'root') {
            //this is the logic when you have shared drives as root path or you navigate to it
            $options = [
                'pageSize' => $sugar_config['list_max_entries_per_page'],
            ];

            if ($nextPageToken) {
                $options['pageToken'] = $nextPageToken;
            }
            try {
                $resource = $drive->drives->listDrives($options);
                $fs = $resource->getDrives();
                foreach ($fs as $index => $sharedDrive) {
                    $sharedDriveId = $sharedDrive->getId();
                    //get metadata of the shared drive
                    $sharedDriveMeta = $drive->files->get($sharedDriveId, [
                        'supportsAllDrives' => true,
                        'fields' => 'webViewLink',
                    ]);
                    $fs[$index]->webViewLink = $sharedDriveMeta->getWebViewLink();
                }

                if ($sortOptions) {
                    usort($fs, function ($first, $second) use ($fieldName, $direction) {
                        if ($direction === 'asc') {
                            return strcmp($first->$fieldName, $second->$fieldName);
                        } else {
                            return strcmp($second->$fieldName, $first->$fieldName);
                        }
                    });
                }
            } catch (Google_Exception $e) {
                $GLOBALS['log']->fatal($e);
                return [
                    'success' => false,
                    'message' => 'LBL_CHECK_GOOGLE_CONNECTION',
                ];
            }
        } else {
            $options = [
                'corpora' => 'allDrives',
                'includeItemsFromAllDrives' => true,
                'pageSize' => $sugar_config['list_max_entries_per_page'],
                'fields' => '*',
                'orderBy' => $orderBy,
                'q' => $q,
                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true,
            ];

            if ($nextPageToken) {
                $options['pageToken'] = $nextPageToken;
            }

            try {
                $files = $drive->files->listFiles($options);
                $fs = $files->getFiles();
            } catch (Google_Exception $e) {
                $GLOBALS['log']->fatal($e);
                return [
                    'success' => false,
                    'message' => 'LBL_CHECK_GOOGLE_CONNECTION',
                ];
            }
        }

        $mapper = new DriveItemMapper($fs, DriveType::GOOGLE);
        $mappedData = $mapper->mapToArray();
        $nextPageToken = null;
        if (isset($files) && !empty($files->nextPageToken)) {
            $nextPageToken = $files->nextPageToken;
        } elseif (isset($resource) && !empty($resource["nextPageToken"])) {
            $nextPageToken = $resource["nextPageToken"];
        }

        return [
            'files' => $mappedData,
            'nextPageToken' => $nextPageToken,
        ];
    }

    /**
     *  Downloads a file from the drive
     *
     * @param array $options
     * @return bool|array
     */
    public function downloadFile(array $options)
    {
        $googleApi = new ExtAPIGoogle();

        $fileId = $options['fileId'];
        $fileInfo = $googleApi->retrieveFileInfo($fileId);
        $mimeType = $fileInfo->getMimeType();
        $usableMimeType = $this->usableMimeTypes[$mimeType] ?? '';

        try {
            $contentData = $googleApi->downloadDoc($fileId, $mimeType);
        } catch (Google_Exception $e) {
            $GLOBALS['log']->fatal($e);
            return false;
        }

        return [
            'success' => true,
            'content' => $contentData['message'],
            'usableMimeType' => $usableMimeType,
            'mimeType' => $mimeType,
        ];
    }

    /**
     *  Uploads a file to the drive
     *
     * @param array options
     * @return null|array
     */
    public function uploadFile(array $options): ?array
    {
        $googleApi = new ExtAPIGoogle();

        if (isset($options['data']) && $options['data']) {
            $fileName = $options['fileName'];
            $parentId = $options['parentId'];
            $isSharedDrive = $options['isSharedDrive'] ? json_decode($options['isSharedDrive']) : false;
            $data = $options['data'];
            return $googleApi->uploadFileToFolder($fileName, $parentId, $data, $isSharedDrive);
        }

        $documentBean = $options['documentBean'];
        $pathId = $options['pathId'];
        $parent = $pathId ?? 'root';

        $file = $this->checkFileExists($googleApi, $pathId, $documentBean->name);

        $filePath = $this->getFilePath($documentBean);

        if ($file['exists']) {
            $googleApi->deleteDoc($file['file']['id']);
        }

        return $googleApi->uploadDocToFolder($documentBean, $filePath, $parent);
    }

    /**
     *  Deletes a file from the drive
     *
     * @param array $options
     * @return null|array
     */
    public function deleteFile(array $options): ?array
    {
        $googleApi = new ExtAPIGoogle();
        $client = $googleApi->getClient();

        $fileId = $options['fileId'];

        try {
            $content = $googleApi->deleteDoc($fileId);
        } catch (Google_Exception $e) {
            $GLOBALS['log']->fatal($e);
            return [
                'success' => false,
                'message' => 'LBL_SHARED_DRIVE_ACTION_FAIL_MSG',
            ];
        }
        return $content;
    }

    /**
     * Get file data from drive
     *
     * @param array $options
     * @return mixed
     */
    public function getFile(array $options)
    {
        $fileId = $options['fileId'];

        $googleApi = new ExtAPIGoogle();
        $client = $googleApi->getClient();

        $drive = new GDrive($client);
        $driveOptions = [
            'fileId' => $fileId,
            'supportsAllDrives' => true,
            'fields' => '*',
        ];

        try {
            $file = $drive->files->get($fileId, $driveOptions);
        } catch (Google_Exception $e) {
            $GLOBALS['log']->fatal($e);
            return [
                'hasSharedDrive' => $options['hasSharedDriveAccess'] ?? false,
                'googleException' => true,
            ];
        }
        $mapper = new DriveItemMapper($file, DriveType::GOOGLE);
        $mappedData = $mapper->mapToDriveItem();
        return $mappedData;
    }

    /**
     * calls the google drive api to retrieve folders
     *
     * @param string folderName
     * @param string orderBy
     * @param string parentId
     * @param mixed sharedWithMe
     * @param mixed folderParent
     *
     * @return FileList|bool
     */
    private function retrieveFolders(?string $folderName, string $orderBy, ?string $parentId, $sharedWithMe, $folderParent, $sharedDrive)
    {
        global $sugar_config;
        $includeItemsFromAllDrives = false;
        $supportsAllDrives = false;

        $googleApi = new ExtAPIGoogle();
        $client = $googleApi->getClient();
        $drive = new GDrive($client);

        $folderMimeType = 'application/vnd.google-apps.folder';
        $q = "mimeType = '{$folderMimeType}'";

        if ($sharedWithMe || $sharedDrive) {
            $includeItemsFromAllDrives = true;
            $supportsAllDrives = true;
        }

        if ($parentId) {
             //this is the condition to list all shared drives
            if ($sharedDrive && $parentId === 'root' && !$folderName) {
                try {
                    $pageSize = $sugar_config['list_max_entries_per_page'];
                    $resource = $drive->drives->listDrives([
                        'pageSize' => $pageSize,
                    ]);
                    $sharedDrives = $resource->getDrives();
                    while ($resource->getNextPageToken()) {
                        $resource = $drive->drives->listDrives([
                            'pageSize' => $pageSize,
                            'pageToken' => $resource->getNextPageToken(),
                        ]);
                        $sharedDrives = array_merge($sharedDrives, $resource->getDrives());
                    }
                } catch (Google_Exception $e) {
                    $GLOBALS['log']->fatal($e);
                    return [
                        'success' => false,
                        'message' => 'LBL_CHECK_GOOGLE_CONNECTION',
                    ];
                }

                return ['files' => $sharedDrives];
            }

            if ($sharedWithMe && $parentId === 'root') {
                $q .= ' and sharedWithMe';
            } else {
                $q .= " and '{$parentId}' in parents";
            }
        } else {
            if (!$sharedWithMe && $folderParent === 'root') {
                $q .= " and 'root' in parents";
            }
        }

        if ($folderName) {
            $q .= " and name = '{$folderName}'";
        }

        $q .= ' and trashed = false';

        $options = [
            'pageSize' => $sugar_config['list_max_entries_per_page'],
            'orderBy' => $orderBy,
            'q' => $q,
            'includeItemsFromAllDrives' => $includeItemsFromAllDrives,
            'supportsAllDrives' => $supportsAllDrives,
            'fields' => '*',
        ];

        try {
            $resource = $drive->files->listFiles($options);
            $folders = $resource;
            // set path logic to load all folders
            if (!$folderName && $parentId === 'root') {
                while ($resource->getNextPageToken()) {
                    $options['pageToken'] = $resource->getNextPageToken();
                    $resource = $drive->files->listFiles($options);
                    $folders->files = array_merge($folders->files, $resource->files);
                }
            }
        } catch (Google_Exception $e) {
            $GLOBALS['log']->fatal($e);
            return [
                'success' => false,
                'message' => 'LBL_CHECK_GOOGLE_CONNECTION',
            ];
        }

        $mapper = new DriveItemMapper($folders->getFiles(), DriveType::GOOGLE);
        $mappedData = $mapper->mapToArray();

        $nextPageToken = $folders->nextPageToken;

        return [
            'files' => $mappedData,
            'nextPageToken' => $nextPageToken,
        ];
    }

    /**
     * Check if a file exists on drive
     *
     * @param ExtApiGoogle $googleApi
     * @param string $pathId
     * @return false|array
     * @throws Exception
     */
    private function checkFileExists($googleApi, $pathId, $fileName)
    {
        $file = [
            'exists' => false,
        ];
        $q = "'{$pathId}' in parents and trashed = false and name = '{$fileName}'";

        $client = $googleApi->getClient();
        $drive = new GDrive($client);
        $options = [
            'corpora' => 'allDrives',
            'includeItemsFromAllDrives' => true,
            'fields' => '*',
            'q' => $q,
            'supportsAllDrives' => true,
            'includeItemsFromAllDrives' => true,
        ];

        try {
            $files = $drive->files->listFiles($options);
        } catch (Google_Exception $e) {
            $GLOBALS['log']->fatal($e);
            return false;
        }

        if (safeCount($files) > 0) {
            $file['exists'] = true;
            $file['file'] = $files[0];
        }

        return $file;
    }

    /**
     * Uploads a larget file to the drive
     *
     * @param array $options
     * @return null||array
     */
    public function uploadLargeFile(array $options): ?array
    {
        return $this->uploadFile($options);
    }

    /**
     * Get the file extension of a google file
     *
     * @param array $options
     * @return string
     */
    public function getFileExtension(array $options)
    {
        $type = $options['mimeType'];

        return $this->fileExtensions[$type];
    }

    /**
     * Get the usable mime type of a google file
     *
     * @param array $options
     * @return string
     */
    public function getUsableMimeType(array $options)
    {
        $type = $options['mimeType'];

        return $this->usableMimeTypes[$type];
    }

    /**
     * Get google api client
     */
    public function getClient()
    {
        $googleApi = new ExtAPIGoogle();
        $client = $googleApi->getClient();
        $eapm = \EAPM::getLoginInfo('Google');

        if (empty($eapm->api_data) || (isset($client->token) && !isset($client->token->access_token))) {
            return [
                'success' => false,
                'message' => 'LBL_CHECK_GOOGLE_CONNECTION',
            ];
        }

        return $client;
    }

    /**
     * Create a new shared drive
     *
     * @param string $folderName
     * @param string $driveId
     *
     * @return Object
     * @throws Google_Exception
     */
    private function createNewSharedDrive($folderName)
    {
        $client = $this->getClient();
        $drive = new GDrive($client);
        $driveMetadata = new GDrive\Drive();
        $driveMetadata->setName($folderName);
        try {
            $newSharedDrive = $drive->drives->create(uniqid(), $driveMetadata);
            return $newSharedDrive;
        } catch (Google_Exception $e) {
            $GLOBALS['log']->fatal($e);
            return [
                'success' => false,
                'message' => 'LBL_SHARED_DRIVE_ACTION_FAIL_MSG',
            ];
        }
    }

    /**
     * Create a folder on a shared drive
     *
     * @param string $folderName
     * @param string $parent
     *
     * @return Object
     * @throws Google_Exception
     */
    private function createFolderOnSharedDrive($folderName, $parent)
    {
        $client = $this->getClient();
        $drive = new GDrive($client);
        $folderMetadata = new DriveFile([
            'name' => $folderName,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => [$parent],
        ]);

        try {
            $folder = $drive->files->create($folderMetadata, [
                'fields' => 'id',
                'supportsAllDrives' => true,
            ]);

            return $folder;
        } catch (Google_Exception $e) {
            $GLOBALS['log']->fatal($e);
            return [
                'success' => false,
                'message' => 'LBL_SHARED_DRIVE_ACTION_FAIL_MSG',
            ];
        }
    }
}
