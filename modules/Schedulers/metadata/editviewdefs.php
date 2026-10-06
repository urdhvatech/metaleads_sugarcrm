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


$viewdefs['Schedulers']['EditView'] = [
    'templateMeta' => [
        'maxColumns' => '2',
        'widths' => [
            ['label' => '10', 'field' => '30'],
            ['label' => '10', 'field' => '30'],
        ],
        'includes' => [
            ['file' => 'modules/Schedulers/Schedulers.js'],
        ],
        'panelClass' => 'edit view edit508 schedulers-editview-panel',
        'form' => [
            'infoTpl' => 'modules/Schedulers/templates/SystemTimeSchedulerNote.tpl',
        ],
    ],

    'panels' => [
        'default' => [
            ['name', 'status'],
            ['job_function', 'job_url'],
            ['adv_interval'],
            [['name' => 'job_interval', 'label' => 'LBL_INTERVAL', 'customCode' => '
				<div id="job_interval_advanced">
				<script nonce="{sugar_nonce}">
					var adv_interval = {$adv_interval};
				</script>
				<table cellpadding="0" cellspacing="0">
					<tr>
						<td>{$MOD.LBL_MINS}</td>
						<td>{$MOD.LBL_HOURS}</td>
						<td>{$MOD.LBL_DAY_OF_MONTH}</td>
						<td>{$MOD.LBL_MONTHS}</td>
						<td>{$MOD.LBL_DAY_OF_WEEK}</td>
					</tr><tr>
						<td><input name="mins" maxlength="25" type="text" size="3" value="{$mins}"></td>
						<td><input name="hours" maxlength="25" type="text" size="3" value="{$hours}"></td>
						<td><input name="day_of_month" maxlength="25" type="text" size="3" value="{$day_of_month}"></td>
						<td><input name="months" maxlength="25" type="text" size="3" value="{$months}"></td>
						<td><input name="day_of_week" maxlength="25" type="text" size="3" value="{$day_of_week}"></td>
					</tr><tr>
						<td colspan="5">
							<em>{$MOD.LBL_CRONTAB_EXAMPLES}</em>
						</td>
					</tr>
				</table>
				</div>
				']],
            [['name' => 'job_interval', 'label' => 'LBL_INTERVAL', 'customCode' => '
				<div id="job_interval_basic">
				<table cellpadding="0" cellspacing="0" border="0">
					<tr>
						<td valign="top" width="25%">
							&nbsp;{$MOD.LBL_EVERY}&nbsp;
							<select name="basic_interval">{html_options options=$basic_intervals selected=$basic_interval}</select>&nbsp;
							<select name="basic_period">{html_options options=$basic_periods selected=$basic_period}</select>
						</td>
						<td valign="top" width="25%">
						<table cellpadding="0" cellspacing="0" border="0">
							<tr>
								<td><slot><input type="checkbox" name="all" value="true" id="all" {$ALL} data-onclick-{sugar_nonce}="allDays();">&nbsp;<i>{$MOD.LBL_ALL}</i></slot></td>
							</tr>
							<tr>
								<td><slot><input type="checkbox" name="mon" value="true" id="mon" {$MON}>&nbsp;{$MOD.LBL_MON}</slot></td>
							</tr>
							<tr>
								<td><slot><input type="checkbox" name="tue" value="true" id="tue"  {$TUE}>&nbsp;{$MOD.LBL_TUE}</slot></td>
							</tr>
							<tr>
								<td><slot><input type="checkbox" name="wed" value="true" id="wed"  {$WED}>&nbsp;{$MOD.LBL_WED}</slot></td>
							</tr>
						</table>
						</td>

						<td valign="top" width="25%">
						<table cellpadding="0" cellspacing="0" border="0">
							<tr>
								<td><slot><input type="checkbox" name="thu" value="true" id="thu"  {$THU}>&nbsp;{$MOD.LBL_THU}</slot></td>
							</tr>
							<tr>
								<td><slot><input type="checkbox" name="fri" value="true" id="fri"  {$FRI}>&nbsp;{$MOD.LBL_FRI}</slot></td>
							</tr>
							<tr>
								<td><slot><input type="checkbox" name="sat" value="true" id="sat"  {$SAT}>&nbsp;{$MOD.LBL_SAT}</slot></td>
							</tr>
							<tr>
								<td><slot><input type="checkbox" name="sun" value="true" id="sun"  {$SUN}>&nbsp;{$MOD.LBL_SUN}</slot></td>
							</tr>
						</table>
						</td>
					</tr>
				</table>
				</div>
				']],
            [['name' => 'job_run_order', 'label' => 'LBL_JOB_RUN_ORDER', 'customCode' => '
			    <div id="job_run_order_basic">
                    <select tabindex="0" name="job_run_order">
                        <option value="1" {if $selected_job_run_order==1}selected="selected"{/if}>1</option>
                        <option value="2" {if $selected_job_run_order==2}selected="selected"{/if}>2</option>
                        <option value="3" {if $selected_job_run_order==3}selected="selected"{/if}>3</option>
                        <option value="4" {if $selected_job_run_order==4}selected="selected"{/if}>4</option>
                        <option value="5" {if $selected_job_run_order==5}selected="selected"{/if}>5</option>
                        <option value="6" {if $selected_job_run_order==6}selected="selected"{/if}>6</option>
                        <option value="7" {if $selected_job_run_order==7}selected="selected"{/if}>7</option>
                        <option value="8" {if $selected_job_run_order==8}selected="selected"{/if}>8</option>
                        <option value="9" {if $selected_job_run_order==9}selected="selected"{/if}>9</option>
                        <option value="10" {if $selected_job_run_order==10}selected="selected"{/if}>10</option>
                    </select>
				</div>
			']],
        ],
        'lbl_adv_options' => [
            [['name' => 'catch_up', 'prefix' => '{sugar_help text=$MOD.LBL_CATCH_UP_WARNING}']],
            ['date_time_start', 'time_from'],
            ['date_time_end', 'time_to'],
        ],
    ],

];
