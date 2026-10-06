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

use Sugarcrm\Sugarcrm\AccessControl\AdminWork;

/**
 * Update is_template flag for Template Reports
 */
class SugarUpgradeSetupReportTemplates extends UpgradeScript
{
    public $order = 9500;
    public $type = self::UPGRADE_DB;

    /**
     * @throws SugarQueryException
     */
    public function run()
    {
        $this->log('Running 9_SetupReportTemplates script...');

        // install the new Reports as templates
        if (version_compare($this->from_version, '14.0.0', '<')) {
            $this->log('Installing new Template Reports');
            $this->installTemplateReports();
            $this->log('Successfully Installed new Template Reports');
        } else {
            $this->log('Not installing new Template Reports');
        }

        // Check and install audit reports if upgrading to versions >= 14.1
        if ($this->shouldInstallAuditReports()) {
            $this->installReports($this->getAuditReportsToInstall());
        } else {
            $this->log('Not installing new audit reports');
        }
    }

    /**
     * Determine if we should install the new Audit Reports this upgrade.
     *
     * @return bool true if we should install new Audit Reports this upgrade. false otherwise.
     */
    public function shouldInstallAuditReports(): bool
    {
        $auditReportIds = ['1a094f7e-1ba3-11ef-b364-9ec2aa760073', '9ffae228-1ba3-11ef-a143-9ec2aa760073'];

        $bean = BeanFactory::newBean('Reports');
        $query = new SugarQuery();

        $query->select(['id']);
        $query->from($bean);
        $query->where()->in('id', $auditReportIds);

        $row = $query->execute();

        return empty($row);
    }

    /**
     * Get the new Audit Reports to install for this upgrade.
     *
     * @return array A list of new Audit Report names.
     */
    public function getAuditReportsToInstall(): array
    {
        // New audit reports
        return [
            'AUDIT: User Status',
            'AUDIT: Portal Enabled Contacts',
        ];
    }

    /**
     * Install the OOB Reports with the given names.
     *
     * @param array $reportNames List of report names to install.
     *   These should be actual Report names, not translatable labels.
     */
    public function installReports(array $reportNames)
    {
        $this->prepareAdminWork();
        create_default_reports(true, $reportNames);
    }

    /**
     * Install the Template Reports
     */
    public function installTemplateReports()
    {
        $this->prepareAdminWork();

        create_default_reports();
    }

    /**
     * Prepare the groundwork
     */
    public function prepareAdminWork()
    {
        $this->log('Temporarily enabling admin work for Report installation');
        $adminWork = new AdminWork();
        $adminWork->startAdminWork();

        require_once 'modules/Reports/SavedReport.php';
        require_once 'modules/Reports/SeedReports.php';

        $this->log('Installing new Reports');
    }
}
