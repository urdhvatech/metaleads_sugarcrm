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
    margin: 0;
    font-size: 18px;
    line-height: 1.2;
}
.ut-sm-oauth-settings .ut-sm-page-header {
    display: flex;
    align-items: center;
    gap: 12px;
    border-bottom: 2px solid #3498db;
    padding-bottom: 10px;
    margin-bottom: 20px;
}
.ut-sm-oauth-settings .ut-sm-page-header img {
    height: 40px;
    width: auto;
    flex-shrink: 0;
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
.ut-sm-oauth-settings input[type="text"],
.ut-sm-oauth-settings input[type="password"] {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 14px;
}
.ut-sm-oauth-settings .help-text {
    font-size: 12px;
    color: #6c757d;
    margin-top: 5px;
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
.ut-sm-oauth-settings .status-box.warning {
    background-color: #fff3cd;
    border: 1px solid #ffeeba;
    color: #856404;
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
.ut-sm-oauth-settings .btn-success {
    background-color: #28a745;
    color: #fff;
}
.ut-sm-oauth-settings .btn-secondary {
    background-color: #6c757d;
    color: #fff;
}
.ut-sm-oauth-settings .status-pill {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-weight: bold;
    font-size: 12px;
    text-transform: uppercase;
}
.ut-sm-oauth-settings .status-pill.connected {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}
.ut-sm-oauth-settings .status-pill.not-connected {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
.ut-sm-oauth-settings table.pages-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    background: #fff;
}
.ut-sm-oauth-settings table.pages-table th,
.ut-sm-oauth-settings table.pages-table td {
    border: 1px solid #dee2e6;
    padding: 10px;
    text-align: left;
}
.ut-sm-oauth-settings table.pages-table th {
    background: #f8f9fa;
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
.ut-sm-oauth-settings .btn-configure {
    padding: 6px 12px;
    margin: 0;
    white-space: nowrap;
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
.ut-sm-oauth-settings .status-inline {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: #155724;
    font-weight: 600;
    font-size: 13px;
}
.ut-sm-oauth-settings .status-inline .suitepicon {
    color: #28a745;
}
.ut-sm-oauth-settings h3 .ut-sm-icon {
    color: #3498db;
}
.ut-sm-oauth-settings .auth-card {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 16px 18px;
}
.ut-sm-oauth-settings .auth-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}
.ut-sm-oauth-settings .auth-card-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    color: #343a40;
    font-size: 14px;
}
.ut-sm-oauth-settings .auth-card-title .suitepicon {
    color: #3498db;
}
.ut-sm-oauth-settings .auth-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 18px;
    margin: 0 0 14px;
    padding: 10px 12px;
    background: #f8f9fa;
    border-radius: 4px;
    border: 1px solid #e9ecef;
    font-size: 13px;
    color: #495057;
}
.ut-sm-oauth-settings .auth-meta .meta-item {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.ut-sm-oauth-settings .auth-meta .meta-label {
    color: #6c757d;
    font-weight: 600;
}
.ut-sm-oauth-settings .auth-meta.warning {
    background: #fff8e6;
    border-color: #ffe08a;
    color: #856404;
}
.ut-sm-oauth-settings .auth-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
}
.ut-sm-oauth-settings .auth-actions .btn {
    margin-right: 0;
}
.ut-sm-oauth-settings .btn-danger-outline {
    background: #fff;
    color: #c0392b;
    border: 1px solid #e0b4b4;
}
.ut-sm-oauth-settings .btn-danger-outline:hover {
    background: #fdf2f2;
}
.ut-sm-oauth-settings .auth-help {
    margin: 0 0 14px;
    font-size: 13px;
    color: #6c757d;
    line-height: 1.45;
}
.ut-sm-oauth-settings .section-subtitle {
    margin: -6px 0 14px;
    font-size: 13px;
    color: #6c757d;
}
.ut-sm-oauth-settings table.pages-table {
    margin-top: 0;
}
.ut-sm-oauth-settings .accounts-empty {
    margin-top: 0;
}
.ut-sm-oauth-settings .reconcile-stats {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 10px 16px;
    margin: 0 0 14px;
}
.ut-sm-oauth-settings .reconcile-stat {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 4px;
    padding: 10px 12px;
}
.ut-sm-oauth-settings .reconcile-stat .label {
    display: block;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: #6c757d;
    margin-bottom: 4px;
}
.ut-sm-oauth-settings .reconcile-stat .value {
    font-size: 18px;
    font-weight: 700;
    color: #343a40;
}
.ut-sm-oauth-settings input[type="number"] {
    width: 100%;
    max-width: 120px;
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 14px;
}
</style>
{/literal}

<div class="ut-sm-oauth-settings">
    <div class="ut-sm-page-header">
        <img src="modules/ut_sm/images/metalead.png" alt="{$MOD.LBL_UT_SM_OAUTH_PAGE_TITLE|escape:'html'}">
        <h2>{$MOD.LBL_UT_SM_OAUTH_PAGE_TITLE|escape:'html'}</h2>
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

    <div class="section">
        <h3>{$MOD.LBL_UT_SM_FACEBOOK_APP_CONFIGURATION|escape:'html'}</h3>
        <form method="POST" action="index.php?module=ut_sm&action=oauth_handler">
            <input type="hidden" name="save_settings" value="1">
            <input type="hidden" name="ut_sm_form_token" value="{$FORM_TOKEN|escape:'html'}">

            <div class="form-group">
                <label for="app_id">{$MOD.LBL_UT_SM_FACEBOOK_APP_ID|escape:'html'}</label>
                <input type="text" id="app_id" name="app_id" value="{$CURRENT_APP_ID|escape:'html'}" required>
            </div>

            <div class="form-group">
                <label for="app_secret">{$MOD.LBL_UT_SM_FACEBOOK_APP_SECRET|escape:'html'}</label>
                <input type="password" id="app_secret" name="app_secret" value="{$CURRENT_APP_SECRET|escape:'html'}" required>
            </div>

            <div class="form-group">
                <label for="oauth_redirect_uri">{$MOD.LBL_UT_SM_OAUTH_REDIRECT_URI|escape:'html'}</label>
                <input type="text" id="oauth_redirect_uri" name="oauth_redirect_uri" value="{$CURRENT_REDIRECT_URI|escape:'html'}" required>
                <div class="help-text">{$MOD.LBL_UT_SM_OAUTH_REDIRECT_URI_HELP|escape:'html'}</div>
            </div>

            <div class="form-group">
                <label for="callback_url">{$MOD.LBL_UT_SM_CALLBACK_URL|escape:'html'}</label>
                <input type="text" id="callback_url" name="callback_url" value="{$CURRENT_CALLBACK_URL|escape:'html'}" required>
                <div class="help-text">{$MOD.LBL_UT_SM_CALLBACK_URL_HELP|escape:'html'}</div>
            </div>

            <div class="form-group">
                <label for="verify_token">{$MOD.LBL_UT_SM_VERIFY_TOKEN|escape:'html'}</label>
                <input type="text" id="verify_token" name="verify_token" value="{$CURRENT_VERIFY_TOKEN|escape:'html'}" required>
                <div class="help-text">{$MOD.LBL_UT_SM_VERIFY_TOKEN_HELP|escape:'html'}</div>
            </div>

            <div class="form-group">
                <label for="reconciliation_hours">{$MOD.LBL_UT_SM_RECONCILIATION_HOURS|escape:'html'}</label>
                <input type="number" id="reconciliation_hours" name="reconciliation_hours" min="1" max="168" value="{$RECONCILIATION_HOURS|escape:'html'}">
                <div class="help-text">{$MOD.LBL_UT_SM_RECONCILIATION_HOURS_HELP|escape:'html'}</div>
            </div>

            <button type="submit" class="btn btn-primary">
                <span class="suitepicon suitepicon-action-confirm ut-sm-icon" aria-hidden="true"></span>{$MOD.LBL_UT_SM_SAVE_SETTINGS|escape:'html'}
            </button>
            <a href="index.php?module=Administration&action=index" class="btn btn-secondary">{$MOD.LBL_UT_SM_CANCEL|escape:'html'}</a>
        </form>
    </div>

    <div class="section">
        <h3><span class="suitepicon suitepicon-action-user ut-sm-icon" aria-hidden="true"></span>{$MOD.LBL_UT_SM_OAUTH_AUTHORIZATION|escape:'html'}</h3>
        <p class="section-subtitle">{$MOD.LBL_UT_SM_OAUTH_AUTH_SUBTITLE|escape:'html'}</p>

        <div class="auth-card">
            <div class="auth-card-header">
                <div class="auth-card-title">
                    <span class="suitepicon suitepicon-action-confirm" aria-hidden="true"></span>
                    {$MOD.LBL_UT_SM_APP_CONNECTION_STATUS|escape:'html'}
                </div>
                {if $IS_APP_CONNECTED}
                    <span class="status-pill connected">{$MOD.LBL_UT_SM_CONNECTED|escape:'html'}</span>
                {else}
                    <span class="status-pill not-connected">{$MOD.LBL_UT_SM_NOT_CONNECTED|escape:'html'}</span>
                {/if}
            </div>

            {if $IS_APP_CONNECTED}
                <p class="auth-help">{$MOD.LBL_UT_SM_APP_CONNECTED|escape:'html'}</p>
                {if $HAS_TOKEN_EXPIRY}
                <div class="auth-meta{if $TOKEN_NEEDS_REFRESH} warning{/if}">
                    <span class="meta-item">
                        <span class="meta-label">{$MOD.LBL_UT_SM_TOKEN_EXPIRES_AT|escape:'html'}:</span>
                        {$TOKEN_EXPIRES_AT|escape:'html'}
                    </span>
                    {if $TOKEN_NEEDS_REFRESH}
                    <span class="meta-item">{$MOD.LBL_UT_SM_TOKEN_NEEDS_REFRESH|escape:'html'}</span>
                    {/if}
                </div>
                {/if}
                <div class="auth-actions">
                    <a href="index.php?module=ut_sm&action=oauth_handler&action_param=refresh_tokens"
                       class="btn btn-primary">
                        <span class="suitepicon suitepicon-action-reload ut-sm-icon" aria-hidden="true"></span>{$MOD.LBL_UT_SM_REFRESH_TOKENS_NOW|escape:'html'}
                    </a>
                    <a href="index.php?module=ut_sm&action=oauth_handler&action_param=disconnect"
                       class="btn btn-danger-outline"
                       onclick="return confirm('{$MOD.LBL_UT_SM_DISCONNECT_CONFIRM|escape:'javascript'}');">
                        {$MOD.LBL_UT_SM_DISCONNECT|escape:'html'}
                    </a>
                </div>
            {else}
                {if $HAS_APP_CREDENTIALS}
                    {if $HAS_OAUTH_URL}
                    <p class="auth-help">{$MOD.LBL_UT_SM_AUTH_INFO|escape:'html'}</p>
                    <div class="auth-actions">
                        <a href="{$OAUTH_URL|escape:'html'}" class="btn btn-success">
                            <span class="suitepicon suitepicon-action-plus ut-sm-icon" aria-hidden="true"></span>{$MOD.LBL_UT_SM_AUTHORIZE_WITH_FACEBOOK|escape:'html'}
                        </a>
                    </div>
                    {else}
                    <div class="status-box error" style="margin-bottom:0;">
                        {$MOD.LBL_UT_SM_OAUTH_URL_FAILED|escape:'html'}
                    </div>
                    {/if}
                {else}
                <p class="auth-help">{$MOD.LBL_UT_SM_CONFIGURE_APP_FIRST|escape:'html'}</p>
                {/if}
            {/if}
        </div>
    </div>

    <div class="section">
        <h3><span class="suitepicon suitepicon-action-reload ut-sm-icon" aria-hidden="true"></span>{$MOD.LBL_UT_SM_RECONCILIATION_TITLE|escape:'html'}</h3>
        <p class="section-subtitle">{$MOD.LBL_UT_SM_RECONCILIATION_HELP|escape:'html'}</p>

        <div class="auth-card">
            {if $HAS_RECONCILIATION_RUN}
            <div class="reconcile-stats">
                <div class="reconcile-stat">
                    <span class="label">{$MOD.LBL_UT_SM_RECONCILIATION_LAST_RUN|escape:'html'}</span>
                    <span class="value" style="font-size:14px;font-weight:600;">{$RECONCILIATION_SUMMARY.last_run|escape:'html'}</span>
                </div>
                <div class="reconcile-stat">
                    <span class="label">{$MOD.LBL_UT_SM_RECONCILIATION_CHECKED|escape:'html'}</span>
                    <span class="value">{$RECONCILIATION_SUMMARY.checked|escape:'html'}</span>
                </div>
                <div class="reconcile-stat">
                    <span class="label">{$MOD.LBL_UT_SM_RECONCILIATION_IMPORTED|escape:'html'}</span>
                    <span class="value">{$RECONCILIATION_SUMMARY.imported|escape:'html'}</span>
                </div>
                <div class="reconcile-stat">
                    <span class="label">{$MOD.LBL_UT_SM_RECONCILIATION_DUPLICATES|escape:'html'}</span>
                    <span class="value">{$RECONCILIATION_SUMMARY.duplicate|escape:'html'}</span>
                </div>
                <div class="reconcile-stat">
                    <span class="label">{$MOD.LBL_UT_SM_RECONCILIATION_FAILED_COUNT|escape:'html'}</span>
                    <span class="value">{$RECONCILIATION_SUMMARY.failed|escape:'html'}</span>
                </div>
                <div class="reconcile-stat">
                    <span class="label">{$MOD.LBL_UT_SM_RECONCILIATION_RETRIED|escape:'html'}</span>
                    <span class="value">{$RECONCILIATION_SUMMARY.retried|escape:'html'}</span>
                </div>
            </div>
            {if $HAS_RECONCILIATION_ERROR}
            <div class="status-box warning" style="margin-bottom:14px;">
                <strong>{$MOD.LBL_UT_SM_RECONCILIATION_LAST_ERROR|escape:'html'}:</strong> {$RECONCILIATION_SUMMARY.last_error|escape:'html'}
            </div>
            {/if}
            {else}
            <p class="auth-help">{$MOD.LBL_UT_SM_RECONCILIATION_NEVER_RUN|escape:'html'}</p>
            {/if}

            <div class="auth-actions">
                {if $IS_APP_CONNECTED}
                <a href="index.php?module=ut_sm&action=oauth_handler&action_param=reconcile_now"
                   class="btn btn-primary">
                    <span class="suitepicon suitepicon-action-reload ut-sm-icon" aria-hidden="true"></span>{$MOD.LBL_UT_SM_RECONCILIATION_RUN_NOW|escape:'html'}
                </a>
                {/if}
                <span class="help-text" style="margin:0;">{$MOD.LBL_UT_SM_RECONCILIATION_WINDOW|escape:'html'} {$RECONCILIATION_SUMMARY.lookback_hours|escape:'html'} {$MOD.LBL_UT_SM_RECONCILIATION_HOURS_SUFFIX|escape:'html'}</span>
            </div>
        </div>
    </div>

    <div class="section">
        <h3><span class="suitepicon suitepicon-module-accounts ut-sm-icon" aria-hidden="true"></span>{$MOD.LBL_UT_SM_CONNECTED_ACCOUNTS|escape:'html'}</h3>
        <p class="section-subtitle">{$MOD.LBL_UT_SM_CONNECTED_ACCOUNTS_HELP|escape:'html'}</p>
        {if $HAS_CONNECTED_ACCOUNTS}
            <table class="pages-table">
                <thead>
                    <tr>
                        <th>{$MOD.LBL_UT_SM_CONNECTED_ACCOUNT|escape:'html'}</th>
                        <th>{$MOD.LBL_UT_SM_ACCOUNT_TYPE|escape:'html'}</th>
                        <th>{$MOD.LBL_UT_SM_STATUS|escape:'html'}</th>
                        <th>{$MOD.LBL_UT_SM_LEAD_FORMS_COL|escape:'html'}</th>
                        <th>{$MOD.LBL_UT_SM_ASSIGNMENT_COL|escape:'html'}</th>
                        <th>{$MOD.LBL_UT_SM_ACTION|escape:'html'}</th>
                    </tr>
                </thead>
                <tbody>
                    {foreach from=$CONNECTED_ACCOUNTS item=account}
                    <tr>
                        <td>{$account.page_name|default:$MOD.LBL_UT_SM_NA|escape:'html'}</td>
                        <td>
                            {if $account.account_type == 'instagram'}
                                <span class="type-pill instagram">{$MOD.LBL_UT_SM_TYPE_INSTAGRAM|escape:'html'}</span>
                            {else}
                                <span class="type-pill facebook">{$MOD.LBL_UT_SM_TYPE_FACEBOOK|escape:'html'}</span>
                            {/if}
                        </td>
                        <td>
                            <span class="status-inline">
                                <span class="suitepicon suitepicon-action-confirm" aria-hidden="true"></span>
                                {$account.status|escape:'html'}
                            </span>
                        </td>
                        <td>{$account.lead_forms_label|escape:'html'}</td>
                        <td>{$account.assignment_label|escape:'html'}</td>
                        <td>
                            <a href="index.php?module=ut_sm&action=account_settings&record={$account.id|escape:'url'}" class="btn btn-primary btn-configure">
                                <span class="suitepicon suitepicon-action-edit ut-sm-icon" aria-hidden="true"></span>{$MOD.LBL_UT_SM_CONFIGURE|escape:'html'}
                            </a>
                        </td>
                    </tr>
                    {/foreach}
                </tbody>
            </table>
        {else}
            <div class="status-box info accounts-empty">
                {$MOD.LBL_UT_SM_NO_CONNECTED_ACCOUNTS|escape:'html'}
            </div>
        {/if}
    </div>
</div>
