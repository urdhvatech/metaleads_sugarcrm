{*
 * This file is part of the "SuiteAI Insight" package.
 *
 * @package SuiteAIInsight
 * @author Urdhva Tech <contact@urdhva-tech.com>
 * @link https://www.urdhva-tech.com
 * @copyright Urdhva Tech
 * @license As specified in the License Agreement supplied with this package.
*}

<!-- BEGIN: main -->
{literal}
<script type="text/javascript">

    // Only do anything if jQuery isn't defined
    if (typeof(jQuery) == 'undefined') {

        if (typeof($) == 'function') {
            // warning, global var
            thisPageUsingOtherJSLibrary = true;
        }

        function getScript(url, success) {
            var script     = document.createElement('script');
            script.src = url;

            var head = document.getElementsByTagName('head')[0],
            done = false;

            // Attach handlers for all browsers
            script.onload = script.onreadystatechange = function() {
                if (!done && (!this.readyState || this.readyState == 'loaded' || this.readyState == 'complete')) {

                    done = true;

                    // callback function provided as param
                    success();

                    script.onload = script.onreadystatechange = null;
                    head.removeChild(script);

                };
            };

            head.appendChild(script);
        };

        getScript('{/literal}{$file_path}{literal}', function() {

            if (typeof jQuery=='undefined') {
                // Super failsafe - still somehow failed...
            } else {
                if (thisPageUsingOtherJSLibrary) {
                    // Run your jQuery Code
                } else {
                    // Use .noConflict(), then run your jQuery Code
                }

            }

        });

    }

    function outfitters_validate_license()
    {
        var curl_not_enabled = '{/literal}{$CURL_NOT_ENABLED}{literal}';
        if(curl_not_enabled) {
{/literal}{if $IS_SUGAR_6 == true}{literal}
            alert(curl_not_enabled);
{/literal}{else}{literal}
            var app = window.parent.SUGAR.App;
            app.alert.show('License_error', {
                level: 'error',
                title: app.lang.get('ERR_LICENSE_CONNECTION', 'AddonBoilerplate'),
                messages: curl_not_enabled,
                autoClose: false
            });
{/literal}{/if}{literal}

            return;
        }
        var licKey = $('#outfitters_license_key').val();
        if(!licKey) {
            return false;
        }
        $('#outfitters_licensed_users').html('');
        $('#btn-outfitters-validate-license').hide();
        $('#outfitters_validation_success').hide();
        $('#outfitters_validation_fail').hide();
        $('#outfitters_license_passed').hide();
        $('#outfitters_license_increase').hide();
        $('#outfitters_validating_license').show();

        $.ajax('index.php?module={/literal}{$MODULE}{literal}&action=outfitterscontroller&to_pdf=1',{
            type: 'POST',
            dataType: 'json',
            data: {
                method: 'validate',
                key: licKey
            },
            success: function(response){
                //hide loading
                $('#outfitters_validating_license').hide();
                $('#btn-outfitters-validate-license').show();
                if (response){
                    if(response.validated == true) {
                        $('#outfitters_validation_success').show();
{/literal}
{if $validate_users == true && $manage_licensed_users == true}
                        $('.validation-required').hide();
                        $('.manage-users').show();
{/if}
{literal}
                    } else {
                        $('#outfitters_fail_message').html('Invalid key');
                        $('#outfitters_validation_fail').show();
                    }
                    if(response.licensed_user_count != undefined) {
                        $('#outfitters_licensed_users').html(response.licensed_user_count);
                        $('#licensed_users').val(response.licensed_user_count);
                        outfitters_recalculate_users();

                        if(response.validated_users == true) {
                            $('#outfitters_license_passed').show();
                        } else {
                            $('#btn-outfitters-increase').html('Increase to {/literal}{$current_users}{literal} users');
                            $('#outfitters_license_increase').show();
                        }
                    } else {
                        $('#outfitters_licensed_users').html('0');
                        $('#licensed_users').val(0);
                    }
                } else {
                    alert('Unexpected data returned from the server.');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $('#outfitters_validating_license').hide();
                $('#btn-outfitters-validate-license').show();
                $('#outfitters_fail_message').html($.parseJSON(jqXHR.responseText));
                $('#outfitters_validation_fail').show();

                alert('Error: '+$.parseJSON(jqXHR.responseText));
            }
        });

        return false;
    }

    function outfitters_increase_license(increase_to)
    {
        var curl_not_enabled = '{/literal}{$CURL_NOT_ENABLED}{literal}';
        if(curl_not_enabled) {
{/literal}{if $IS_SUGAR_6 == true}{literal}
            alert(curl_not_enabled);
{/literal}{else}{literal}
            var app = window.parent.SUGAR.App;
            app.alert.show('License_error', {
                level: 'error',
                title: app.lang.get('ERR_LICENSE_CONNECTION', 'AddonBoilerplate'),
                messages: curl_not_enabled,
                autoClose: false
            });
{/literal}{/if}{literal}

            return;
        }
        var licKey = $('#outfitters_license_key').val();
        if(!licKey) {
            return false;
        }

        $('#outfitters_increasing_license').show();

        $.ajax('index.php?module={/literal}{$MODULE}{literal}&action=outfitterscontroller&to_pdf=1',{
            type: 'POST',
            dataType: 'json',
            data: {
                method: 'change',
                key: licKey,
                user_count: increase_to
            },
            success: function(response){
                //hide loading
                $('#outfitters_increasing_license').hide();

                $('#btn-outfitters-validate-license').show();

                if (response.licensed_user_count){
                    $('#outfitters_license_increase').hide();
                    $('#outfitters_licensed_users').html(response.licensed_user_count);
                    $('#outfitters_license_passed').show();
                    $('#licensed_users').val(response.licensed_user_count);
                    outfitters_recalculate_users();
                } else {
                    alert('Unexpected data returned from the server.');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $('#outfitters_increasing_license').hide();

                alert('Error: '+$.parseJSON(jqXHR.responseText));
            }
        });

        return false;
    }

    function outfitters_recalculate_users()
    {
{/literal}{if $validate_users == true && $manage_licensed_users == true}{literal}
        var lic_users = parseInt($('#licensed_users').val(),10);

        var avail_users = $('select[name="licensed_users[]"] option').length;

        avail_users = lic_users - avail_users;
        $('#available_users').val(avail_users);
        $('#outfitters_available_licensed_users').html(avail_users);

        if(avail_users <= 0)
        {
            $('#outfitters_license_increase').show();
        }
{/literal}{/if}{literal}
    }

    $(document).ready(function(){
        $('#chooser_unlicensed_users_left_arrow').parent().css('vertical-align','middle');

        $('#outfitters_additional_license_form').submit(function() { return false; });
    });


{/literal}{if $validate_users == true && $manage_licensed_users == true}{literal}
    SUGAR.tabChooser.movementCallback = function(left_side, right_side) {
        outfitters_recalculate_users();
    };

    function outfitters_save_additional_users()
    {
        var lic_users = parseInt($('#licensed_users').val(),10);
        var addt_users = parseInt($('#outfitters_additional_licenses').val(),10);
        if(isNaN(addt_users))
        {
            $('#outfitters_additional_licenses').val(0);
            return false;
        }

        outfitters_increase_license(lic_users + addt_users);
    }

    function outfitters_save_licensed_users()
    {
        $('#outfitters_save_licensed_users_fail').hide();
        $('#btn-outfitters-licensed-users').hide();
        $('#outfitters_save_licensed_users_success').hide();
        $('#outfitters_save_licensed_users').show();

        var lic_users = parseInt($('#licensed_users').val(),10);
        var avail_users = $('select[name="licensed_users[]"] option').length;

        avail_users = lic_users - avail_users;
        $('#available_users').val(avail_users);
        $('#outfitters_available_licensed_users').html(avail_users);

        if(avail_users < 0)
        {
            $('#outfitters_save_licensed_users').hide();
            $('#btn-outfitters-licensed-users').show();
            $('#outfitters_save_licensed_users_fail').show();
            $('#outfitters_save_licensed_users_fail_message').html('{/literal}{$LICENSE.LBL_ERROR_TOO_MANY_USERS}{literal}');
            return false;
        }

        var licensed_users = [];
        $('select[name="licensed_users[]"] option').each(function() {
            licensed_users.push($(this).val());
        });

        $.ajax('index.php?module={/literal}{$MODULE}{literal}&action=outfitterscontroller&to_pdf=1',{
            type: 'POST',
            dataType: 'json',
            data: {
                method: 'add',
                licensed_users: licensed_users
            },
            success: function(response){
                //hide loading
                $('#outfitters_save_licensed_users').hide();

                $('#btn-outfitters-licensed-users').show();
                $('#outfitters_save_licensed_users_success').show();
            },
            error: function(jqXHR, textStatus, errorThrown) {
                $('#outfitters_save_licensed_users').hide();
                $('#btn-outfitters-licensed-users').show();

                alert('Error: '+$.parseJSON(jqXHR.responseText));
            }
        });

        return false;
    }

{/literal}{/if}{literal}

    function outfitters_continue_url()
    {
{/literal}{if $IS_SUGAR_6 == true}{literal}
        window.location='{/literal}{$continue_url}{literal}';
{/literal}{else}{literal}
        var app = window.parent.SUGAR.App;
        app.router.navigate('{/literal}{$continue_url}{literal}', {trigger:true, replace:true});
{/literal}{/if}{literal}
    }
</script>
<style type="text/css">
    /* Modern License Page Styling */
    .license-container {
        max-width: 1200px;
        margin: 20px auto;
        padding: 0 20px;
    }
    
    .license-section {
        background: #ffffff;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        margin-bottom: 25px;
        overflow: hidden;
    }
    
    .license-brand-bar {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 14px 20px;
        background: #ffffff;
        border-bottom: 1px solid #e2e8f0;
        margin-bottom: 5px;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }
    .license-brand-company-logo {
        height: 32px;
        width: auto;
        object-fit: contain;
        flex-shrink: 0;
    }
    .license-brand-divider {
        width: 1px;
        height: 28px;
        background: #cbd5e1;
        flex-shrink: 0;
    }
    .license-brand-product-logo {
        width: 26px;
        height: 26px;
        object-fit: contain;
        flex-shrink: 0;
    }
    .license-brand-product-name {
        color: #1e293b;
        font-size: 16px;
        font-weight: 600;
        letter-spacing: 0.01em;
        white-space: nowrap;
    }

    .license-section h4 {
        background: linear-gradient(135deg, #4E8CCF 0%, #3a6ba8 100%);
        color: #ffffff;
        margin: 0;
        padding: 18px 25px;
        font-size: 1.3em;
        font-weight: 600;
        border-bottom: 3px solid #2d5a8a;
    }
    
    .license-section .edit.view {
        border: none;
        margin: 0;
    }
    
    .license-section .edit.view td,
    .license-section .edit.view th {
        padding: 15px 25px;
        border-bottom: 1px solid #e8e8e8;
    }
    
    .license-section .edit.view tr:last-child td {
        border-bottom: none;
    }
    
    .outfitters_license_key {
        background-color: #f8f9fa;
        border: 2px solid #4E8CCF;
        border-radius: 6px;
        text-align: left;
        padding: 12px 20px;
        font-size: 1.1em;
        margin-right: 12px;
        width: 400px;
        max-width: 100%;
        transition: all 0.3s ease;
        font-family: 'Courier New', monospace;
        letter-spacing: 1px;
    }
    
    .outfitters_license_key:focus {
        outline: none;
        border-color: #2d5a8a;
        box-shadow: 0 0 0 3px rgba(78, 140, 207, 0.1);
        background-color: #ffffff;
    }
    
    .button.primary {
        background: linear-gradient(135deg, #4E8CCF 0%, #3a6ba8 100%);
        border: none;
        border-radius: 6px;
        color: #ffffff;
        padding: 12px 24px;
        font-size: 1em;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(78, 140, 207, 0.3);
    }
    
    .button.primary:hover {
        background: linear-gradient(135deg, #3a6ba8 0%, #2d5a8a 100%);
        box-shadow: 0 4px 8px rgba(78, 140, 207, 0.4);
        transform: translateY(-1px);
    }
    
    .button.primary:active {
        transform: translateY(0);
        box-shadow: 0 2px 4px rgba(78, 140, 207, 0.3);
    }
    
    .btn-outfitters-big {
        margin-top: 12px;
        padding: 12px 28px !important;
        font-size: 1.05em;
    }
    
    /* Status Messages */
    .status-message {
        display: inline-flex;
        align-items: center;
        padding: 12px 18px;
        border-radius: 6px;
        margin-top: 12px;
        font-weight: 500;
        gap: 10px;
    }
    
    #outfitters_validation_success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    #outfitters_validation_fail {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    #outfitters_license_passed {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
        padding: 12px 18px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }
    
    #outfitters_license_increase {
        background: #fff3cd;
        color: #856404;
        border: 1px solid #ffeaa7;
        padding: 15px 20px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 1.05em;
        margin-top: 12px;
        display: inline-block;
    }
    
    #outfitters_validating_license,
    #outfitters_increasing_license,
    #outfitters_save_licensed_users {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 12px 18px;
        color: #4E8CCF;
        font-weight: 500;
    }
    
    .outfitters-success {
        margin: 0 15px;
    }
    
    /* Info Boxes */
    .info-box {
        background: #e7f3ff;
        border-left: 4px solid #4E8CCF;
        padding: 15px 20px;
        margin: 15px 0;
        border-radius: 4px;
        line-height: 1.6;
    }
    
    .info-box em {
        color: #2d5a8a;
        font-style: normal;
    }
    
    /* User Management */
    .manage-users select {
        width: 100% !important;
        max-width: 280px;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 0.95em;
    }
    
    #chooser_unlicensed_users_up_arrow,
    #chooser_licensed_users_down_arrow {
        display: none;
    }
    
    #chooser_unlicensed_users_left_arrow,
    #chooser_unlicensed_users_left_to_right {
        display: block;
        cursor: pointer;
        padding: 10px;
        margin-bottom: 8px;
        transition: transform 0.2s ease;
    }
    
    #chooser_unlicensed_users_left_arrow:hover,
    #chooser_unlicensed_users_left_to_right:hover {
        transform: scale(1.1);
    }
    
    #outfitters_additional_licenses {
        text-align: center;
        padding: 10px;
        border: 2px solid #4E8CCF;
        border-radius: 6px;
        font-size: 1.1em;
        width: 100px;
        margin-right: 10px;
    }
    
    /* Stats Display */
    .stat-value {
        font-size: 1.4em;
        font-weight: 700;
        color: #4E8CCF;
        margin-top: 5px;
    }
    
    .stat-label {
        color: #666;
        font-size: 0.95em;
        margin-bottom: 5px;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .outfitters_license_key {
            width: 100%;
            margin-bottom: 12px;
        }
        
        .button.primary {
            width: 100%;
            margin-top: 8px;
        }
        
        .license-container {
            padding: 0 10px;
        }
    }
    
    /* Loading Animation */
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    #outfitters_validating_license img,
    #outfitters_increasing_license img,
    #outfitters_save_licensed_users img {
        animation: spin 1s linear infinite;
    }
</style>
{/literal}

<div class="license-container">



<form name="outfitters_license_form" id="outfitters_license_form" method="POST">
    <input type="hidden" name="module" value="{$MODULE}">
    <input type="hidden" name="action">
    <input type="hidden" name="return_module" value="{$RETURN_MODULE}">
    <input type="hidden" name="return_action" value="{$RETURN_ACTION}">
    <input type="hidden" name="licensed_users" id="licensed_users" value="{$licensed_users}">
    <input type="hidden" name="available_users" id="available_users" value="{$available_licensed_users}">

<div class="license-section">
<div class="license-brand-bar">
    <img src="modules/ut_sm/images/urdhvatech-logo.png" alt="Urdhva Tech" class="license-brand-company-logo" />
    <div class="license-brand-divider"></div>
    
    <span class="license-brand-product-name">{$LICENSE.LBL_METALEADS_LICENSE_TITLE}</span>
</div>
    <table width="100%" border="0" cellspacing="0" cellpadding="0" class="edit view">
        <tr>
            <td align="left" scope="row" colspan="4" style="padding: 20px 25px;">
                <div class="info-box">
                    {$LICENSE.LBL_STEPS_TO_LOCATE_KEY}
                </div>
            </td>
        </tr>
        <tr>
            <td width="20%" scope="row" style="vertical-align: middle; font-weight: 600;">{$LICENSE.LBL_LICENSE_KEY}</td>
            <td width="80%" colspan="3" scope="row" style="padding: 20px 25px;">
                <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 12px;">
                    <input id='outfitters_license_key' name='outfitters_license_key' class='outfitters_license_key' tabindex='1' size='50' maxlength='100' type="text" value="{$license_key}" placeholder="Enter your license key">
                    <input title="{$LICENSE.LBL_VALIDATE_LABEL}" class="button primary" onclick="return outfitters_validate_license();" type="submit" name="button" id="btn-outfitters-validate-license" value="{$LICENSE.LBL_VALIDATE_LABEL}" style="padding:0 20px 0 20px !important">
                </div>
                <div style="margin-top: 15px;">
                    <span id="outfitters_validating_license" style="display: none" class="status-message"><img src="themes/default/images/img_loading.gif" alt="Loading"> Validating license...</span>
                    <span id="outfitters_validation_fail" style="display: none" class="status-message"><img src="themes/default/images/no.gif" alt="Failed"> <strong>Validation Failed:</strong> <span id="outfitters_fail_message"></span></span>
                    <span id="outfitters_validation_success" style="display: none" class="status-message">
                        <img src="themes/default/images/yes.gif" alt="Success"> <strong>License validated successfully!</strong>
                        {if $validate_users == false && !empty($continue_url)}
                            <br/><br/>
                            <input title="Continue" class="button primary" onclick="javascript:outfitters_continue_url();" type="button" name="button" value=" Continue " style="padding:0 20px 0 20px !important">
                        {/if}
                    </span>
                </div>
            </td>
        </tr>
    </table>
</div>
</form>

{if $validate_users == true}

    {if $manage_licensed_users == false}
        <div class="license-section">
            <h4>License Information</h4>
            <table width="100%" border="0" cellspacing="0" cellpadding="0" class="edit view">
                <tr>
                    <td width="25%" scope="row" style="font-weight: 600;">{$LICENSE.LBL_CURRENT_USERS}</td>
                    <td width="75%" scope="row">
                        <div class="stat-value" id="outfitters_current_users">{$current_users}</div>
                    </td>
                </tr>
                <tr>
                    <td width="25%" scope="row" style="font-weight: 600;">{$LICENSE.LBL_LICENSED_USERS}</td>
                    <td width="75%" scope="row" style="padding: 20px 25px;">
                        <div class="stat-value" id="outfitters_licensed_users" style="margin-bottom: 15px;"></div>
                        <span id="outfitters_increasing_license" style="display: none" class="status-message"><img src="themes/default/images/img_loading.gif" alt="Loading"> Updating license...</span>
                        <div id="outfitters_license_increase" style="display: none">
                            <div style="margin-bottom: 12px;">
                                <img src="themes/default/images/no.gif" alt="Warning"> <strong>License Upgrade Required</strong>
                            </div>
                            <button id="btn-outfitters-increase" class="button primary" onclick="javascript:outfitters_increase_license({$current_users}); return false;">Upgrade License</button>
                        </div>
                        <div id="outfitters_license_passed" style="display: none">
                            <div style="margin-bottom: 12px;">
                                <img src="themes/default/images/yes.gif" alt="Passed"> <strong>License Verified</strong>
                            </div>
                            {if !empty($continue_url)}
                                <input title="Continue" class="button primary" onclick="javascript:outfitters_continue_url();" type="button" name="button" value=" Continue ">
                            {/if}
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    {else}
        {if $validation_required == true}
            <div class="license-section validation-required">
                <h4>{$LICENSE.LBL_MANAGE_USERS_TITLE}</h4>
                <table width="100%" border="0" cellspacing="0" cellpadding="0" class="edit view">
                    <tr>
                        <td colspan="4" scope="row" style="padding: 25px;">
                            <div class="info-box">
                                <strong>{$LICENSE.LBL_VALIDATION_REQUIRED}</strong>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        {/if}
        <form name="outfitters_additional_license_form" id="outfitters_additional_license_form" method="POST">
            <input type="hidden" name="method" value="add"/>
            <div class="license-section manage-users" {if $validation_required == true}style="display: none"{/if}>
                <h4>{$LICENSE.LBL_MANAGE_USERS_TITLE}</h4>
                <table width="100%" border="0" cellspacing="0" cellpadding="0" class="edit view">
                    <tr>
                        <td width="25%" scope="row" style="font-weight: 600;">{$LICENSE.LBL_LICENSED_USERS}</td>
                        <td width="75%" scope="row">
                            <div class="stat-value" id="outfitters_licensed_users">{$licensed_users}</div>
                        </td>
                    </tr>
                    <tr>
                        <td width="25%" scope="row" style="font-weight: 600;">{$LICENSE.LBL_AVAILABLE_USERS}</td>
                        <td width="75%" scope="row">
                            <div class="stat-value" id="outfitters_available_licensed_users">{$available_licensed_users}</div>
                        </td>
                    </tr>
                    <tr id="outfitters_show_additional_licenses">
                        <td width="25%" scope="row" style="font-weight: 600;">{$LICENSE.LBL_HOW_MANY_USERS}</td>
                        <td width="75%" scope="row" style="padding: 20px 25px;">
                            <div id="outfitters_additional_license_increase" style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                                <input type="text" name="outfitters_additional_licenses" id="outfitters_additional_licenses" size="6" value="5" style="text-align: center; padding: 10px; border: 2px solid #4E8CCF; border-radius: 6px; font-size: 1.1em; width: 100px;"/>
                                <input title="{$LICENSE.LBL_ADD_USERS_BUTTON_LABEL}" class="button primary" onclick="return outfitters_save_additional_users();" type="button" name="button" id="btn-outfitters-additional-users" value="{$LICENSE.LBL_ADD_USERS_BUTTON_LABEL}">
                            </div>
                            <span id="outfitters_increasing_license" style="display: none" class="status-message"><img src="themes/default/images/img_loading.gif" alt="Loading"> Updating license...</span>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" scope="row" style="padding: 25px;">
                            <div style="background: #f8f9fa; padding: 20px; border-radius: 6px; border: 1px solid #e8e8e8;">
                                {$USER_CHOOSER}
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td scope="row" colspan="2" style="padding: 25px;">
                            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 15px;">
                                <input title="{$APP.LBL_SAVE_BUTTON_LABEL}" class="button primary btn-outfitters-big" onclick="return outfitters_save_licensed_users();" type="button" name="button" id="btn-outfitters-licensed-users" value="{$APP.LBL_SAVE_BUTTON_LABEL}">
                                <span id="outfitters_save_licensed_users" style="display: none" class="status-message"><img src="themes/default/images/img_loading.gif" alt="Loading"> Saving users...</span>
                                <span id="outfitters_save_licensed_users_fail" style="display: none" class="status-message"><img src="themes/default/images/no.gif" alt="Failed"> <strong>Failed:</strong> <span id="outfitters_save_licensed_users_fail_message"></span></span>
                                <span id="outfitters_save_licensed_users_success" style="display: none">
                                    <span class="status-message" style="background: #d4edda; color: #155724; border: 1px solid #c3e6cb;">
                                        <img src="themes/default/images/yes.gif" alt="Success"> <strong>Users saved successfully!</strong>
                                    </span>
                                    {if !empty($continue_url)}
                                        <input title="Continue" class="button primary btn-outfitters-big" onclick="javascript:outfitters_continue_url();" type="button" name="button" value=" Continue ">
                                    {/if}
                                </span>
                            </div>
                            <div class="info-box" style="margin-top: 20px;">
                                <em>{$LICENSE.LBL_HOWTO_REDUCE_LICENSE}</em>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        </form>
    {/if}
{/if}
</div>
