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

class SugarUpgradeUpgradeConfigCategoryMySettings extends UpgradeScript
{

    private const SERIALIZED_FALSE = 'YjowOw==';
    // must be ahead of 2_Rebuild.php
    public $order = 2088;

    public $type = self::UPGRADE_DB;

    public function run()
    {
        if (version_compare($this->from_version, '14.2.0', '>=')) {
            return;
        }

        $bean = BeanFactory::newBean('Administration');

        $query = new SugarQuery();
        $query->select('category', 'value', 'name', 'platform');
        $query->from($bean);
        $query->where()->equals('category', 'MySettings');

        $rows = $query->execute();
        foreach ($rows as $row) {
            $value = $this->convert($row['value']);
            if ($value !== null) {
                $this->db->getConnection()->update('config', ['value' => $value], [
                    'category' => 'MySettings',
                    'name' => $row['name'],
                    'platform' => $row['platform'],
                ]);
            }
        }
        // refresh Administration MySettings cache
        Administration::getSettings('MySettings', true);
    }

    protected function convert(string $rawValue): ?string
    {
        $base64Decoded = base64_decode(trim($rawValue));
        if ($base64Decoded === false) {
            return null;
        }
        // mute warning which is emitted if value is not unserializable, we handle it in different way
        $unserialized = @unserialize($base64Decoded, ['allowed_classes' => false]);
        if ($unserialized === false && $rawValue !== self::SERIALIZED_FALSE) {
            return null;
        }
        if (is_array($unserialized)) {
            $unserialized = array_values($unserialized);
        }
        return json_encode($unserialized);
    }
}
