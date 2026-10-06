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

class sf_webActivity extends Basic
{
    public $new_schema = true;
    public $module_dir = 'sf_webActivity';
    public $object_name = 'sf_webActivity';
    public $table_name = 'sf_webactivity';
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
    public $webactivityid;
    public $websessionid;
    public $recipientid;
    public $emailaddress;
    public $webbrowserid;
    public $contactid;
    public $clienthostname;
    public $clientip;
    public $duration;
    public $enddate;
    public $hemisphere;
    public $startdate;
    public $timezone;
    public $browserlanguage;
    public $javaenabled;
    public $resolution;
    public $colordepth;
    public $pixeldepth;
    public $operatingsystem;
    public $useragent;
    public $javascriptversion;
    public $identifiedby;
    public $referrerdomain;
    public $referrerkeywords;
    public $referrerquery;
    public $referrerreferrer;
    public $longitude;
    public $latitude;
    public $areacode;
    public $city;
    public $countrycode;
    public $countryname;
    public $dma_code;
    public $postalcode;
    public $region;
    public $ownerid;
    public $organizationname;
    public $scoring;
    public $chat;
    public $isp;
    public $touchpoint;
    public $dialogsession;
    public $cookiesenabled;
    public $donottrack;
    public $placecookie;
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
