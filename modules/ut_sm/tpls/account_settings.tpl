{literal}
<style>
.ut-sm-oauth-settings {
    padding: 20px 20px 0;
    max-width: 900px;
    margin: 0 auto;
    background-color: #fff;
}
.ut-sm-oauth-settings h2 {
    color: #2c3e50;
    border-bottom: 2px solid #3498db;
    padding-bottom: 10px;
    margin-bottom: 20px;
}
.ut-sm-oauth-settings .section {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    padding: 20px;
    margin-bottom: 20px;
}
.ut-sm-oauth-settings .section h3 {
    color: #495057;
    margin-top: 0;
    margin-bottom: 15px;
}
.ut-sm-oauth-settings .form-group {
    margin-bottom: 15px;
}
.ut-sm-oauth-settings label {
    display: block;
    font-weight: bold;
    margin-bottom: 5px;
    color: #495057;
}
.ut-sm-oauth-settings select,
.ut-sm-oauth-settings input[type="text"] {
    width: 100%;
    max-width: 420px;
    padding: 0px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 14px;
}
.ut-sm-oauth-settings .help-text {
    font-size: 12px;
    color: #6c757d;
    margin-top: 5px;
    margin-bottom: 10px;
}
.ut-sm-oauth-settings .status-box {
    padding: 15px;
    border-radius: 4px;
    margin-bottom: 15px;
}
.ut-sm-oauth-settings .status-box.success {
    background-color: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}
.ut-sm-oauth-settings .status-box.error {
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}
.ut-sm-oauth-settings .status-box.info {
    background-color: #d1ecf1;
    border: 1px solid #bee5eb;
    color: #0c5460;
}
.ut-sm-oauth-settings .btn {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    text-decoration: none;
    display: inline-block;
    margin-right: 10px;
}
.ut-sm-oauth-settings .btn-primary {
    background-color: #007bff;
    color: #fff;
}
.ut-sm-oauth-settings .btn-secondary {
    background-color: #6c757d;
    color: #fff;
}
.ut-sm-oauth-settings .checkbox-list {
    max-height: 240px;
    overflow-y: auto;
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 10px 14px;
}
.ut-sm-oauth-settings .checkbox-list label {
    font-weight: normal;
    margin-bottom: 8px;
    display: block;
}
.ut-sm-oauth-settings .checkbox-list-users {
    max-height: 280px;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 6px 16px;
    padding: 12px 14px;
    align-content: start;
}
.ut-sm-oauth-settings .checkbox-list-users label {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    margin: 0;
    padding: 4px 2px;
    line-height: 1.35;
    font-size: 13px;
    color: #343a40;
    word-break: break-word;
}
.ut-sm-oauth-settings .checkbox-list-users input[type="checkbox"] {
    flex: 0 0 auto;
    margin: 2px 0 0;
}
.ut-sm-oauth-settings .meta-line {
    color: #6c757d;
    font-size: 13px;
    margin-bottom: 15px;
}
.ut-sm-oauth-settings .ut-sm-icon {
    display: inline-block;
    vertical-align: middle;
    margin-right: 6px;
    line-height: 1;
    font-size: 14px;
}
.ut-sm-oauth-settings .btn .ut-sm-icon {
    margin-right: 5px;
}
.ut-sm-oauth-settings h3 .ut-sm-icon {
    color: #3498db;
}
.ut-sm-oauth-settings .type-pill {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    background: #eef2f7;
    color: #334155;
}
.ut-sm-oauth-settings .type-pill.instagram {
    background: #fce7f3;
    color: #9d174d;
}
.ut-sm-oauth-settings .type-pill.facebook {
    background: #e0e7ff;
    color: #3730a3;
}
.ut-sm-oauth-settings .mapping-form-block {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    padding: 14px 16px;
    margin-bottom: 14px;
}
.ut-sm-oauth-settings .mapping-form-block h4 {
    margin: 0 0 12px;
    color: #334155;
    font-size: 15px;
}
.ut-sm-oauth-settings .mapping-table {
    width: 100%;
    border-collapse: collapse;
}
.ut-sm-oauth-settings .mapping-table th,
.ut-sm-oauth-settings .mapping-table td {
    text-align: left;
    padding: 8px 10px;
    border-bottom: 1px solid #e9ecef;
    vertical-align: middle;
}
.ut-sm-oauth-settings .mapping-table th {
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: #6c757d;
    background: #f1f3f5;
}
.ut-sm-oauth-settings .mapping-table select {
    width: 100%;
    max-width: 100%;
}
.ut-sm-oauth-settings .mapping-meta-label {
    font-weight: 600;
    color: #343a40;
}
.ut-sm-oauth-settings .mapping-warnings {
    margin-top: 10px;
}
.ut-sm-oauth-settings .mapping-warnings .status-box {
    margin-bottom: 8px;
    padding: 10px 12px;
}
</style>
<script type="text/javascript">
function utSmToggleAssignmentPanels() {
    var typeEl = document.getElementById('assignment_type');
    if (!typeEl) { return; }
    var type = typeEl.value;
    var panels = {
        round_robin: document.getElementById('panel_round_robin'),
        specific_user: document.getElementById('panel_specific_user'),
        security_group: document.getElementById('panel_security_group')
    };
    for (var key in panels) {
        if (panels[key]) {
            panels[key].style.display = (type === key) ? 'block' : 'none';
        }
    }
}
</script>
{/literal}

<div class="ut-sm-oauth-settings">
    <h2 style="font-size:18px;margin-top:0;">
        <span class="suitepicon suitepicon-action-edit ut-sm-icon" aria-hidden="true"></span>{$MOD.LBL_UT_SM_LEAD_SETTINGS_FOR|escape:'html'} {$ACCOUNT_NAME|escape:'html'}
    </h2>
    <div class="meta-line">
        {if $IS_INSTAGRAM}
            <span class="type-pill instagram">{$ACCOUNT_TYPE_LABEL|escape:'html'}</span>
        {else}
            <span class="type-pill facebook">{$ACCOUNT_TYPE_LABEL|escape:'html'}</span>
        {/if}
    </div>

    {if $HAS_ERROR}
    <div class="status-box error">
        <strong>{$MOD.LBL_UT_SM_ERROR|escape:'html'}:</strong> {$ERROR_MESSAGE|escape:'html'}
    </div>
    {/if}

    {if $HAS_SUCCESS}
    <div class="status-box success">
        <strong>{$MOD.LBL_UT_SM_SUCCESS|escape:'html'}:</strong> {$SUCCESS_MESSAGE|escape:'html'}
    </div>
    {/if}

    <form method="POST" action="index.php?module=ut_sm&action=account_settings&record={$RECORD|escape:'url'}">
        <input type="hidden" name="save_account_config" value="1">
        <input type="hidden" name="ut_sm_account_form_token" value="{$FORM_TOKEN|escape:'html'}">

        <div class="section">
            <h3><span class="suitepicon suitepicon-action-user ut-sm-icon" aria-hidden="true"></span>{$MOD.LBL_UT_SM_LEAD_ASSIGNMENT|escape:'html'}</h3>
            <div class="form-group">
                <label for="assignment_type">{$MOD.LBL_UT_SM_ASSIGN_NEW_LEADS_TO|escape:'html'}</label>
                <select id="assignment_type" name="assignment_type" onchange="utSmToggleAssignmentPanels();">
                    <option value="keep_empty" {if $IS_KEEP_EMPTY}selected{/if}>{$MOD.LBL_UT_SM_ASSIGN_KEEP_EMPTY|escape:'html'}</option>
                    <option value="round_robin" {if $IS_ROUND_ROBIN}selected{/if}>{$MOD.LBL_UT_SM_ASSIGN_ROUND_ROBIN|escape:'html'}</option>
                    <option value="specific_user" {if $IS_SPECIFIC_USER}selected{/if}>{$MOD.LBL_UT_SM_ASSIGN_SPECIFIC_USER|escape:'html'}</option>
                    <option value="security_group" {if $IS_SECURITY_GROUP}selected{/if}>{$MOD.LBL_UT_SM_ASSIGN_SECURITY_GROUP|escape:'html'}</option>
                </select>
            </div>

            <div id="panel_round_robin" class="form-group" style="{if !$IS_ROUND_ROBIN}display:none;{/if}">
                <label>{$MOD.LBL_UT_SM_RR_USERS|escape:'html'}</label>
                <div class="help-text">{$MOD.LBL_UT_SM_RR_USERS_HELP|escape:'html'}</div>
                {if $HAS_USERS}
                <div class="checkbox-list checkbox-list-users">
                    {foreach from=$USER_OPTIONS item=user}
                    <label title="{$user.name|escape:'html'}">
                        <input type="checkbox" name="rr_user_ids[]" value="{$user.id|escape:'html'}" {if $user.rr_checked}checked{/if}>
                        <span>{$user.name|escape:'html'}</span>
                    </label>
                    {/foreach}
                </div>
                {else}
                <div class="status-box info">{$MOD.LBL_UT_SM_NO_USERS|escape:'html'}</div>
                {/if}
            </div>

            <div id="panel_specific_user" class="form-group" style="{if !$IS_SPECIFIC_USER}display:none;{/if}">
                <label for="assignment_user_id">{$MOD.LBL_UT_SM_USER|escape:'html'}</label>
                <select id="assignment_user_id" name="assignment_user_id">
                    <option value="">{$MOD.LBL_UT_SM_SELECT_USER|escape:'html'}</option>
                    {foreach from=$USER_OPTIONS item=user}
                    <option value="{$user.id|escape:'html'}" {if $user.selected}selected{/if}>{$user.name|escape:'html'}</option>
                    {/foreach}
                </select>
            </div>

            <div id="panel_security_group" class="form-group" style="{if !$IS_SECURITY_GROUP}display:none;{/if}">
                <label for="assignment_group_id">{$MOD.LBL_UT_SM_SECURITY_GROUP|escape:'html'}</label>
                {if $HAS_SECURITY_GROUPS}
                <select id="assignment_group_id" name="assignment_group_id">
                    <option value="">{$MOD.LBL_UT_SM_SELECT_GROUP|escape:'html'}</option>
                    {foreach from=$SECURITY_GROUPS item=group}
                    <option value="{$group.id|escape:'html'}" {if $group.selected}selected{/if}>{$group.name|escape:'html'}</option>
                    {/foreach}
                </select>
                <div class="help-text">{$MOD.LBL_UT_SM_SECURITY_GROUP_HELP|escape:'html'}</div>
                {else}
                <div class="status-box info">{$MOD.LBL_UT_SM_NO_SECURITY_GROUPS|escape:'html'}</div>
                {/if}
            </div>
        </div>

        <div class="section">
            <h3><span class="suitepicon suitepicon-action-create-person-form ut-sm-icon" aria-hidden="true"></span>{$MOD.LBL_UT_SM_LEAD_FORMS|escape:'html'}</h3>
            <div class="help-text">{$MOD.LBL_UT_SM_LEAD_FORMS_HELP|escape:'html'}</div>

            <p>
                <a class="btn btn-secondary" href="index.php?module=ut_sm&action=account_settings&record={$RECORD|escape:'url'}&refresh_forms=1">
                    <span class="suitepicon suitepicon-action-reload ut-sm-icon" aria-hidden="true"></span>{$MOD.LBL_UT_SM_REFRESH_FORMS|escape:'html'}
                </a>
            </p>

            {if $HAS_FORMS}
            <div class="checkbox-list">
                {foreach from=$FORMS item=form}
                <label>
                    <input type="checkbox" name="enabled_form_ids[]" value="{$form.form_id|escape:'html'}" {if $form.enabled}checked{/if}>
                    {$form.form_name|escape:'html'}
                </label>
                {/foreach}
            </div>
            {else}
            <div class="status-box info">{$MOD.LBL_UT_SM_NO_FORMS_YET|escape:'html'}</div>
            {/if}
        </div>

        <div class="section">
            <h3><span class="suitepicon suitepicon-action-edit ut-sm-icon" aria-hidden="true"></span>{$MOD.LBL_UT_SM_FIELD_MAPPING|escape:'html'}</h3>
            <div class="help-text">{$MOD.LBL_UT_SM_FIELD_MAPPING_HELP|escape:'html'}</div>

            {if $HAS_FORM_MAPPINGS}
                {foreach from=$FORMS_WITH_MAPPINGS item=fmap}
                <div class="mapping-form-block">
                    <h4>{$MOD.LBL_UT_SM_FORM_MAPPING_HEADING|escape:'html'}: {$fmap.form_name|escape:'html'}</h4>
                    {if $fmap.has_mappings}
                    <table class="mapping-table">
                        <thead>
                            <tr>
                                <th style="width:48%;">{$MOD.LBL_UT_SM_META_FIELD|escape:'html'}</th>
                                <th style="width:52%;">{$MOD.LBL_UT_SM_SUITECRM_FIELD|escape:'html'}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach from=$fmap.mappings item=map}
                            <tr>
                                <td>
                                    <span class="mapping-meta-label">{$map.meta_field_label|escape:'html'}</span>
                                </td>
                                <td>
                                    <select name="field_map[{$fmap.form_id|escape:'html'}][{$map.meta_field_key|escape:'html'}]">
                                        {foreach from=$map.crm_options item=opt}
                                        <option value="{$opt.value|escape:'html'}" {if $opt.selected}selected{/if}>{$opt.label|escape:'html'}</option>
                                        {/foreach}
                                    </select>
                                </td>
                            </tr>
                            {/foreach}
                        </tbody>
                    </table>
                    {if $fmap.has_warnings}
                    <div class="mapping-warnings">
                        {foreach from=$fmap.warnings item=warn}
                        <div class="status-box info">{$warn|escape:'html'}</div>
                        {/foreach}
                    </div>
                    {/if}
                    {else}
                    <div class="status-box info">{$MOD.LBL_UT_SM_NO_QUESTIONS_FOR_FORM|escape:'html'}</div>
                    {/if}
                </div>
                {/foreach}
            {else}
            <div class="status-box info">{$MOD.LBL_UT_SM_NO_MAPPING_YET|escape:'html'}</div>
            {/if}
        </div>

        <div style="margin-bottom: 30px;">
            <button type="submit" class="btn btn-primary">
                <span class="suitepicon suitepicon-action-confirm ut-sm-icon" aria-hidden="true"></span>{$MOD.LBL_UT_SM_SAVE_CONFIGURATION|escape:'html'}
            </button>
            <a href="index.php?module=ut_sm&action=settings" class="btn btn-secondary">
                <span class="suitepicon suitepicon-action-back ut-sm-icon" aria-hidden="true"></span>{$MOD.LBL_UT_SM_CANCEL|escape:'html'}
            </a>
        </div>
    </form>
</div>

{literal}
<script type="text/javascript">
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', utSmToggleAssignmentPanels);
} else {
    utSmToggleAssignmentPanels();
}
</script>
{/literal}
