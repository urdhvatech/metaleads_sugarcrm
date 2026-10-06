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

use RecursiveIterator;
use SplFileInfo;
use Sugarcrm\Sugarcrm\Util\Files\FilesystemIterator;
use UnexpectedValueException;

/**
 * This class is a reimplementation of the RecursiveDirectoryIterator in pure PHP to workaround issues with shadow
 */
class RecursiveDirectoryIterator extends FilesystemIterator implements RecursiveIterator
{
    /** @var string Absolute filesystem path of the original root */
    protected $rootPath;

    /** @var string Relative subpath (using “/”) under root for this instance */
    protected $subPath = '';

    /**
     * @param string $path
     * @param ?int $flags Default matches native RecursiveDirectoryIterator:
     *                       FilesystemIterator::KEY_AS_PATHNAME
     *                     | FilesystemIterator::CURRENT_AS_FILEINFO
     *
     * @throws UnexpectedValueException if $path is not a directory or cannot be opened
     */
    public function __construct($path, $flags = null)
    {
        if ($flags === null) {
            $flags = FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::CURRENT_AS_FILEINFO;
        }
        parent::__construct($path, $flags);
        $this->rootPath = rtrim($path, '/\\');
        $this->subPath = '';
    }

    /**
     * Returns true if the current entry is a directory that should be recursed into.
     *
     * @param bool $allowLinks
     * @return bool
     */
    public function hasChildren($allowLinks = false): bool
    {
        if (!$this->valid()) {
            return false;
        }

        if ($this->isDot()) {
            return false;
        }

        $full = $this->getPathname();
        if (is_link($full) && !$allowLinks) {
            return false;
        }
        return is_dir($full);
    }

    /**
     * Returns a new RecursiveDirectoryIterator for the current directory entry.
     *
     * @return RecursiveDirectoryIterator
     * @throws UnexpectedValueException if the child path is not a directory or cannot be opened
     */
    public function getChildren(): ?RecursiveIterator
    {
        $currentFull = $this->getPathname();
        $child = new self($currentFull, $this->flags);

        // Inherit the same root path
        $child->rootPath = $this->rootPath;

        // Build child's subPath relative to root, always using "/" as separator
        $name = $this->getFilename();
        if ($this->subPath === '') {
            $child->subPath = $name;
        } else {
            $child->subPath = $this->subPath . '/' . $name;
        }

        return $child;
    }

    /**
     * Returns the subpath (relative from the root) for this iterator node.
     *
     * @return string
     */
    public function getSubPath()
    {
        return $this->subPath;
    }

    /**
     * Returns the subpathname (subpath + "/" + filename) for the current entry.
     *
     * @return string
     */
    public function getSubPathname()
    {
        if ($this->subPath === '') {
            return $this->getFilename();
        }
        return $this->subPath . '/' . $this->getFilename();
    }
}
