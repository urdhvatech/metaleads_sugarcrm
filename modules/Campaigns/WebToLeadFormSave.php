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
require_once 'include/formbase.php';

global $mod_strings;
global $app_strings;


//-----------begin replacing text input tags that have been marked with text area tags
//get array of text areas strings to process
//Protect &nbsp; entities before html_entity_decode by converting to temporary marker
//then decode HTML entities so tags render properly and restore &nbsp; entities
$bodyHTML = $_REQUEST['body_html'];
$bodyHTML = str_replace('&nbsp;', '___NBSP_PLACEHOLDER___', $bodyHTML);
$bodyHTML = html_entity_decode($bodyHTML, ENT_QUOTES);
$bodyHTML = str_replace('___NBSP_PLACEHOLDER___', '&nbsp;', $bodyHTML);

while (strpos($bodyHTML, 'ta_replace') !== false) {
    //define the marker edges of the sub string to process (opening and closing tag brackets)
    $marker = strpos($bodyHTML, 'ta_replace');
    $start_border = strpos($bodyHTML, 'input', $marker) - 1;// to account for opening '<' char;
    $end_border = strpos($bodyHTML, '>', $start_border); //get the closing tag after marker ">";

    //extract the input tag string
    $working_str = substr($bodyHTML, $marker - 3, $end_border - ($marker - 3));

    //replace input markup with text areas markups
    $new_str = str_replace('input', 'textarea', $working_str);
    $new_str = str_replace("type='text'", ' ', $new_str);
    $new_str = $new_str . '> </textarea';

    //replace the marker with generic term
    $new_str = str_replace('ta_replace', 'sugarslot', $new_str);

    //merge the processed string back into bodyhtml string
    $bodyHTML = str_replace($working_str, $new_str, $bodyHTML);
}
//<<<----------end replacing marked text inputs with text area tags

$guid = create_guid();
$form_file = "upload://$guid";

$SugarTiny = new SugarTinyMCE();
$html = $SugarTiny->cleanEncodedMCEHtml($bodyHTML);

// Re-inject JavaScript functions that TinyMCE may have stripped
// These are essential for Web-to-Lead form validation and submission
$regex = "/^\w+(['\.\-\+]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,})+\$/";
$web_form_required_fileds_msg = $mod_strings['LBL_PROVIDE_WEB_TO_LEAD_FORM_FIELDS'] ?? 'Please provide all the required fields';
$web_not_valid_email_address = $mod_strings['LBL_NOT_VALID_EMAIL_ADDRESS'] ?? 'Not a valid email address';

$javascript = <<<JAVASCRIPT

<script type="text/javascript">
 function submit_form(){
 	check_webtolead_fields();
 }
 function check_webtolead_fields(){
     if(document.getElementById('bool_id') != null){
        var reqs=document.getElementById('bool_id').value;
        bools = reqs.substring(0,reqs.lastIndexOf(';'));
        var bool_fields = bools.split(';');
        nbr_fields = bool_fields.length;
        for(var i=0;i<nbr_fields;i++){
          if(document.getElementById(bool_fields[i]).value == 'on'){
             document.getElementById(bool_fields[i]).value = 1;
          }
          else{
             document.getElementById(bool_fields[i]).value = 0;
          }
        }
      }
    if(document.getElementById('req_id') != null){
        var reqs=document.getElementById('req_id').value;
        reqs = reqs.substring(0,reqs.lastIndexOf(';'));
        var req_fields = reqs.split(';');
        nbr_fields = req_fields.length;
        var req = true;
        for(var i=0;i<nbr_fields;i++){
          if(document.getElementById(req_fields[i]).value.length <=0 || document.getElementById(req_fields[i]).value==0){
           req = false;
           break;
          }
        }
        if(req){
            document.WebToLeadForm.submit();
            return true;
        }
        else{
          alert('$web_form_required_fileds_msg');
          return false;
         }
        return false
   }
   else{
    document.WebToLeadForm.submit();
   }
}
function validateEmailAdd(){
	if(document.getElementById('email1') && document.getElementById('email1').value.length >0) {
		if(document.getElementById('email1').value.match($regex) == null){
		  alert('$web_not_valid_email_address');
		}
	}
	if(document.getElementById('email2') && document.getElementById('email2').value.length >0) {
		if(document.getElementById('email2').value.match($regex) == null){
		  alert('$web_not_valid_email_address');
		}
	}
}
</script>
JAVASCRIPT;

// Re-inject event handlers that TinyMCE strips for security
// 1. Submit button onclick handler (match regardless of attribute order)
$html = preg_replace(
    '/(<input[^>]*name=["\']Submit["\'][^>]*type=["\']button["\'][^>]*)(>)/i',
    '$1 onclick=\'submit_form();\' $2',
    $html
);
// Also try reverse order if first pattern didn't match
if (strpos($html, 'onclick=\'submit_form();\'') === false) {
    $html = preg_replace(
        '/(<input[^>]*type=["\']button["\'][^>]*name=["\']Submit["\'][^>]*)(>)/i',
        '$1 onclick=\'submit_form();\' $2',
        $html
    );
}

// 2. Email field onchange handlers for validation
$html = preg_replace(
    '/(<input[^>]*(?:id|name)=["\']email[12]?["\'][^>]*)(>)/i',
    '$1 onchange=\'validateEmailAdd();\' $2',
    $html
);

// 3. Date field onblur handlers
$html = preg_replace_callback(
    '/(<input[^>]*name=["\']([a-z_]+)_(month|day|year)["\'][^>]*)(>)/i',
    function ($matches) {
        $fieldName = $matches[2];
        return $matches[1] . ' onblur="update' . $fieldName . 'Value()" ' . $matches[4];
    },
    $html
);

// Inject the JavaScript before the closing </body> tag, or at the end if no body tag exists
if (stripos($html, '</body>') !== false) {
    $html = str_ireplace('</body>', $javascript . '</body>', $html);
} elseif (stripos($html, '</form>') !== false) {
    $html = str_ireplace('</form>', '</form>' . $javascript, $html);
} else {
    $html .= $javascript;
}

// Fix encoding issues: remove any invalid UTF-8 sequences before &nbsp; entities
// The issue is TinyMCE or html_entity_decode adds invalid characters before nbsp
$html = preg_replace('/\xC2\xA0/', '&nbsp;', $html); // Replace UTF-8 non-breaking space with entity
$html = preg_replace('/[\x00-\x1F\x7F-\x9F]/u', '', $html); // Remove control characters except newlines/tabs
$html = str_replace(chr(194) . chr(160), '&nbsp;', $html); // Another way to catch UTF-8 nbsp
$html = str_replace(chr(160), '&nbsp;', $html); // Catch ISO-8859-1 nbsp

//Check to ensure we have <html> tags in the form. Without them, IE8 will attempt to display the page as XML.
if (stripos($html, '<html') === false) {
    $langHeader = get_language_header();
    $html = "<html {$langHeader}><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\"></head><body>" . $html . '</body></html>';
}
file_put_contents($form_file, $html);

$xtpl = new XTemplate('modules/Campaigns/WebToLeadDownloadForm.html');
$xtpl->assign('MOD', $mod_strings);
$xtpl->assign('APP', $app_strings);
$webformlink = "<b>$mod_strings[LBL_DOWNLOAD_TEXT_WEB_TO_LEAD_FORM]</b><br/>";
$webformlink .= "<a href=\"index.php?entryPoint=download&id={$guid}&isTempFile=1&tempName=WebToLeadForm.html&type=temp\">$mod_strings[LBL_DOWNLOAD_WEB_TO_LEAD_FORM]</a>";
$xtpl->assign('LINK_TO_WEB_FORM', $webformlink);
$xtpl->assign('RAW_SOURCE', htmlspecialchars($html, ENT_COMPAT));
$xtpl->parse('main.copy_source');
$xtpl->parse('main');
$xtpl->out('main');
