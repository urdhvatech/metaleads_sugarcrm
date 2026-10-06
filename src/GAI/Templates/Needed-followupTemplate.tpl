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
<div class="flex flex-col gap-3 neededFollowupContainer">
    {foreach from=$data key=key item=items}
        {if $items|is_array}
            {foreach from=$items item=item}
                {include file="src/GAI/Templates/Needed-followupItemTemplate.tpl" item=$item}
            {/foreach}
        {else}
            <div class="text-xs leading-4 font-normal text-gray-800 dark:text-gray-300">{$items|escape:'html'}</div>
        {/if}
    {/foreach}
</div>
