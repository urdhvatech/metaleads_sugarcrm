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

class SugarUpgradeInjectGAIDashlets extends UpgradeScript
{
    public $order = 9999;
    public $type = self::UPGRADE_DB;

    private $modulesRelatedMetadata = [
        'Cases' => [
            'record' => [
                'dashboardId' => '5d672260-7b52-11e9-93ba-f218983a1c3e',
                'dashletId' => '6ee7abac-02e0-11ef-a101-b50704fce68d',
                'criteria' => [
                    'name' => 'LBL_CASES_RECORD_DASHBOARD',
                    'module' => 'Cases',
                    'view_name' => 'record',
                ],
            ],
            'focus' => [
                'dashboardId' => 'c290ef46-7606-11e9-9129-f218983a1c3e',
                'dashletId' => '364f6f72-02e1-11ef-a101-b50704fce68d',
                'criteria' => [
                    'name' => 'LBL_CASES_FOCUS_DRAWER_DASHBOARD',
                    'module' => 'Cases',
                    'view_name' => 'focus',
                ],
            ],
        ],
        'Opportunities' => [
            'record' => [
                'dashboardId' => '5d671a22-7b52-11e9-b2bc-f218983a1c3e',
                'dashletId' => 'f6e7d35a-3e66-47b7-87f5-c7a380007c98',
                'criteria' => [
                    'name' => 'LBL_OPPORTUNITIES_RECORD_DASHBOARD',
                    'module' => 'Opportunities',
                    'view_name' => 'record',
                ],
            ],
            'focus' => [
                'dashboardId' => 'e64f2d12-13cb-11eb-a909-acde48001122',
                'dashletId' => '9eb4ddec-fbdc-11ee-9899-a0e8aba124d0',
                'criteria' => [
                    'name' => 'LBL_OPPORTUNITIES_FOCUS_DRAWER_DASHBOARD',
                    'module' => 'Opportunities',
                    'view_name' => 'focus',
                ],
            ],
        ],
        'Accounts' => [
            'record' => [
                'dashboardId' => '1606ad42-651d-11ef-a46f-2efb54f90376',
                'dashletId' => 'ed34e261-6127-11ef-86df-0242ac110002',
                'criteria' => [
                    'name' => 'LBL_ACCOUNTS_RECORD_DASHBOARD',
                    'module' => 'Accounts',
                    'view_name' => 'record',
                ],
            ],
            'focus' => [
                'dashboardId' => 'e64ea022-13cb-11eb-b4e3-acde48001122',
                'dashletId' => 'a0684184-6128-11ef-86df-0242ac110002',
                'criteria' => [
                    'name' => 'LBL_ACCOUNTS_FOCUS_DRAWER_DASHBOARD',
                    'module' => 'Accounts',
                    'view_name' => 'focus',
                ],
            ],
        ],
    ];

    private $dashletData = [
        'view' => [
            'type' => 'gai-dashlet',
            'templateEdit' => 'edit',
            'dashletConfig' => [
                'styleConfig' => [
                    'header' => ['class' => 'gradient-header'],
                    'container' => ['class' => 'gradient-border custom-toolbar'],
                    'buttons' => ['class' => 'gradient-buttons'],
                ],
            ],
        ],
        'autoPosition' => false,
        'x' => 0,
        'y' => 0,
    ];

    public function run()
    {
        if (version_compare($this->from_version, '25.2.0', '<')) {
            $this->log('Injecting GAI dashlets...');
            $this->injectDashlets();
            $this->log('Finished injecting GAI dashlets.');
        }
    }

    /**
     * Injects GAI dashlets into the specified dashboards.
     */
    private function injectDashlets()
    {
        foreach ($this->modulesRelatedMetadata as $module => $views) {
            foreach ($views as $viewType => $config) {
                $this->processDashboard($module, $viewType, $config['dashboardId'], $config['dashletId'], $config['criteria']);
            }
        }
    }

    /**
     * Processes a dashboard by injecting the GAI dashlet if it doesn't already exist.
     *
     * @param string $module The module name.
     * @param string $viewType The view type (e.g., 'record', 'focus').
     * @param string $dashboardId The ID of the dashboard.
     * @param string $dashletId The ID of the dashlet to inject.
     * @param array $criteria Criteria to find the dashboard if not found by ID.
     */
    private function processDashboard(string $module, string $viewType, string $dashboardId, string $dashletId, array $criteria)
    {
        $dashboardBean = BeanFactory::retrieveBean('Dashboards', $dashboardId);

        if (!$dashboardBean || empty($dashboardBean->metadata)) {
            $this->log("Dashboard $dashboardId not found. Trying fallback criteria...");

            $dashboardDefinition = $this->getDashboardByCriteria($criteria);
            if (!$dashboardDefinition || empty($dashboardDefinition['id'])) {
                $this->log("GAI[addDashlet]: Unable to find dashboard by ID or criteria: $dashboardId");
                return;
            }

            $dashboardId = $dashboardDefinition['id'];
            $dashboardBean = BeanFactory::retrieveBean('Dashboards', $dashboardId);
        }

        $decodedMetadata = json_decode($dashboardBean->metadata, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log("Invalid JSON in dashboard metadata for dashboard $dashboardId");
            return;
        }

        $metadata = $this->initializeMetadataStructure($decodedMetadata);

        if ($this->dashletExists($metadata, $dashletId)) {
            $this->log("Dashlet $dashletId already exists in dashboard $dashboardId, skipping.");
            return;
        }

        $dashletMeta = $this->getSummaryDashletMeta($dashletId, $module, $viewType);

        if ($this->isRowBasedLayout($metadata)) {
            $this->injectDashletIntoRowLayout($metadata, $dashletMeta, $viewType);
        } elseif ($this->isDashletsLayout($metadata)) {
            $this->injectDashletIntoDashletsLayout($metadata, $dashletMeta, $viewType);
        } else {
            $metadata['components'][0]['rows'] = [[$dashletMeta]];
        }

        $dashboardBean->metadata = json_encode($metadata);

        try {
            $dashboardBean->save();
            $this->log("Injected dashlet into dashboard $dashboardId ($module - $viewType)");
        } catch (Exception $e) {
            $this->log("Failed to save dashboard $dashboardId: " . $e->getMessage());
        }
    }

    /**
     * Initializes the metadata structure for the dashboard.
     * If the 'components' layout is not present, it creates a default structure.
     * If the 'dashlets' layout is present, it leaves it as is.
     *
     * @param array $metadata The dashboard metadata.
     * @return array The initialized metadata structure.
     */
    private function initializeMetadataStructure(array $metadata): array
    {
        if (isset($metadata['dashlets']) && is_array($metadata['dashlets'])) {
            return $metadata;
        }

        if (!isset($metadata['components']) || !is_array($metadata['components'])) {
            $metadata['components'] = [['rows' => []]];
        }
        if (!isset($metadata['components'][0]) || !is_array($metadata['components'][0])) {
            $metadata['components'][0] = ['rows' => []];
        }
        if (!isset($metadata['components'][0]['rows']) || !is_array($metadata['components'][0]['rows'])) {
            $metadata['components'][0]['rows'] = [];
        }

        return $metadata;
    }

    /**
     * Checks if the dashboard metadata is using a row-based layout.
     *
     * @param array $metadata The dashboard metadata.
     * @return bool True if the layout is row-based, false otherwise.
     */
    private function isRowBasedLayout(array $metadata): bool
    {
        return isset($metadata['components'][0]['rows']) && is_array($metadata['components'][0]['rows']);
    }

    /**
     * Checks if the dashboard metadata is using a dashlets layout.
     *
     * @param array $metadata The dashboard metadata.
     * @return bool True if the layout is dashlets-based, false otherwise.
     */
    private function isDashletsLayout(array $metadata): bool
    {
        return isset($metadata['dashlets']) && is_array($metadata['dashlets']);
    }

    /**
     * Retrieves a dashboard by its criteria.
     *
     * @param array $criteria Criteria to find the dashboard.
     * @return array|false The dashboard metadata if found, false otherwise.
     */
    private function getDashboardByCriteria(array $criteria)
    {
        $query = new SugarQuery();
        $bean = BeanFactory::newBean('Dashboards');

        $query->select(['id', 'metadata']);
        $query->from($bean)
            ->where()
            ->equals('name', $criteria['name'])
            ->equals('dashboard_module', $criteria['module'])
            ->equals('view_name', $criteria['view_name']);
        $query->limit(1);

        $result = $query->execute();

        return $result[0] ?? false;
    }

    /**
     * Injects the dashlet metadata into the row layout of the dashboard.
     *
     * @param array $metadata The dashboard metadata.
     * @param array $dashletMeta The dashlet metadata to inject.
     * @param string $viewType The view type (e.g., 'record', 'focus').
     */
    private function injectDashletIntoRowLayout(array &$metadata, array $dashletMeta, string $viewType)
    {
        $rows =& $metadata['components'][0]['rows'];

        if ($viewType === 'record') {
            if (empty($rows[0])) {
                $rows[0] = [$dashletMeta];
            } else {
                array_unshift($rows, [$dashletMeta]);
            }
        } else {
            $flat = array_merge(...$rows);
            $dashletMeta['y'] = $this->calculateNextY($flat);
            $rows[] = [$dashletMeta];
        }
    }

    /**
     * Injects the dashlet metadata into the dashlets layout of the dashboard.
     *
     * @param array $metadata The dashboard metadata.
     * @param array $dashletMeta The dashlet metadata to inject.
     * @param string $viewType The view type (e.g., 'record', 'focus').
     */
    private function injectDashletIntoDashletsLayout(array &$metadata, array $dashletMeta, string $viewType)
    {
        if ($viewType === 'record') {
            array_unshift($metadata['dashlets'], $dashletMeta);
        } else {
            $dashletMeta['y'] = $this->calculateNextY($metadata['dashlets']);
            $metadata['dashlets'][] = $dashletMeta;
        }
    }

    /**
     * Checks if a dashlet with the given ID already exists in the dashboard metadata.
     *
     * @param array $metadata The dashboard metadata.
     * @param string $dashletId The ID of the dashlet to check.
     * @return bool True if the dashlet exists, false otherwise.
     */
    private function dashletExists(array $metadata, string $dashletId): bool
    {
        foreach ($metadata['components'][0]['rows'] ?? [] as $row) {
            foreach ($row as $dashlet) {
                if (!empty($dashlet['id']) && $dashlet['id'] === $dashletId) {
                    return true;
                }
            }
        }

        foreach ($metadata['dashlets'] ?? [] as $dashlet) {
            if (!empty($dashlet['id']) && $dashlet['id'] === $dashletId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculates the next Y position for a new dashlet based on existing dashlets.
     *
     * @param array $dashlets The existing dashlets in the dashboard.
     * @return int The next Y position.
     */
    private function calculateNextY(array $dashlets): int
    {
        $maxY = 0;
        foreach ($dashlets as $dashlet) {
            if (!is_array($dashlet)) {
                continue;
            }
            $y = $dashlet['y'] ?? 0;
            $height = $dashlet['height'] ?? 6;
            $maxY = max($maxY, $y + $height);
        }
        return $maxY;
    }

    /**
     * Returns the metadata for the GAI dashlet to be injected.
     *
     * @param string $id The ID of the dashlet.
     * @param string $module The module name.
     * @param string $viewType The view type (e.g., 'record', 'focus').
     * @return array The dashlet metadata.
     */
    private function getSummaryDashletMeta(string $id, string $module, string $viewType): array
    {
        $dashletLabel = $this->getDashletLabel($module);

        $meta = $this->dashletData;
        $meta['view']['label'] = $dashletLabel;
        $meta['width'] = $viewType === 'focus' ? 6 : 12;
        $meta['id'] = $id;

        if ($viewType === 'focus') {
            $meta['context'] = [
                'module' => $module,
                'skipFetch' => true,
            ];
            $meta['view']['module'] = $module;
        }

        return $meta;
    }

    /**
     * Returns the label for the GAI dashlet based on the module.
     *
     * @param string $module The module name.
     * @return string The dashlet label.
     */
    private function getDashletLabel(string $module): string
    {
        switch ($module) {
            case 'Accounts':
                return 'LBL_GAI_DASHLET_TITLE_INTELLIGENCE';
            case 'Opportunities':
            case 'Cases':
                return 'LBL_GAI_DASHLET_TITLE_SUMMARY';
            default:
                return 'LBL_GAI_DASHLET_TITLE';
        }
    }
}
