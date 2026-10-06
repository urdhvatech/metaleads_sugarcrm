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
{foreach from=$fragment key=key item=value}
  <div class="px-2.5">
    {if $key|@strlen > 0}
      <div class="flex">
        <div class="rounded-full mr-2 w-1.5 h-1.5 mt-1.5 min-w-[6px] bg-gray-800 dark:bg-gray-300"></div>
        <div class="font-bold">{$key|escape:'htmlall'}:</div>
      </div>
    {/if}

    {if $value|@is_array}
      {if $value|isAssocArray}
        <div class="mb-1">
          {foreach from=$value key=subKey item=subValue}
            <div class="flex">
              <div class="rounded-full mr-2 w-1.5 h-1.5 mt-1.5 min-w-[6px] bg-gray-800 dark:bg-gray-300"></div>
              {if $subKey|@strlen > 0}
                <span class="font-bold mr-2">{$subKey|escape:'htmlall'}: </span>
              {/if}
              <span>{$subValue|escape:'htmlall'}</span>
            </div>
          {/foreach}
        </div>
      {else}
        {if $value|containsObjects}
          {foreach from=$value item=item}
            <ul class="pl-5 list-disc" style="padding-left: 20px; list-style-type: disc;">
              {foreach from=$item key=objKey item=objValue}
                <li><span class="font-bold">{$objKey|escape:'htmlall'}: </span>{$objValue|escape:'htmlall'}</li>
              {/foreach}
            </ul>
          {/foreach}
        {else}
          <ul class="pl-5 list-disc">
            {foreach from=$value item=item}
              <li>{$item|escape:'htmlall'}</li>
            {/foreach}
          </ul>
        {/if}
      {/if}
    {else}
      <div class="flex">
        <div class="rounded-full mr-2 w-1.5 h-1.5 mt-1.5 min-w-[6px] bg-gray-800 dark:bg-gray-300"></div>
        <div class="whitespace-pre-line overflow-hidden text-ellipsis">{$value|escape:'htmlall'}</div>
      </div>
    {/if}
  </div>
{/foreach}
