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

/**
 * Set DB collation to utf8mb4_0900_ai_ci
 */
class SugarUpgradeChangeMySQLCollation extends UpgradeScript
{
    public $order = 100;
    private $defaultCollation = 'utf8mb4_0900_ai_ci';
    private $collationMap = [
        'utf8mb4_general_ci' => 'utf8mb4_0900_ai_ci',
        'utf8mb4_bin' => 'utf8mb4_0900_ai_ci',
        'utf8mb4_unicode_ci' => 'utf8mb4_0900_ai_ci',
        'utf8mb4_unicode_520_ci' => 'utf8mb4_0900_ai_ci',
        'utf8mb4_german2_ci' => 'utf8mb4_de_pb_0900_ai_ci',
        'utf8mb4_swedish_ci' => 'utf8mb4_sv_0900_ai_ci',
        'utf8mb4_spanish_ci' => 'utf8mb4_es_0900_ai_ci',
        'utf8mb4_danish_ci' => 'utf8mb4_da_0900_ai_ci',
        'utf8mb4_croatian_ci' => 'utf8mb4_hr_0900_ai_ci',
        'utf8mb4_slovak_ci' => 'utf8mb4_sk_0900_ai_ci',
        'utf8mb4_slovenian_ci' => 'utf8mb4_sl_0900_ai_ci',
        'utf8mb4_polish_ci' => 'utf8mb4_pl_0900_ai_ci',
        'utf8mb4_latvian_ci' => 'utf8mb4_lv_0900_ai_ci',
        'utf8mb4_lithuanian_ci' => 'utf8mb4_lt_0900_ai_ci',
        'utf8mb4_roman_ci' => 'utf8mb4_ro_0900_ai_ci',
        'utf8mb4_romanian_ci' => 'utf8mb4_ro_0900_ai_ci',
        'utf8mb4_hungarian_ci' => 'utf8mb4_hu_0900_ai_ci',
        'utf8mb4_estonian_ci' => 'utf8mb4_et_0900_ai_ci',
        'utf8mb4_spanish2_ci' => 'utf8mb4_es_trad_0900_ai_ci',
        'utf8mb4_esperanto_ci' => 'utf8mb4_eo_0900_ai_ci',
        'utf8mb4_sinhala_ci' => 'utf8mb4_si_0900_ai_ci',
    ];

    public function run()
    {
        if ($this->shouldRun() === false) {
            return;
        }
        $cfg = new Configurator();
        $db = DBManagerFactory::getInstance();
        $platform = $db->getConnection()
            ->getDatabasePlatform();
        $newCollation = $this->getCollation($cfg);
        $db->query(
            sprintf(
                'ALTER DATABASE %s DEFAULT COLLATE %s',
                $platform->quoteIdentifier(SugarConfig::getInstance()->get('dbconfig.db_name', '')),
                $platform->quoteIdentifier($newCollation)
            ),
            true
        );
        $db->setCollation($newCollation);
        if (isset($cfg->config['dbconfigoption']['collation']) && $cfg->config['dbconfigoption']['collation'] != $newCollation) {
            $cfg->config['dbconfigoption']['collation'] = $newCollation;
            $cfg->populateFromPost();
            $cfg->handleOverride();
        }
    }

    protected function shouldRun(): bool
    {
        return version_compare($this->from_version, '14.2.0', '<')
            && DBManagerFactory::getConnection()->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\MySQL80Platform;
    }

    protected function getCollation($cfg)
    {
        $collationMap = $this->collationMap;
        if (isset($cfg->config['dbconfigoption']['collation'])) {
            if (isset($collationMap[$cfg->config['dbconfigoption']['collation']])) {
                return $collationMap[$cfg->config['dbconfigoption']['collation']];
            }
            if (in_array($cfg->config['dbconfigoption']['collation'], $collationMap)) {
                return $cfg->config['dbconfigoption']['collation'];
            }
        }
        return $this->defaultCollation;
    }
}
