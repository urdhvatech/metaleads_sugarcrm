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
namespace Sugarcrm\Sugarcrm\PackageBuilder;

use Exception;
use InvalidArgumentException;
use Ramsey\Uuid\Exception\UnsatisfiedDependencyException;
use Sugarcrm\Sugarcrm\Util\Uuid;
use SugarException;
use UploadFile;
use UploadStream;

/**
 * *** FileHandlerTrait ***
 *
 * This trait manipulates Sugar files as it provides support for uploading/downloading/deleting them.
 *
 */
trait FileHandlerTrait
{
    /**
     * @param UploadStream $stream
     * @param string $path
     * @param string $mode
     *
     * @return resource
     *
     * @throws SugarException
     */
    public function fileOpen(UploadStream $stream, string $path, string $mode)
    {
        $streamOpened = $stream->stream_open($path, $mode);

        if (!$streamOpened) {
            throw new SugarException("Fail to open file {$path}.");
        }

        return $stream->stream_cast(null);
    }

    /**
     * @param UploadStream $stream
     *
     * @return bool
     */
    public function fileClose(UploadStream $stream): bool
    {
        return $stream->stream_close();
    }

    /**
     * Returns the upload path for the given file.
     *
     * @param string $id
     *
     * @return string 'upload/28587d2c-0192-11eb-8496-08002709c5df'
     */
    public function fileUploadPathGet(string $id): string
    {
        return (new UploadFile())->get_upload_path($id);
    }

    /**
     * Initializes the stream resource accordingly if we deal with upload files or relative path files.
     *
     * @param bool $isUploadFile True to init upload file stream, false to init relative path file stream
     *
     * @return UploadStream
     */
    public function fileStreamInit(bool $isUploadFile): UploadStream
    {
        if ($isUploadFile) {
            return new UploadStream();
        }

        return new FileHandlerTrait_UploadStream();
    }

    /**
     * @access protected
     *
     * @param string $path
     * @param bool $isUploadFile
     *
     * @return string
     */
    protected function filePathParse(string $path, bool $isUploadFile): string
    {
        if ($isUploadFile) {
            return $this->fileUploadPathGet($path);
        }

        return $path;
    }

    /**
     * <code>
     *
     * $this->fileGetSize('custom/Sample.txt', false);
     *
     * --------------------
     *
     * $this->fileGetSize('8f12ad-0189-11eb-b339-08002709c5df', true);
     *
     * </code>
     *
     * @param string $pathOrId         Relative file path or ID to point to a file from the upload folder
     * @param bool   $isInUploadFolder True to point to a file from the upload folder (based on the file ID)
     *
     * @return float
     *
     * @throws \SugarException
     */
    public function fileGetSize(string $pathOrId, bool $isInUploadFolder): float
    {
        $filePath = $this->filePathParse($pathOrId, $isInUploadFolder);
        $stream = $this->fileStreamInit($isInUploadFolder);

        $this->fileOpen($stream, $filePath, 'r');
        $stats = $stream->stream_stat();
        $this->fileClose($stream);

        return floatval($stats['size'] ?? 0);
    }
}

class FileHandlerTrait_UploadStream extends UploadStream
{
    public function getFSPath($path)
    {
        /**
         * Returns the path as it is so to be able to handle files outside the upload folder as well.
         *
         * By default, this method would return a path like "upload/{$path}",
         * which would force us to only handle files from the upload folder.
         */
        return $path;
    }
}
