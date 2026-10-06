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
/**
 * UT SM service layer bootstrap.
 *
 * Loads all Meta Lead Ads integration services in dependency order.
 * Entry points may require individual service files or this bootstrap once.
 */
if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

$utSmServicesDir = __DIR__;

require_once $utSmServicesDir . '/GraphClient.php';
require_once $utSmServicesDir . '/AssignmentService.php';
require_once $utSmServicesDir . '/FieldMappingService.php';
require_once $utSmServicesDir . '/ImportTrackerService.php';
require_once $utSmServicesDir . '/LicenseService.php';
require_once $utSmServicesDir . '/MetaLeadSubmissionService.php';
require_once $utSmServicesDir . '/AccountConfigService.php';
require_once $utSmServicesDir . '/LeadImportService.php';
require_once $utSmServicesDir . '/TokenService.php';
require_once $utSmServicesDir . '/ReconciliationService.php';
