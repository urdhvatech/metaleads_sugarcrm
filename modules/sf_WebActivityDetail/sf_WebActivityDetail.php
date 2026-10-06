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

class sf_WebActivityDetail extends Basic
{
    public $new_schema = true;
    public $module_dir = 'sf_WebActivityDetail';
    public $object_name = 'sf_WebActivityDetail';
    public $table_name = 'sf_webactivitydetail';
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
    public $webinteractionid;
    public $websessionid;
    public $interactiondate;
    public $protocol;
    public $hostname;
    public $path;
    public $title;
    public $referrer;
    public $parameters;
    public $entrypage;
    public $exitpage;
    public $duration;
    public $webactivitydetailid;
    public $disable_row_level_security = true;

    public function bean_implements($interface)
    {
        switch ($interface) {
            case 'ACL':
                return true;
        }
        return false;
    }
}
