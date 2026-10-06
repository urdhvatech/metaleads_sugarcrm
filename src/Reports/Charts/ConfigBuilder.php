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

namespace Sugarcrm\Sugarcrm\Reports\Charts;

use BeanFactory;
use Report;

class ConfigBuilder
{
    /** @var array */
    public $chartData;
    public $reporter;
    /**
     * @var mixed|mixed[]
     */
    public $chartType;
    public $reportDef;
    /**
     * @var \Sugarcrm\Sugarcrm\Reports\Charts\Types\BaseChart|null|mixed
     */
    public $chart;
    // map chartjs types with our chart types
    private $typeMap = [
        'treemapF' => 'treemap',
        'pieF' => 'pie',
        'donutF' => 'doughnut',
        'funnelF' => 'funnel',
        'hBarF' => 'bar',
        'hGBarF' => 'bar',
        'vBarF' => 'bar',
        'vGBarF' => 'bar',
        'lineF' => 'line',
    ];
    private $config = [];

    /**
     * Config builder constructor
     *
     * @param array $chartData
     * @param Report $reporter
     */
    public function __construct(array $chartData, Report $reporter)
    {
        $this->chartData = $chartData['chartData'];
        $this->reporter = $reporter;
        $this->chartType = $this->reporter->chart_type;
        $this->reportDef = $this->reporter->report_def;

        $this->setupConsistentChartColors($chartData);

        $this->chart = ChartFactory::getChart($this->chartType, $this->chartData, $this->reportDef);
    }

    /**
     * Setup all the necessary stuff for a chart config
     */
    public function build()
    {
        $chartOptions = $this->chart->getOptions();

        $this->config['type'] = $this->typeMap[$this->chartType];
        $this->config['data'] = $this->chart->transformData();
        $this->config['options'] = $chartOptions;
        $this->config['plugins'] = $chartOptions['plugins'];
    }

    /**
     * Returns the config
     *
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get the option key based on label
     *
     * @param array $dom
     * @param string $optionLabel
     *
     * @return string
     */
    private function getOptionKeyByLabel($dom, $optionLabel)
    {
        $optionKey = false;

        foreach ($dom as $key => $label) {
            if ($label === $optionLabel) {
                $optionKey = $key;
                break;
            }
        }
        return $optionKey;
    }

    /**
     * Setup consistent chart colors
     *
     * @param array $chartData
     */
    private function setupConsistentChartColors($data)
    {
        $chartLabels = $this->getChartLabels($data['chartData']);
        $chartColors = $this->getConsistentChartColors($data);

        if (!empty($chartColors) && safeCount($chartColors) === safeCount($chartLabels)) {
            $this->chartData['colorOverrideList'] = $chartColors;
        }

        // if we have 2 group bys but only 1 label, we need to use the second group by as the label
        if (safeCount($data['chartData']['label']) === 1 && isset($data['reportData']['group_defs'])) {
            $this->setupSingularLabel($data['reportData'], $data['chartData']);
        }
    }

    /**
     * Setup singular label
     *
     * @param array $reportData
     * @param array $chartData
     *
     * @return bool
     */
    private function setupSingularLabel($reportData, $chartData)
    {
        global $app_list_strings, $app_dropdowns_style;

        $groupMeta = $this->getGroupByMeta('last', $reportData['group_defs'], $reportData);

        if (!$groupMeta || !isset($groupMeta['options']) || $groupMeta['type'] !== 'enum') {
            return false;
        }


        $domName = array_key_exists('options', $groupMeta) ? $groupMeta['options'] : '';

        if (empty($domName)) {
            return [];
        }

        $options = array_key_exists($domName, $app_list_strings) ? $app_list_strings[$domName] : [];

        $domStyleName = $domName . '_style';
        $optionsStyle = array_key_exists($domStyleName, $app_dropdowns_style) ? $app_dropdowns_style[$domStyleName] : [];

        if (empty($optionsStyle) || empty($options)) {
            return false;
        }

        $targetLabel = $chartData['label'][0];
        $optionKey = $this->getOptionKeyByLabel($options, $targetLabel);

        if ($optionKey === false) {
            return false;
        }

        $optionStyle = $optionsStyle[$optionKey];

        if (!$optionStyle || !isset($optionStyle['backgroundColor'])) {
            return false;
        }

        $this->chartData['legendItemColor'] = $optionStyle['backgroundColor'];

        return true;
    }

    /**
     * Get consistent chart colors
     *
     * @param array $data
     *
     * @return array
     */
    private function getConsistentChartColors($data)
    {
        global $app_list_strings, $app_dropdowns_style;

        $defaultColor = '#e2d4fd';
        $reportData = $data['reportData'];
        $chartData = $data['chartData'];
        $fieldMeta = $this->getChartGroupColumn($reportData, $chartData);
        $chartType = reset($chartData['properties'])['type'];

        if (!$fieldMeta || !array_key_exists('options', $fieldMeta) ||
            !isset($fieldMeta['options']) || $fieldMeta['type'] !== 'enum') {
            return [];
        }

        $domName = array_key_exists('options', $fieldMeta) ? $fieldMeta['options'] : '';

        if (empty($domName)) {
            return [];
        }

        $options = array_key_exists($domName, $app_list_strings) ? $app_list_strings[$domName] : [];

        if (empty($options)) {
            return [];
        }

        $domStyleName = $domName . '_style';
        $optionsStyle = array_key_exists($domStyleName, $app_dropdowns_style) ? $app_dropdowns_style[$domStyleName] : [];

        if (empty($optionsStyle)) {
            return [];
        }

        if ($chartType === 'treemap chart') {
            usort($chartData['values'], function ($a, $b) {
                return reset($b['values']) - reset($a['values']);
            });
        }

        $chartLabels = $this->getChartLabels($chartData);
        $consistentColors = [];

        foreach ($chartLabels as $optionLabel) {
            $targetLabel = is_array($optionLabel) ? reset($optionLabel) : $optionLabel;
            $optionKey = $this->getOptionKeyByLabel($options, $targetLabel);

            if ($optionKey === false) {
                $consistentColors[] = $defaultColor;
                continue;
            }

            $optionStyle = array_key_exists($optionKey, $optionsStyle) ? $optionsStyle[$optionKey] : false;

            if (!$optionStyle) {
                $consistentColors[] = $defaultColor;
                continue;
            }

            $consistentColors[] = $optionStyle['backgroundColor'] ?? $defaultColor;
        }

        if ($chartType === 'funnel chart 3D') {
            return array_reverse($consistentColors);
        }

        return $consistentColors;
    }

    /**
     * Get chart group column
     *
     * @param array $reportData
     *
     * @return array|bool
     */
    private function getChartGroupColumn($reportData, $chartData)
    {
        $reportDataGroupDefs = array_key_exists('group_defs', $reportData) ? $reportData['group_defs'] : [];

        if (safeCount($reportDataGroupDefs) === 0) {
            return false;
        }

        $alwaysFirstGrp = [
            'pie chart',
            'funnel chart 3D',
            'line chart',
            'donut chart',
            'treemap chart',
        ];
        $chartType = reset($chartData['properties'])['type'];
        $singularLabel = safeCount($chartData['label']) === 1;
        $targetGroupByFn = in_array($chartType, $alwaysFirstGrp) || $singularLabel ? 'first' : 'last';

        return $this->getGroupByMeta($targetGroupByFn, $reportDataGroupDefs, $reportData);
    }

    private function getGroupByMeta($targetGroupByFn, $reportDataGroupDefs, $reportData)
    {
        $targetIdx = 0;

        if ($targetGroupByFn === 'last') {
            $targetIdx = min(1, safeCount($reportDataGroupDefs) - 1);
        }

        $chartGroupBy = $reportDataGroupDefs[$targetIdx];
        $tableKey = array_key_exists('table_key', $chartGroupBy) ? $chartGroupBy['table_key'] : '';
        $fieldName = array_key_exists('name', $chartGroupBy) ? $chartGroupBy['name'] : '';
        $tableList = array_key_exists('full_table_list', $reportData) ? $reportData['full_table_list'] : '';

        $tableData = array_key_exists($tableKey, $tableList) ? $tableList[$tableKey] : [];
        $module = array_key_exists('module', $tableData) ? $tableData['module'] : '';

        if (!$module || !$fieldName) {
            return false;
        }

        $targetBean = BeanFactory::newBean($module);

        return array_key_exists($fieldName, $targetBean->field_defs) ? $targetBean->field_defs[$fieldName] : false;
    }

    /**
    * Get chart labels
    *
    * @param array $chartData
    *
    * @return array
    */
    private function getChartLabels($chartData)
    {
        $parsedLabelsCharts = ['line chart', 'treemap chart'];
        $chartType = reset($chartData['properties'])['type'];

        $chartLabels = $chartData['label'];

        if (in_array($chartType, $parsedLabelsCharts) || safeCount($chartData['label']) === 1) {
            $chartLabels = array_column($chartData['values'], 'label');
        }

        return $chartLabels;
    }
}
