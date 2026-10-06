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
<div class="px-2.5 flex relative flex-col h-full summaryContainer">
  <div class="flex flex-col my-1">
    {if $summary|@is_array}
      {if $summary|isAssocArray}
        {foreach from=$summary key=key item=value}
          <div class="mb-4 flex flex-col">
            {if $key|@strlen > 0}
              <div class="font-bold">{$key|escape:'htmlall'}</div>
            {/if}

            {if $value|@is_array}
              {if $value|isAssocArray}
                {foreach from=$value key=subKey item=subValue}
                  {assign var='fragment' value=[{$subKey} => $subValue]}
                  {include file='SummaryFragmentTemplate.tpl' fragment=$fragment}
                {/foreach}
              {else}
                {if $value|containsObjects}
                  {foreach from=$value item=item}
                    <ul class="pl-5 list-disc">
                      {foreach from=$item key=objKey item=objValue}
                        <li><span class="font-bold">{$objKey|escape:'htmlall'}: </span>{$objValue|escape:'htmlall'}</li>
                      {/foreach}
                    </ul>
                  {/foreach}
                {else}
                  <ul>
                    {foreach from=$value item=item}
                      <li class="flex flex-row">
                        <div class="rounded-md w-1.5 h-1.5 mt-1.5 min-w-[6px] bg-gray-800 dark:bg-gray-300"></div>
                        <div class="pl-2 whitespace-pre-line overflow-hidden text-ellipsis">
                          {$item|escape:'htmlall'}
                        </div>
                      </li>
                    {/foreach}
                  </ul>
                {/if}
              {/if}
            {else}
              <div class="px-2.5 flex flex-row gai-flex-flex-row-inherit">
                <div class="rounded-full mr-2 w-1.5 h-1.5 mt-1.5 min-w-[6px] bg-gray-800 dark:bg-gray-300"></div>
                <div class="whitespace-pre-line overflow-hidden text-ellipsis">{$value|escape:'htmlall'}</div>
              </div>
            {/if}
          </div>
        {/foreach}
      {else}
        <ul>
          {foreach from=$summary item=item}
            <li class="flex flex-row">
              <div class="rounded-md w-1.5 h-1.5 mt-1.5 min-w-[6px] bg-gray-800 dark:bg-gray-300"></div>
              <div class="pl-2 whitespace-pre-line overflow-hidden text-ellipsis">
                {$item|escape:'htmlall'}
              </div>
            </li>
          {/foreach}
        </ul>
      {/if}
    {else}
      <div class="px-2.5 flex flex-row gai-flex-flex-row-inherit">
        <div class="rounded-full mr-2 w-1.5 h-1.5 mt-1.5 min-w-[6px] bg-gray-800 dark:bg-gray-300"></div>
        <div class="whitespace-pre-line overflow-hidden text-ellipsis">{$summary|escape:'htmlall'}</div>
      </div>
    {/if}
  </div>
</div>
