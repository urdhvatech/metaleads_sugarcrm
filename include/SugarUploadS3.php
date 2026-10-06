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

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use Psr\Log\LoggerInterface;
use Sugarcrm\Sugarcrm\DependencyInjection\Container;

/**
 * S3 uploads driver
 * @api
 */
class SugarUploadS3 extends UploadStream
{
    protected $s3;
    protected $s3dir;
    protected $path;
    protected $localpath;
    protected $write;
    protected $bucket;
    protected $metadata;

    public const S3_STREAM_NAME = 'uploads3';

    public function __construct()
    {
        parent::__construct();
        $this->init();
    }

    /**
     * Initialize the data
     * Not doing it in ctor due to the bug in PHP where ctor is not called on some stream ops
     */
    protected function init()
    {
        if (!empty($this->s3)) {
            return;
        }
        if (empty($GLOBALS['sugar_config']['aws'])
            || empty($GLOBALS['sugar_config']['aws']['aws_key'])
            || empty($GLOBALS['sugar_config']['aws']['aws_secret'])
            || empty($GLOBALS['sugar_config']['aws']['upload_bucket'])
            || empty($GLOBALS['sugar_config']['aws']['aws_region'])
        ) {
            $GLOBALS['log']->fatal('S3 keys are not set!');
            throw new Exception('S3 keys are not set!');
        }
        Container::getInstance()->get(LoggerInterface::class)
            ->warning('S3 is deprecated since 10.3.0 and will be removed in future versions of SugarCRM.');

        $this->bucket = $GLOBALS['sugar_config']['aws']['upload_bucket'];

        // Initialize AWS S3 client
        $this->s3 = new S3Client(
            [
                'version' => 'latest',
                'region' => $GLOBALS['sugar_config']['aws']['aws_region'],
                'credentials' => [
                    'key' => $GLOBALS['sugar_config']['aws']['aws_key'],
                    'secret' => $GLOBALS['sugar_config']['aws']['aws_secret'],
                ],
            ]
        );
    }

    /**
     * Convert upload url to form bucket/filename by converting all /s but last to -
     * @param string $path
     * @return string
     */
    public function urlToObject($path, $prefix = false)
    {
        $object = substr($path, strlen(self::STREAM_NAME) + 3); // upload://
        if ($prefix) {
            return self::S3_STREAM_NAME . '://' . $this->bucket . '/' . $object;
        } else {
            return $this->bucket . '/' . $object;
        }
    }


    /**
     * @param string $func Function
     * @param array $args arguments
     */
    protected function callS3($func, $args)
    {
        $message = "The method callS3 is removed. It was called with function - {$func}";
        trigger_error($message, E_USER_DEPRECATED);

        return false;
    }

    /**
     * Register new file added to uploads by external means
     * @param string $path
     * @return boolean
     */
    public function registerFile($path)
    {
        try {
            $result = $this->s3->putObject(
                [
                    'Bucket' => $this->bucket,
                    'Key' => $this->urlToObject($path),
                    'SourceFile' => parent::getFSPath($path),
                    'ACL' => 'private',
                ]
            );
            return !empty($result);
        } catch (S3Exception $e) {
            $GLOBALS['log']->fatal('Error uploading file to S3: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetch file if exists from S3 to local copy
     * @param string $path
     * @return string
     */
    public function fetchFile($path)
    {
        $localpath = parent::getFSPath($path);
        if (!file_exists($localpath)) {
            try {
                $result = $this->s3->getObject(
                    [
                        'Bucket' => $this->bucket,
                        'Key' => $this->urlToObject($path),
                    ]
                );

                if (isset($result['Body'])) {
                    file_put_contents($localpath, $result['Body']);
                } else {
                    $GLOBALS['log']->warning('Body key does not exist in the result.');
                }
            } catch (S3Exception $e) {
                $GLOBALS['log']->fatal('Error fetching file from S3: ' . $e->getMessage());
            }
        }
        return $localpath;
    }

    /**
     * Is this path an upload URL path?
     * @param string $path
     * @return boolean
     */
    public function isUploadUrl($path)
    {
        return substr($path, 0, strlen(self::STREAM_NAME) + 3) == self::STREAM_NAME . '://';
    }

    public function dir_closedir()
    {
        $this->s3dir = null;
    }

    public function dir_opendir($path, $options)
    {
        $this->init(); // because of php bug not calling stream ctor
        try {
            $result = $this->s3->listObjectsV2(
                [
                    'Bucket' => $this->bucket,
                    'Prefix' => $this->urlToObject($path),
                    'Delimiter' => '/',
                ]
            );
            $this->s3dir = [
                'prefixes' => $result->get('CommonPrefixes') ?? [],
                'objects' => $result->get('Contents') ?? [],
            ];
            $this->dir_rewinddir();
            return true;
        } catch (S3Exception $e) {
            $GLOBALS['log']->fatal('Error opening S3 directory: ' . $e->getMessage());
            return false;
        }
    }

    public function dir_readdir()
    {
        if (empty($this->s3dir)) {
            return false;
        }
        // Go first through all prefixes then though all objects
        $pref = current($this->s3dir['prefixes']);
        if ($pref !== false) {
            next($this->s3dir['prefixes']);
            return rtrim($pref, '/');
        }
        $obj = current($this->s3dir['objects']);
        if ($obj !== false) {
            next($this->s3dir['objects']);
        }
        return $obj;
    }

    public function dir_rewinddir()
    {
        if (empty($this->s3dir)) {
            return false;
        }
        reset($this->s3dir['prefixes']);
        reset($this->s3dir['objects']);
    }

    public function rename($path_from, $path_to)
    {
        parent::rename($path_from, $path_to);
        $this->init(); // because of php bug not calling stream ctor
        if ($this->isUploadUrl($path_to)) {
            if ($this->isUploadUrl($path_from)) {
                // from S3 to S3 - copy there
                $this->s3->copyObject(
                    [
                        'Bucket' => $this->bucket,
                        'CopySource' => "{$this->bucket}/" . $this->urlToObject($path_from),
                        'Key' => $this->urlToObject($path_to),
                    ]
                );
            } else {
                // from local to S3 - just register the copy, parent did the local part
                $this->registerFile($path_to);
            }
        }

        if ($this->isUploadUrl($path_from)) {
            $this->unlink($path_from);
        }

        return true;
    }

    public function stream_flush()
    {
        parent::stream_flush();
        if ($this->write) {
            if (file_exists($this->path) && filesize($this->path)) {
                $this->registerFile($this->path);
            }
        }
    }

    public function stream_open($path, $mode)
    {
        $this->path = $path;
        $this->localpath = parent::getFSPath($path);
        if (strpbrk($mode, 'wax')) {
            // writing - do nothing, we'll catch it on flush()
            $this->write = true;
        } else {
            // reading
            if (!file_exists($this->localpath)) {
                $this->fetchFile($path);
            }
        }
        return parent::stream_open($path, $mode);
    }

    public function unlink($path)
    {
        $this->init(); // because of php bug not calling stream ctor
        @unlink(parent::getFSPath($path));
        try {
            $this->s3->deleteObject(
                [
                    'Bucket' => $this->bucket,
                    'Key' => $this->urlToObject($path),
                ]
            );
            return true;
        } catch (S3Exception $e) {
            $GLOBALS['log']->fatal('Error deleting file from S3: ' . $e->getMessage());
            return false;
        }
    }

    public function url_stat($path, $flags)
    {
        $this->init(); // because of php bug not calling stream ctor
        if (file_exists(parent::getFSPath($path))) {
            return parent::url_stat($path, $flags);
        }

        try {
            $result = $this->s3->headObject(
                [
                    'Bucket' => $this->bucket,
                    'Key' => $this->urlToObject($path),
                ]
            );
            return [
                'size' => $result['ContentLength'],
                'mtime' => strtotime($result['LastModified']),
            ];
        } catch (S3Exception $e) {
            $GLOBALS['log']->fatal('Error fetching file metadata from S3: ' . $e->getMessage());
            return false;
        }
    }
}
