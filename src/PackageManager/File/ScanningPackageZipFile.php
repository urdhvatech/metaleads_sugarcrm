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

namespace Sugarcrm\Sugarcrm\PackageManager\File;

use FilesystemIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use RuntimeException;
use SplFileInfo;
use SugarConfig;
use Sugarcrm\Sugarcrm\DependencyInjection\Container;
use Sugarcrm\Sugarcrm\FeatureToggle\FeatureFlag;
use Sugarcrm\Sugarcrm\FeatureToggle\Features\TranslateMLPCode;
use ZipArchive;
use Sugarcrm\Sugarcrm\Util\Files\FileLoader;
use Sugarcrm\Sugarcrm\Util\Files\RecursiveDirectoryIterator;

class ScanningPackageZipFile
{
    /**
     * @var string
     */
    private $zipFile;

    /**
     * @var string
     */
    private $basePackagesDir;

    /**
     * @var string
     */
    private $packageDir;

    /**
     * @var string
     */
    private $packageDirKey;

    /**
     * @var bool
     */
    private $isFullPackageExtracted = false;

    /**
     * PackageZipFile constructor.
     * @param string $zipFile
     * @param string $basePackagesDir
     * @param ?string $packageDirName unpacked package directory name relative to $baseDir
     * @throws NoPackageFileException
     */
    public function __construct(string $zipFile, string $basePackagesDir, ?string $packageDirKey)
    {
        $this->basePackagesDir = $basePackagesDir;
        if ($packageDirKey !== null) {
            try {
                $this->packageDirKey = $packageDirKey;
                $this->packageDir = $this->validateFilePath($this->basePackagesDir . DIRECTORY_SEPARATOR . $packageDirKey);
            } catch (RuntimeException $e) {
                $exception = new NoPackageFileException();
                throw $exception;
            }
            if (!is_dir($this->packageDir)) {
                throw new NoPackageFileException();
            }
            $this->isFullPackageExtracted = true;
            return;
        }

        try {
            $zipFile = $this->validateFilePath($zipFile);
        } catch (RuntimeException $e) {
            $exception = new NoPackageFileException();
            $exception->setErrorDescription($e->getMessage());
            throw $exception;
        }

        if (strpos($zipFile, \UploadStream::getDir()) !== false) {
            try {
                $fileConverter = new FilePhpEntriesConverter();

                //We should abort the process in case if file converter returns SugarException
                $this->zipFile = $fileConverter->revert($zipFile);

                register_shutdown_function([$this, 'unlinkZip']);
            } catch (SugarException $e) {
                $exception = new PackageConvertingException();
                $exception->setErrorDescription($e->getMessage());
                throw $exception;
            }
        } else {
            $this->zipFile = $zipFile;
        }
    }

    /**
     * extract package to package dir
     * @throws UnableExtractFileException
     */
    public function extractPackage(): void
    {
        if ($this->isFullPackageExtracted) {
            return;
        }
        if (!$this->packageDir) {
            $this->createPackageDir();
        }
        $archive = $this->openZipArchive();
        $result = $archive->extractTo($this->packageDir);
        if ($result !== true) {
            throw new UnableExtractFileException('ERR_UW_UNABLE_EXTRACT_FILE', [intval($result), $archive->status]);
        }

        if (SugarConfig::getInstance()->get('moduleInstaller.packageScan', false)) {
            $features = Container::getInstance()->get(FeatureFlag::class);
            if ($features->isEnabled(TranslateMLPCode::getName())) {
                $this->translateCode();
            }
        }

        $this->isFullPackageExtracted = true;
    }

    public function getPackageFileList(?array $extensions = null): array
    {
        $dir = $this->getPackageDir();
        if (!is_dir($dir)) {
            return [];
        }
        $dirPathLen = strlen($dir);
        $files = [];
        /** @var $fileInfo SplFileInfo */
        $fsIterator = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
        foreach (new RecursiveIteratorIterator($fsIterator) as $pathName => $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }
            if ($extensions !== null && !in_array($fileInfo->getExtension(), $extensions)) {
                continue;
            }
            $files[] = substr($pathName, $dirPathLen);
        }

        return $files;
    }

    /**
     * return package dir
     * @return string
     */
    public function getPackageDir(): string
    {
        return $this->packageDir;
    }

    /**
     * clean up after self
     */
    public function deletePackageDir(): void
    {
        if (file_exists($this->packageDir)) {
            rmdir_recursive($this->packageDir);
        }
    }

    public function unlinkZip(): void
    {
        unlink($this->zipFile);
    }

    public function cleanup(): void
    {
        register_shutdown_function([$this, 'deletePackageDir']);
    }

    public function getPackageDirKey(): ?string
    {
        return $this->packageDirKey;
    }

    /**
     * @param string $path
     * @return string
     * @throws RuntimeException
     */
    protected function validateFilePath(string $path): string
    {
        return FileLoader::validateFilePath($path, true);
    }

    /**
     * create temp package dir and register shutdown function to delete it
     */
    protected function createPackageDir()
    {
        if (!file_exists($this->basePackagesDir)) {
            sugar_mkdir($this->basePackagesDir, null, true);
        }
        $this->packageDir = mk_temp_dir($this->basePackagesDir);
        $this->packageDirKey = basename($this->packageDir);
    }

    /**
     * @return ZipArchive
     * @throws UnableExtractFileException
     */
    protected function openZipArchive(): ZipArchive
    {
        $archive = new ZipArchive();
        $result = $archive->open($this->zipFile);
        if ($result !== true) {
            throw new UnableExtractFileException('ERR_UW_UNABLE_EXTRACT_FILE', [intval($result), $archive->status]);
        }
        return $archive;
    }

    /**
     * @return void
     */
    protected function translateCode(): void
    {
        $recursiveIterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $this->packageDir,
                FilesystemIterator::SKIP_DOTS | FilesystemIterator::KEY_AS_PATHNAME | FilesystemIterator::CURRENT_AS_FILEINFO
            )
        );
        $regex = '/^.+\.php$/i';
        foreach (new RegexIterator($recursiveIterator, $regex) as $phpFile) {
            $code = file_get_contents($phpFile);
            $translatedCode = SweetTranslator::translate($code);
            if (false === sugar_file_put_contents($phpFile, $translatedCode)) {
                throw new \RuntimeException("Failed to write translated code into $phpFile");
            }
        }
    }
}
