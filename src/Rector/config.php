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

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\MemoryCacheStorage;
use Rector\CodingStyle\Rector\FuncCall\ConsistentImplodeRector;
use Rector\Php70\Rector\StaticCall\StaticCallOnNonStaticToInstanceCallRector;
use Rector\Php72\Rector\Assign\ListEachRector;
use Rector\Php72\Rector\Assign\ReplaceEachAssignmentWithKeyCurrentRector;
use Rector\Php72\Rector\FuncCall\GetClassOnNullRector;
use Rector\Php72\Rector\FuncCall\ParseStrWithResultArgumentRector;
use Rector\Php72\Rector\FuncCall\StringifyDefineRector;
use Rector\Php72\Rector\While_\WhileEachToForeachRector;
use Rector\Php73\Rector\FuncCall\StringifyStrNeedlesRector;
use Rector\Php74\Rector\Double\RealToFloatTypeCastRector;
use Rector\Php74\Rector\FuncCall\ArrayKeyExistsOnPropertyRector;
use Rector\Php74\Rector\FuncCall\FilterVarToAddSlashesRector;
use Rector\Php74\Rector\FuncCall\MbStrrposEncodingArgumentPositionRector;
use Rector\Php74\Rector\FuncCall\MoneyFormatToNumberFormatRector;
use Rector\Php74\Rector\Property\RestoreDefaultNullToNullableTypePropertyRector;
use Rector\Php74\Rector\StaticCall\ExportToReflectionFunctionRector;
use Rector\Php80\Rector\ClassMethod\AddParamBasedOnParentClassMethodRector;
use Rector\Php80\Rector\ClassMethod\SetStateToStaticRector;
use Rector\Config\RectorConfig;
use Rector\Php74\Rector\ArrayDimFetch\CurlyToSquareBracketArrayStringRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->disableParallel();

    sugar_mkdir(sugar_cached('rector'));
    $baseCacheDir = realpath(sugar_cached('rector'));
    $rectorConfig->containerCacheDirectory($baseCacheDir);
    $rectorConfig->cacheDirectory($baseCacheDir . '/cached_files');
    $rectorConfig->cacheClass(MemoryCacheStorage::class);

    $rectorConfig->rules([
        AddParamBasedOnParentClassMethodRector::class,
        SetStateToStaticRector::class,
        ArrayKeyExistsOnPropertyRector::class,
        ExportToReflectionFunctionRector::class,
        FilterVarToAddSlashesRector::class,
        MbStrrposEncodingArgumentPositionRector::class,
        MoneyFormatToNumberFormatRector::class,
        RealToFloatTypeCastRector::class,
        RestoreDefaultNullToNullableTypePropertyRector::class,
        StringifyStrNeedlesRector::class,
        GetClassOnNullRector::class,
        ListEachRector::class,
        ReplaceEachAssignmentWithKeyCurrentRector::class,
        ParseStrWithResultArgumentRector::class,
        StringifyDefineRector::class,
        WhileEachToForeachRector::class,
        ConsistentImplodeRector::class,
        CurlyToSquareBracketArrayStringRector::class,
        StaticCallOnNonStaticToInstanceCallRector::class,
    ]);
};
