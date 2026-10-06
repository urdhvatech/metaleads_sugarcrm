<?php
/**
 * This file is part of the "Meta Leads" package.
 *
 * @package Meta Leads
 * @author Urdhva Tech <contact@urdhva-tech.com>
 * @link https://www.urdhva-tech.com
 * @copyright Urdhva Tech
 * @license As specified in the License Agreement supplied with this package.
 */
if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

require_once 'include/MVC/View/views/view.detail.php';
require_once 'modules/ut_sm/services/MetaLeadSubmissionService.php';

class ut_MetaLeadSubmissionsViewDetail extends ViewDetail
{
    public function preDisplay()
    {
        parent::preDisplay();
        $this->assignSubmittedValuesHtml();
    }

    public function display()
    {
        $this->assignSubmittedValuesHtml();

        echo '<style type="text/css">'
            . '.ut-mls-submitted-values{margin:4px 0 12px;max-width:720px;}'
            . '.ut-mls-submitted-table{width:100%;border-collapse:collapse;background:#fff;'
            . 'border:1px solid #dee2e6;border-radius:6px;overflow:hidden;}'
            . '.ut-mls-submitted-table th,.ut-mls-submitted-table td{padding:10px 14px;'
            . 'border-bottom:1px solid #e9ecef;vertical-align:top;font-size:13px;line-height:1.4;}'
            . '.ut-mls-submitted-table tr:last-child th,.ut-mls-submitted-table tr:last-child td{border-bottom:none;}'
            . '.ut-mls-submitted-table th{width:34%;text-align:left;font-weight:600;color:#495057;'
            . 'background:#f8f9fa;white-space:nowrap;}'
            . '.ut-mls-submitted-table td{color:#212529;word-break:break-word;}'
            . '.ut-mls-submitted-table tr:nth-child(even) td{background:#fcfcfd;}'
            . '.ut-mls-submitted-pre{margin:0;padding:12px 14px;background:#f8f9fa;border:1px solid #dee2e6;'
            . 'border-radius:6px;white-space:pre-wrap;font-family:inherit;font-size:13px;}'
            . '.ut-mls-empty{color:#6c757d;font-style:italic;}'
            . '</style>';

        parent::display();
    }

    protected function assignSubmittedValuesHtml()
    {
        $raw = '';
        if (!empty($this->bean) && isset($this->bean->submitted_values)) {
            $raw = $this->bean->submitted_values;
        }
        $html = UTSMMetaLeadSubmissionService::formatSubmittedValuesHtml($raw);

        if (!empty($this->dv) && !empty($this->dv->ss)) {
            $this->dv->ss->assign('UT_MLS_SUBMITTED_VALUES_HTML', $html);
        }
        if (!empty($this->ss)) {
            $this->ss->assign('UT_MLS_SUBMITTED_VALUES_HTML', $html);
        }
    }
}
