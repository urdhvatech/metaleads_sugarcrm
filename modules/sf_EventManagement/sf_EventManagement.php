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

class sf_EventManagement extends Basic
{
    public $new_schema = true;
    public $module_dir = 'sf_EventManagement';
    public $object_name = 'sf_EventManagement';
    public $table_name = 'sf_eventmanagement';
    public $importable = false;
    public $id;
    public $name;
    public $date_entered;
    public $date_modified;
    public $modified_user_id;
    public $modified_by_name;
    public $created_by;
    public $created_by_name;
    public $description;
    public $deleted;
    public $created_by_link;
    public $modified_user_link;
    public $assigned_user_id;
    public $assigned_user_name;
    public $assigned_user_link;
    public $attended;
    public $duration;
    public $event_date;
    public $event_location;
    public $lead_source;
    public $registered;
    public $disable_row_level_security = true;

    /**
     * {@inheritdoc}
     */
    public function bean_implements($interface)
    {
        switch ($interface) {
            case 'ACL':
                return true;
        }
        return false;
    }
}
