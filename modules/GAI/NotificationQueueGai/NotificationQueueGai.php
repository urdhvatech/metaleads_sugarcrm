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

// @codingStandardsIgnoreLine
class NotificationQueueGai extends SugarBean
{
    public $new_schema = true;
    public $module_dir = 'GAI/NotificationQueueGai';
    public $module_name = 'NotificationQueueGai';

    public $object_name = 'NotificationQueueGai';
    public $table_name = 'notification_queue_gai';
    public $importable = false;
    public $disable_custom_fields = true;
    public $tracker_visibility = false;
    public $disable_row_level_security = true;
    public $update_modified_by = false;

    public $id;
    public $date_entered;
    public $date_modified;
    public $deleted;
    public $parent_id;
    public $parent_type;
    public $parent_name;
    public $severity;
    public $notification_user_id;
}
