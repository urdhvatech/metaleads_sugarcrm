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

use FilesystemIterator as NativeFilesystemIterator;
use SplFileInfo;
use Sugarcrm\Sugarcrm\Util\Files\DirectoryIterator;
use UnexpectedValueException;

/**
 * This class is a reimplementation of the FilesystemIterator in pure PHP to workaround issues with shadow
 */
class FilesystemIterator extends DirectoryIterator
{
    public const CURRENT_MODE_MASK = NativeFilesystemIterator::CURRENT_MODE_MASK;
    public const CURRENT_AS_PATHNAME = NativeFilesystemIterator::CURRENT_AS_PATHNAME;
    public const CURRENT_AS_FILEINFO = NativeFilesystemIterator::CURRENT_AS_FILEINFO;
    public const CURRENT_AS_SELF = NativeFilesystemIterator::CURRENT_AS_SELF;
    public const KEY_MODE_MASK = NativeFilesystemIterator::KEY_MODE_MASK;
    public const KEY_AS_PATHNAME = NativeFilesystemIterator::KEY_AS_PATHNAME;
    public const FOLLOW_SYMLINKS = NativeFilesystemIterator::FOLLOW_SYMLINKS;
    public const KEY_AS_FILENAME = NativeFilesystemIterator::KEY_AS_FILENAME;
    public const NEW_CURRENT_AND_KEY = NativeFilesystemIterator::NEW_CURRENT_AND_KEY;
    public const SKIP_DOTS = NativeFilesystemIterator::SKIP_DOTS;
    public const UNIX_PATHS = NativeFilesystemIterator::UNIX_PATHS;
    public const OTHER_MODE_MASK = NativeFilesystemIterator::OTHER_MODE_MASK;

    /** @var int */
    protected $flags;

    /**
     * @param string $path
     * @param int $flags Default = KEY_AS_PATHNAME|CURRENT_AS_FILEINFO|SKIP_DOTS
     *
     * @throws UnexpectedValueException if $path is not a directory or cannot be opened
     */
    public function __construct($path, $flags = null)
    {
        parent::__construct($path);

        if ($flags === null) {
            $this->flags = NativeFilesystemIterator::KEY_AS_PATHNAME | NativeFilesystemIterator::CURRENT_AS_FILEINFO | NativeFilesystemIterator::SKIP_DOTS;
        } else {
            $this->flags = (int)$flags;
        }

        if (($this->flags & NativeFilesystemIterator::SKIP_DOTS) !== 0) {
            $filtered = [];
            foreach ($this->entries as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }
                $filtered[] = $name;
            }
            $this->entries = array_values($filtered);
        }
    }

    public function current(): mixed
    {
        $name = $this->entries[$this->position];
        $fullPath = $this->buildFullPath($name);

        if ($this->flags & NativeFilesystemIterator::CURRENT_AS_PATHNAME) {
            return $fullPath;
        }

        if ($this->flags & NativeFilesystemIterator::CURRENT_AS_SELF) {
            return $this;
        }

        return new SplFileInfo($fullPath);
    }

    public function key(): mixed
    {
        $name = $this->entries[$this->position];
        $fullPath = $this->buildFullPath($name);

        if ($this->flags & NativeFilesystemIterator::KEY_AS_FILENAME) {
            return $name;
        }

        return $fullPath;
    }

    public function isDir()
    {
        $full = $this->buildFullPath($this->getFilename());
        if (is_link($full) && !($this->flags & NativeFilesystemIterator::FOLLOW_SYMLINKS)) {
            return false;
        }
        return is_dir($full);
    }

    public function isFile()
    {
        $full = $this->buildFullPath($this->getFilename());
        if (is_link($full) && !($this->flags & NativeFilesystemIterator::FOLLOW_SYMLINKS)) {
            return false;
        }
        return is_file($full);
    }

    /**
     * Helper to build "<dir><sep><entry>" using DIRECTORY_SEPARATOR
     * or "/" if UNIX_PATHS is set.
     */
    private function buildFullPath($entry)
    {
        $sep = ($this->flags & NativeFilesystemIterator::UNIX_PATHS) ? '/' : DIRECTORY_SEPARATOR;
        return $this->path . $sep . $entry;
    }
}
