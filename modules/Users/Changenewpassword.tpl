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
<link rel="stylesheet" href="{sugar_getjspath file='styleguide/assets/css/build.tailwind.css'}">
{foreach from=$css_url item=url}
	<link rel="stylesheet" href="{sugar_getjspath file=$url}">
{/foreach}

<script type='text/javascript' nonce="{sugar_nonce}">
<!--
var ERR_RULES_NOT_MET = '{$MOD.ERR_RULES_NOT_MET}';
var ERR_ENTER_OLD_PASSWORD = '{$MOD.ERR_ENTER_OLD_PASSWORD}';
var ERR_ENTER_NEW_PASSWORD = '{$MOD.ERR_ENTER_NEW_PASSWORD}';
var ERR_ENTER_CONFIRMATION_PASSWORD = '{$MOD.ERR_ENTER_CONFIRMATION_PASSWORD}';
var ERR_REENTER_PASSWORDS = '{$MOD.ERR_REENTER_PASSWORDS}';
-->
</script>
<script type='text/javascript' src='{sugar_getjspath file="modules/Users/PasswordRequirementBox.js"}' nonce="{sugar_nonce}"></script>
<style type="text/css">
<!--
@import url({sugar_getjspath file='modules/Users/PasswordRequirementBox.css'});
.btn:hover {
	font-weight: normal;
	border: none;
}
div#content {
	position: relative;
	margin: 0;
	top: 0;
	left: 0;
	width: 100%;
}
.welcome {
	box-sizing: border-box;
	position: relative;
	min-width: 50%;
	padding-top: 15%;
}
.change-password-form {
	width: 320px;
	margin: 0 auto;
}

-->
</style>

<div class="sugar-light-theme">

	{if $MARKETING_EXTRAS_URL}
		<div class="marketing-extras">
			<div class="iframe-container"><iframe title="" id="marketing-content" scrolling="no" src="{$MARKETING_EXTRAS_URL}"></iframe></div>
		</div>
	{/if}

	<div class="h-screen bg-[--background-base]">
	<form action="index.php" method="post" name="ChangePasswordForm" id="ChangePasswordForm"
		data-onsubmit-{sugar_nonce}="return document.getElementById('cant_login').value == ''" autocomplete="off">
		{sugar_csrf_form_token}
		<input type="hidden" name="entryPoint" value="{$ENTRY_POINT}" />
		<input type='hidden' name='action' value="{$ACTION}" />
		<input type='hidden' name='module' value="{$MODULE}" />
		<input type="hidden" name="guid" value="{$GUID}" />
		<input type="hidden" name="return_module" value="Home" />
		<input type="hidden" name="login" value="1" />
		<input type="hidden" name="is_admin" value="{$IS_ADMIN}" />
		<input type="hidden" name="cant_login" id="cant_login" value="" />
		<input type="hidden" name="old_password" id="old_password" value="" />
		<input type="hidden" name="password_change" id="password_change" value="true" />
		<input type="hidden" value="" name="user_password" id="user_password" />
		<input type="hidden" name="page" value="Change" />
		<input type="hidden" name="return_id" value="{$ID}" />
		<input type="hidden" name="return_action" value="{$return_action}" />
		<input type="hidden" name="record" value="{$ID}" />
		<input type="hidden" name="user_name" value="{$USER_NAME}" />
		<input type='hidden' name='saveConfig' value='0' />

		<div class="welcome h-screen bg-[--background-base]">
		<div class="change-password-form">
			<div class="border border-[--border-color-light] rounded bg-[--foreground-base] py-5 px-8">
				<div class="pb-6">
					<h2 class="brand flex items-center justify-center m-0">
						<img src="{$COMPANY_LOGO_URL}" alt="SugarCRM" />
					</h2>
				</div>
				<div class="flex flex-col">
					<h3 class="m-0 font-semibold text-base pb-3">{$LBL_INSTRUCTION}</h3>
					<div class="pb-1 mb-6">
						<p class="text-sm ls-font-normal pb-3 m-0">{$LBL_PASSWORD_RULES_MANAGEMENT}:</p>
						{sugar_password_requirements_box class='mb-0 ls-font-normal'}
					</div>
					<div class="mb-4">
						<input type="text" name="user_name" id="user_name" size="26" class="min-w-[15rem]"
							placeholder="{$MOD.LBL_USER_NAME}" aria-label="{$MOD.LBL_USER_NAME}" tabindex="1" />
					</div>
					<div class="mb-4">
						<input type="password" name="new_password" id="new_password" size="26" class="min-w-[15rem]"
							value="" placeholder="{$MOD.LBL_NEW_PASSWORD}" aria-label="{$MOD.LBL_NEW_PASSWORD}"
							data-onkeyup-{sugar_nonce}="password_confirmation();newrules('{$PWDSETTINGS.minpwdlength}','{$PWDSETTINGS.maxpwdlength}','{$REGEX}');"
							tabindex="2" />
					</div>
					<div class="mb-4">
						<input type="password" name="confirm_pwd" id="confirm_pwd" size="26" class="min-w-[15rem]"
							value="" placeholder="{$MOD.LBL_NEW_PASSWORD2}" aria-label="{$MOD.LBL_NEW_PASSWORD2}"
							data-onkeyup-{sugar_nonce}="password_confirmation();" tabindex="2" />
						<div id="comfirm_pwd_match" class="error" style="display: none;">mis-match</div>
					</div>
				</div>
				<div class="flex flex-row w-full justify-end">
					{$SUBMIT_BUTTON}
				</div>
			</div>
		</div>
		</div>
	</form>
	</div>

</div>
