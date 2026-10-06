{*
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
*}
{if !$item|is_array}
    <div class="text-xs leading-4 font-normal text-gray-800 dark:text-gray-300">{$item|escape:'html'}</div>
{else}
    <div class="flex flex-col">
        <div class="inline-flex items-center border p-1 rounded-xl max-w-fit dark:bg-gray-800 dark:border-gray-600
                follow-up-pill border-gray-300"
            data-module="{$item.moduleName|escape:'htmlall'}"
            data-id="{$item.objectId|escape:'htmlall'}"
            data-name="{$item.name|escape:'htmlall'}">
            <i class="sicon label-module-size-sm {$item.icon|escape:'htmlall'} mr-2 w-5 h-5 rounded-full !inline-flex
                    items-center justify-center {$item.iconColor|escape:'htmlall'}"></i>
            <a target="_blank" href="{$item.link|escape:'htmlall'}"
               class="font-normal text-xs leading-[18px] no-underline whitespace-nowrap overflow-hidden text-ellipsis
                    text-blue-600 dark:text-blue-300">
                {$item.name|escape:'html'}
            </a>
        </div>

        {if $item.followUp}
            <div class="text-xs leading-4 font-normal text-gray-800 dark:text-gray-300">{$item.followUp|escape:'html'}
            </div>
        {/if}
    </div>
{/if}
