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

use EAPM;
use ExtAPIMicrosoft;
use GuzzleHttp\Psr7\Utils;
use Microsoft\Graph\Core\Tasks\LargeFileUploadTask;
use Microsoft\Graph\Generated\Drives\Item\Items\Item\Children\ChildrenRequestBuilderGetQueryParameters;
use Microsoft\Graph\Generated\Drives\Item\Items\Item\Children\ChildrenRequestBuilderGetRequestConfiguration;
use Microsoft\Graph\Generated\Drives\Item\Items\Item\CreateUploadSession\CreateUploadSessionPostRequestBody;
use Microsoft\Graph\Generated\Drives\Item\Items\Item\DriveItemItemRequestBuilderGetQueryParameters;
use Microsoft\Graph\Generated\Drives\Item\Items\Item\DriveItemItemRequestBuilderGetRequestConfiguration;
use Microsoft\Graph\Generated\Models\DriveItem;
use Microsoft\Graph\Generated\Models\DriveItemUploadableProperties;
use Microsoft\Graph\Generated\Models\Folder;
use Sugarcrm\Sugarcrm\CloudDrive\Drive;
use Sugarcrm\Sugarcrm\CloudDrive\Constants\DriveType;
use Sugarcrm\Sugarcrm\CloudDrive\Model\DriveItemMapper;
use SchedulersJob;
use Sugarcrm\Sugarcrm\Util\Uuid;
use SugarJobQueue;

class OneDrive extends Drive
{
    /**
     * List folders in a drive
     *
     * @param array $options
     * @return array|\GraphProxy
     */

    public function listFolders(array $options)
    {
        $driveId = $options['driveId'] ?? null;
        $parentId = $options['parentId'] ?? null;
        $sharedWithMe = $options['sharedWithMe'] ?? false;
        $folderName = $options['folderName'] ?? null;
        $folderParent = $options['folderParent'] ?? 'root';

        $client = $this->getClient();
        if (is_array($client) && !$client['success']) {
            return $client;
        }

        if (!$driveId) {
            $driveId = $client->me()->drive()->get()->wait()->getId();
        }

        try {
            if ($folderName) {
                if ($sharedWithMe) {
                    return $this->searchSharedFolders($client, $driveId, $folderName, $options);
                } else {
                    // get drives by folder name
                    $driveItems = $this->fetchDriveItems($client, $driveId, $parentId, $sharedWithMe);
                }
            } else {
                // get drive items from root or from parentId
                $driveItems = $this->fetchDriveItems($client, $driveId, $parentId, $sharedWithMe);
            }

            return $this->processDriveItems($client, $driveItems, $folderName, $folderParent);
        } catch (\Exception $e) {
            $options['driveId'] = null;
            return $this->listFolders($options);
        }
    }

    public function searchSharedFolders($client, $driveId, $folderName, &$options): array
    {
        try {
            $sharedItems = $client->drives()->byDriveId($driveId)->sharedWithMe()->get()->wait();
            $driveIds = [];
            $items = $sharedItems->getValue() ?? [];

            foreach ($items as $driveItem) {
                $remoteItem = $driveItem->getRemoteItem();
                if ($remoteItem) {
                    $driveIds[] = $remoteItem->getParentReference()->getDriveId();
                }
            }

            $files = [];
            foreach (array_unique($driveIds) as $sharedDriveId) {
                $data = $client->drives()->byDriveId($sharedDriveId)->searchWithQ("{$folderName}")->get()->wait();
                $items = $data->getValue() ?? [];
                foreach ($items as $sharedItem) {
                    if ($sharedItem->getName() === $folderName) {
                        $files[] = $sharedItem;
                    }
                }
            }

            return [
                'files' => $files,
                'nextPageToken' => null,
            ];
        } catch (\Exception $e) {
            $options['sharedWithMe'] = false;
            return $this->listFolders($options);
        }
    }

    public function fetchDriveItems($client, $driveId, $parentId, $sharedWithMe)
    {
        $parentId = $parentId ?? 'root';
        $queryParams = new ChildrenRequestBuilderGetQueryParameters(filter: 'folder ne null');
        $requestConfig = new ChildrenRequestBuilderGetRequestConfiguration(queryParameters: $queryParams);

        if ($sharedWithMe) {
            return $client->drives()->byDriveId($driveId)->sharedWithMe()->get()->wait();
        } else {
            return $client->drives()->byDriveId($driveId)->items()->byDriveItemId($parentId)->children()->get($requestConfig)->wait();
        }
    }

    public function processDriveItems($client, $driveItems, $folderName, $folderParent)
    {
        $filteredData = [];
        $filteredRootData = [];
        $items = $driveItems->getValue() ?? [];

        foreach ($items as $item) {
            $parentReference = $item->getParentReference();
            $parent = null;

            if ($parentReference && !is_null($parentReference->getId())) {
                $data = $client->drives()->byDriveId($parentReference->getDriveId())
                    ->items()
                    ->byDriveItemId($parentReference->getId())
                    ->get(new DriveItemItemRequestBuilderGetRequestConfiguration(
                        queryParameters: new DriveItemItemRequestBuilderGetQueryParameters(select: ['name', 'id'])
                    ))->wait();

                $mapper = new DriveItemMapper($data, DriveType::ONEDRIVE);
                $parent = $mapper->mapToDriveItem();
            }

            $isRoot = $folderParent === 'root';
            $isMatchingParent = isset($parent) && ($parent->name === $folderParent || $parent->id === $folderParent);

            if ($isRoot && isset($parent) && is_array($parent) && $parent['success'] === false) {
                $filteredData[] = $item;
            } elseif ($item->getFolder() && $item->getName() === $folderName && $isMatchingParent) {
                $filteredData[] = $item;
            }

            if ($isMatchingParent) {
                $filteredRootData[] = $item;
            }
        }

        $nextLink = $driveItems->getOdataNextLink();
        $finalItems = $folderName ? $filteredData : (count($filteredRootData) ? $filteredRootData : $driveItems->getValue() ?? []);
        $mapper = new DriveItemMapper($finalItems, DriveType::ONEDRIVE);
        $mappedData = $mapper->mapToArray();

        return [
            'files' => $mappedData,
            'nextPageToken' => $nextLink,
        ];
    }

    /**
     * Create a folder on the drive
     *
     * @param array $options
     * @return null|string
     * @throws \Exception
     */
    public function createFolder(array $options): ?array
    {
        $folderName = $options['name'] ?? null;
        $driveId = $options['driveId'] ?? null;
        $parentId = $options['parent'] ?? 'root';

        if (!$folderName) {
            return null;
        }

        $client = $this->getClient();

        $folder = new Folder();
        $folder->setChildCount(0);

        $driveItem = new DriveItem();
        $driveItem->setName($folderName);
        $driveItem->setFolder($folder);
        $driveItem->setAdditionalData(['@microsoft.graph.conflictBehavior' => 'rename']);

        try {
            if (!$driveId) {
                // get root driveId
                $driveId = $client->me()->drive()->get()->wait()->getId();
            }

            $driveItemRequest = $client->drives()->byDriveId($driveId)->items();

            if ($parentId !== 'root') {
                $drive = $driveItemRequest->byDriveItemId($parentId)->children()->post($driveItem)->wait();
            } else {
                $drive = $driveItemRequest->post($driveItem)->wait();
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getBackingStore()?->get('error')?->getMessage(),
            ];
        }

        return [
            'driveId' => $drive->getParentReference()->getDriveId(),
            'id' => $drive->getId(),
        ];
    }

    /**
     * List files in a folder
     *
     * @param array $options
     * @return array
     * @throws \Exception
     */
    public function listFiles(array $options): array
    {
        global $sugar_config;

        $folderId = $options['folderId'] ?? 'root';
        $sharedWithMe = $options['sharedWithMe'] ?? false;
        $driveId = $options['driveId'] ?? null;
        $sortOptions = $options['sortOptions'] ?? false;
        $top = $sugar_config['list_max_entries_per_page'];
        $orderBy = $sortOptions ? ["{$sortOptions['fieldName']} {$sortOptions['direction']}"] : null;

        $queryParams = new ChildrenRequestBuilderGetQueryParameters(orderby: $orderBy, top: $top);
        $requestConfig = new ChildrenRequestBuilderGetRequestConfiguration(queryParameters: $queryParams);

        $client = $this->getClient();

        if (!$driveId) {
            $driveId = $client->me()->drive()->get()->wait()->getId();
        }

        if ($sharedWithMe) {
            $items = $client->drives()->byDriveId($driveId)->sharedWithMe()->get()->wait();
        } else {
            $itemsRequest = $client->drives()->byDriveId($driveId)->items()->byDriveItemId($folderId)->children();

            if (!empty($options['nextPageToken'])) {
                $itemsRequest = $itemsRequest->withUrl($options['nextPageToken']);
            }

            $items = $itemsRequest->get($requestConfig)->wait();
        }

        $data = $items->getValue();
        $nextLink = $items->getOdataNextLink();

        $mapper = new DriveItemMapper($data, DriveType::ONEDRIVE);
        $mappedData = $mapper->mapToArray();
        return [
            'files' => $mappedData,
            'nextPageToken' => $nextLink,
        ];
    }

    /**
     * Get file data from drive
     *
     * @param array $options
     * @return mixed
     * @throws \Exception
     */
    public function getFile(array $options)
    {
        $fileId = $options['fileId'];
        $driveId = $options['driveId'];
        $select = $options['select'] ?? null;
        $client = $this->getClient();

        if (is_array($client) && !$client['success']) {
            return $client;
        }

        if (!$driveId) {
            $driveId = $client->me()->drive()->get()->wait()->getId();
        }

        $qp = new DriveItemItemRequestBuilderGetQueryParameters(
            select: [$select]
        );
        $req = new DriveItemItemRequestBuilderGetRequestConfiguration(
            queryParameters: $qp
        );

        try {
            $driveItemReq = $client->drives()->byDriveId($driveId)->items()->byDriveItemId($fileId);
            $driveItem = $select ? $driveItemReq->get($req)->wait() : $driveItemReq->get()->wait();
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getBackingStore()?->get('error')?->getMessage(),
            ];
        }

        $mapper = new DriveItemMapper($driveItem, DriveType::ONEDRIVE);
        return $mapper->mapToDriveItem();
    }

    /**
     *  Deletes a file from the drive
     *
     * @param array $options
     * @return null|array
     * @throws \Exception
     */
    public function deleteFile(array $options)
    {
        $client = $this->getClient();

        $fileId = $options['fileId'];
        $driveId = $options['driveId'];

        if (!$driveId) {
            $driveId = $client->me()->drive()->get()->wait()->getId();
        }

        try {
            return $client->drives()->byDriveId($driveId)->items()->byDriveItemId($fileId)->delete()->wait();
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getBackingStore()?->get('error')?->getMessage(),
            ];
        }
    }

    /**
     * Download file from drive
     *
     * @param array $options
     * @return mixed
     * @throws \Exception
     */
    public function downloadFile(array $options)
    {
        $client = $this->getClient();

        $fileId = $options['fileId'];
        $driveId = $options['driveId'];

        if (!$driveId) {
            $driveId = $client->me()->drive()->get()->wait()->getId();
        }

        try {
            $fileContent = $client->drives()->byDriveId($driveId)->items()->byDriveItemId($fileId)->content()->get()->wait();
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getBackingStore()?->get('error')?->getMessage(),
            ];
        }

        return [
            'success' => true,
            'content' => base64_encode($fileContent->getContents()),
        ];
    }

    /**
     * Get microsoft api client
     *
     */
    public function getClient()
    {
        $api = new ExtAPIMicrosoft();
        $eapm = EAPM::getLoginInfo('Microsoft');

        if (empty($eapm->api_data)) {
            return [
                'success' => false,
                'message' => 'LBL_CHECK_MICROSOFT_CONNECTION',
            ];
        }

        $token = json_decode($eapm->api_data);

        return $api->getClient($token->refresh_token ?? '', $eapm->id);
    }

    /**
     * Uploads a file to the drive
     *
     * @param array $options
     * @return array
     * @throws \Exception
     */
    public function uploadFile($options): array
    {
        $client = $this->getClient();
        $parentId = !empty($options['parentId']) ? $options['parentId'] : (!empty($options['pathId']) ? $options['pathId'] : 'root');
        $fileName = $options['fileName'];
        $driveId = $options['driveId'] ?? null;

        if ($options['data']) {
            $data = $options['data'];
            $filePath = $data['tmp_name'];
        } else {
            if (isset($options['filePath'])) {
                $filePath = $options['filePath'];
            } else {
                $filePath = $this->getFilePath($options['documentBean']);
            }
        }

        if (!$driveId) {
            $driveId = $client->me()->drive()->get()->wait()->getId();
        }

        $file = Utils::streamFor(fopen($filePath, 'r'));

        try {
            $client->drives()->byDriveId($driveId)->items()->byDriveItemId($parentId . ':/' . $fileName . ':')
                ->content()->put($file)->wait();
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getBackingStore()?->get('error')?->getMessage(),
            ];
        }

        return [
            'success' => true,
            'message' => 'LBL_FILE_UPLOADED',
        ];
    }

    /**
     * Uploads a large file.
     * @param array $options
     * @return array
     */
    public function uploadLargeFile($options): array
    {
        $client = $this->getClient();

        $parentId = !empty($options['parentId']) ? $options['parentId'] : (!empty($options['pathId']) ? $options['pathId'] : 'root');
        $fileName = $options['fileName'];
        $driveId = $options['driveId'] ?? null;

        if ($options['data']) {
            $data = $options['data'];
            $filePath = $data['tmp_name'];
            $fileSize = $data['size'];
        } else {
            $filePath = $options['filePath'];
            $fileSize = $options['fileSize'];
        }

        if (!$driveId) {
            $driveId = $client->me()->drive()->get()->wait()->getId();
        }

        // Create a file stream
        $file = Utils::streamFor(fopen($filePath, 'r'));

        // Create the upload session request
        $uploadProperties = new DriveItemUploadableProperties();
        $uploadProperties->setAdditionalData([
            '@microsoft.graph.conflictBehavior' => 'replace',
        ]);

        $uploadSessionRequest = new CreateUploadSessionPostRequestBody();
        $uploadSessionRequest->setItem($uploadProperties);

        $uploadSession = $client->drives()
            ->byDriveId($driveId)
            ->items()
            ->byDriveItemId($parentId . ':/' . $fileName . ':')
            ->createUploadSession()
            ->post($uploadSessionRequest)
            ->wait();

        $largeFileUpload = new LargeFileUploadTask($uploadSession, $client->getRequestAdapter(), $file);

        try {
            $largeFileUpload->upload()->wait();
        } catch (\Psr\Http\Client\NetworkExceptionInterface $ex) {
            $largeFileUpload->resume()->wait();
        }

        return [
            'success' => true,
            'message' => 'LBL_LARGE_FILE_UPLOAD',
        ];
    }

    /**
     * Uploads chunks of the file in a schedule job
     * @param string $uploadUrl
     * @param string $filePath
     * @param GraphProxy $client
     * @param string $fileName
     * @param null|int $fileSize
     *
     * @return array
     */
    protected function uploadChunks(string $uploadUrl, string $filePath, string $fileName, ?int $fileSize): array
    {
        global $current_user;

        $payload = [
            'uploadUrl' => $uploadUrl,
            'filePath' => $filePath,
            'fileName' => $fileName,
            'fileSize' => $fileSize,
        ];

        $job = new SchedulersJob();
        $job->name = 'OneDriveUploadJob- ' . Uuid::uuid4();
        $job->data = base64_encode(serialize($payload));
        $job->target = 'class::OneDriveUploadJob';
        $job->assigned_user_id = $current_user->id;
        $jq = new SugarJobQueue();
        $jq->submitJob($job);

        return [
            'success' => true,
            'message' => 'LBL_LARGE_FILE_UPLOAD',
        ];
    }
}
