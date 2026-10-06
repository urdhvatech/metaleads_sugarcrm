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

namespace Sugarcrm\Sugarcrm\Util\Files;

use Iterator;
use RecursiveIterator;
use RuntimeException;
use SplFileInfo;
use SplFileObject;
use UnexpectedValueException;

/**
 * This class is a reimplementation of the DirectoryIterator in pure PHP to workaround issues with shadow
 */
class DirectoryIterator implements Iterator
{
    /** @var string */
    protected $path;

    /** @var array */
    protected $entries = [];

    /** @var int */
    protected $position = 0;

    public function __construct($path)
    {
        if (!is_dir($path)) {
            throw new UnexpectedValueException("Directory \"$path\" does not exist");
        }

        $this->path = rtrim($path, '/\\');

        $entries = @scandir($this->path);
        if ($entries === false) {
            throw new UnexpectedValueException("Failed to open dir: {$this->path}");
        }

        $this->entries = array_values($entries);
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function current(): mixed
    {
        return $this;
    }

    public function key(): mixed
    {
        return $this->position;
    }

    public function next(): void
    {
        $this->position++;
    }

    public function valid(): bool
    {
        return isset($this->entries[$this->position]);
    }

    public function getFilename()
    {
        return $this->entries[$this->position];
    }

    public function getPath()
    {
        return $this->path;
    }

    public function getPathname()
    {
        return $this->path . DIRECTORY_SEPARATOR . $this->getFilename();
    }

    public function isDot()
    {
        $name = $this->getFilename();
        return $name === '.' || $name === '..';
    }

    public function isDir()
    {
        return is_dir($this->getPathname());
    }

    public function isFile()
    {
        return is_file($this->getPathname());
    }

    public function getPerms()
    {
        return fileperms($this->getPathname());
    }

    public function getSize()
    {
        return filesize($this->getPathname());
    }

    public function getMTime()
    {
        return filemtime($this->getPathname());
    }

    public function getATime()
    {
        return fileatime($this->getPathname());
    }

    public function getCTime()
    {
        return filectime($this->getPathname());
    }

    public function getType()
    {
        return filetype($this->getPathname());
    }

    public function isReadable()
    {
        return is_readable($this->getPathname());
    }

    public function isWritable()
    {
        return is_writable($this->getPathname());
    }

    public function isExecutable()
    {
        return is_executable($this->getPathname());
    }

    public function isLink()
    {
        return is_link($this->getPathname());
    }

    public function getLinkTarget()
    {
        if (!$this->isLink()) {
            return false;
        }
        return readlink($this->getPathname());
    }

    public function getRealPath()
    {
        return realpath($this->getPathname());
    }

    public function getInode()
    {
        return fileinode($this->getPathname());
    }

    public function getOwner()
    {
        return fileowner($this->getPathname());
    }

    public function getGroup()
    {
        return filegroup($this->getPathname());
    }

    public function getExtension()
    {
        return pathinfo($this->getFilename(), PATHINFO_EXTENSION);
    }

    public function getBasename($suffix = '')
    {
        $name = $this->getFilename();
        if ($suffix !== '' && str_ends_with($name, $suffix)) {
            return substr($name, 0, -strlen($suffix));
        }
        return $name;
    }

    public function openFile($mode = "r", $useIncludePath = false, $context = null)
    {
        $full = $this->getPathname();
        if (!is_file($full)) {
            throw new RuntimeException("Cannot open directory as file: {$full}");
        }

        if ($context !== null) {
            return new SplFileObject($full, $mode, $useIncludePath, $context);
        }
        return new SplFileObject($full, $mode, $useIncludePath);
    }
}
