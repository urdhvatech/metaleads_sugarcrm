<?php declare(strict_types=1);
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

namespace PHPStan;

use Composer\Autoload\ClassLoader;

final class PharAutoloader
{
    /** @var ClassLoader */
    private static $composerAutoloader;

    final public static function loadClass(string $class): void
    {
        if (strpos($class, '_PHPStan_') === 0) {
            if (self::$composerAutoloader === null) {
                self::$composerAutoloader = require __DIR__ . '/phpstan.phar/vendor/autoload.php';
                require_once __DIR__ . '/phpstan.phar/vendor/jetbrains/phpstorm-stubs/PhpStormStubsMap.php';
                require_once __DIR__ . '/phpstan.phar/vendor/react/async/src/functions_include.php';
                require_once __DIR__ . '/phpstan.phar/vendor/react/promise/src/functions_include.php';
            }
            self::$composerAutoloader->loadClass($class);

            return;
        }
        if (strpos($class, 'PHPStan\\') !== 0 || strpos($class, 'PHPStan\\PhpDocParser\\') === 0) {
            return;
        }

        $filename = str_replace('\\', DIRECTORY_SEPARATOR, $class);
        if (strpos($class, 'PHPStan\\BetterReflection\\') === 0) {
            $filename = substr($filename, strlen('PHPStan\\BetterReflection\\'));
            $filepath = __DIR__ . '/phpstan.phar/vendor/ondrejmirtes/better-reflection/src/' . $filename . '.php';
        } else {
            $filename = substr($filename, strlen('PHPStan\\'));
            $filepath = __DIR__ . '/phpstan.phar/src/' . $filename . '.php';
        }

        if (!file_exists($filepath)) {
            return;
        }

        require $filepath;
    }
}

spl_autoload_register([PharAutoloader::class, 'loadClass']);
