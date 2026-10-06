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

use Sugarcrm\Sugarcrm\CSP\Nonce;

class ProspectList extends SugarBean
{
    // Stored fields
    public $id;
    public $date_entered;
    public $date_modified;
    public $modified_user_id;
    public $assigned_user_id;
    public $created_by;
    public $created_by_name;
    public $modified_by_name;
    public $list_type;
    public $domain_name;
    public $team_id;
    public $team_name;

    public $name;
    public $description;

    // These are related
    public $assigned_user_name;
    public $prospect_id;
    public $contact_id;
    public $lead_id;

    // module name definitions and table relations
    public $table_name = 'prospect_lists';
    public $module_dir = 'ProspectLists';
    public $rel_prospects_table = 'prospect_lists_prospects';
    public $object_name = 'ProspectList';

    // This is used to retrieve related fields from form posts.
    public $additional_column_fields = [
        'assigned_user_name', 'assigned_user_id', 'campaign_id',
    ];
    public $relationship_fields = [
        'campaign_id' => 'campaigns',
        'prospect_list_prospects' => 'prospects',
    ];

    public $entry_count;

    public $new_schema = true;

    public function get_summary_text()
    {
        return "$this->name";
    }

    public function create_list_query($order_by, $where, $show_deleted = 0)
    {
        $custom_join = $this->getCustomJoin();

        $query = 'SELECT ';
        $query .= 'users.user_name as assigned_user_name, ';
        $query .= 'prospect_lists.*';

        if ($custom_join) {
            $query .= $custom_join['select'];
        }
        $query .= ', teams.name as team_name';
        $query .= ' FROM prospect_lists ';

        // We need to confirm that the user is a member of the team of the item.
        $this->add_team_security_where_clause($query);
        $query .= 'LEFT JOIN users
					ON prospect_lists.assigned_user_id=users.id ';
        $query .= 'LEFT JOIN teams ON prospect_lists.team_id=teams.id ';

        if ($custom_join) {
            $query .= $custom_join['join'];
        }

        $where_auto = '1=1';
        if ($show_deleted == 0) {
            $where_auto = "$this->table_name.deleted=0";
        } elseif ($show_deleted == 1) {
            $where_auto = "$this->table_name.deleted=1";
        }

        if ($where != '') {
            $query .= "where $where AND " . $where_auto;
        } else {
            $query .= 'where ' . $where_auto;
        }

        if ($order_by != '') {
            $query .= " ORDER BY $order_by";
        } else {
            $query .= ' ORDER BY prospect_lists.name';
        }

        return $query;
    }

    /**
     * @param string $record_id
     * @return string
     *
     * @deprecated since april 2024, use create_export_members_by_sugar_query() instead
     */
    public function create_export_members_query(string $record_id): string
    {
        $module = 'Leads';
        $leadsQuery = "SELECT l.id AS id, '$module' AS related_type, '' AS name, l.first_name AS first_name, 
                l.last_name AS last_name, l.title AS title, l.salutation AS salutation,
                l.primary_address_street AS primary_address_street,l.primary_address_city AS primary_address_city, 
                l.primary_address_state AS primary_address_state, 
                l.primary_address_postalcode AS primary_address_postalcode, 
                l.primary_address_country AS primary_address_country,
                l.account_name AS account_name,
                ea.email_address AS primary_email_address, ea.invalid_email AS invalid_email, 
                ea.opt_out AS opt_out, ea.deleted AS ea_deleted, ear.deleted AS ear_deleted, 
                ear.primary_address AS primary_address,
                l.do_not_call AS do_not_call, l.phone_fax AS phone_fax, l.phone_other AS phone_other, 
                l.phone_home AS phone_home, l.phone_mobile AS phone_mobile, l.phone_work AS phone_work
            FROM prospect_lists_prospects plp
            INNER JOIN leads l ON plp.related_id=l.id AND plp.prospect_list_id = %1\$s AND plp.related_type = '$module' AND plp.deleted=0";
        BeanFactory::newBean($module)->addVisibilityFrom($leadsQuery, ['table_alias' => 'l']);
        $leadsQuery .= "
            LEFT JOIN email_addr_bean_rel ear ON  ear.bean_id=l.id AND ear.bean_module = 'Leads' AND ear.deleted=0
            LEFT JOIN email_addresses ea ON ear.email_address_id=ea.id
            WHERE l.deleted=0
            AND (ear.deleted=0 OR ear.deleted IS NULL)";
        BeanFactory::newBean($module)->addVisibilityWhere($leadsQuery, ['table_alias' => 'l']);

        $module = 'Users';
        $usersQuery = "SELECT u.id AS id, '$module' AS related_type, '' AS name, u.first_name AS first_name, 
                u.last_name AS last_name, u.title AS title, '' AS salutation,
                u.address_street AS primary_address_street,u.address_city AS primary_address_city,
                u.address_state AS primary_address_state,  u.address_postalcode AS primary_address_postalcode, 
                u.address_country AS primary_address_country,
                '' AS account_name, ea.email_address AS email_address, ea.invalid_email AS invalid_email, 
                ea.opt_out AS opt_out, ea.deleted AS ea_deleted, ear.deleted AS ear_deleted, 
                ear.primary_address AS primary_address, 0 AS do_not_call, u.phone_fax AS phone_fax, 
                u.phone_other AS phone_other, u.phone_home AS phone_home, u.phone_mobile AS phone_mobile, 
                u.phone_work AS phone_work
            FROM prospect_lists_prospects plp
            INNER JOIN users u ON plp.related_id=u.id AND plp.prospect_list_id = %1\$s AND plp.related_type = '$module' AND plp.deleted=0";
        BeanFactory::newBean($module)->addVisibilityFrom($usersQuery, ['table_alias' => 'u']);
        $usersQuery .= "
            LEFT JOIN email_addr_bean_rel ear ON  ear.bean_id=u.id AND ear.bean_module = 'Users' AND ear.deleted=0
            LEFT JOIN email_addresses ea ON ear.email_address_id=ea.id
            WHERE u.deleted=0
            AND (ear.deleted=0 OR ear.deleted IS NULL)";
        BeanFactory::newBean($module)->addVisibilityWhere($usersQuery, ['table_alias' => 'u']);

        $module = 'Contacts';
        $contactsQuery = "SELECT c.id AS id, '$module' AS related_type, '' AS name, c.first_name AS first_name,
                c.last_name AS last_name,c.title AS title, c.salutation AS salutation,
                c.primary_address_street AS primary_address_street, c.primary_address_city AS primary_address_city, 
                c.primary_address_state AS primary_address_state,  
                c.primary_address_postalcode AS primary_address_postalcode, 
                c.primary_address_country AS primary_address_country, a.name AS account_name,
                ea.email_address AS email_address, ea.invalid_email AS invalid_email, ea.opt_out AS opt_out, 
                ea.deleted AS ea_deleted, ear.deleted AS ear_deleted, ear.primary_address AS primary_address,
                c.do_not_call AS do_not_call, c.phone_fax AS phone_fax, c.phone_other AS phone_other, 
                c.phone_home AS phone_home, c.phone_mobile AS phone_mobile, c.phone_work AS phone_work
            FROM prospect_lists_prospects plp
            INNER JOIN contacts c ON plp.related_id=c.id AND plp.prospect_list_id = %1\$s AND plp.related_type = '$module' AND plp.deleted=0";
        BeanFactory::newBean($module)->addVisibilityFrom($contactsQuery, ['table_alias' => 'c']);
        $contactsQuery .= "
            LEFT JOIN accounts_contacts ac ON ac.contact_id=c.id 
            LEFT JOIN accounts a ON ac.account_id=a.id AND ac.deleted=0
            LEFT JOIN email_addr_bean_rel ear ON ear.bean_id=c.id AND ear.bean_module = 'Contacts' AND ear.deleted=0
            LEFT JOIN email_addresses ea ON ear.email_address_id=ea.id
            WHERE c.deleted=0
            AND (ear.deleted=0 OR ear.deleted IS NULL)";
        BeanFactory::newBean($module)->addVisibilityWhere($contactsQuery, ['table_alias' => 'c']);

        $module = 'Prospects';
        $prospectsQuery = "SELECT p.id AS id, '$module' AS related_type, '' AS name, p.first_name AS first_name, 
                p.last_name AS last_name,p.title AS title, p.salutation AS salutation,
                p.primary_address_street AS primary_address_street,p.primary_address_city AS primary_address_city, 
                p.primary_address_state AS primary_address_state,  
                p.primary_address_postalcode AS primary_address_postalcode, 
                p.primary_address_country AS primary_address_country,
                p.account_name AS account_name, ea.email_address AS email_address, ea.invalid_email AS invalid_email, 
                ea.opt_out AS opt_out, ea.deleted AS ea_deleted, ear.deleted AS ear_deleted, 
                ear.primary_address AS primary_address, p.do_not_call AS do_not_call, p.phone_fax AS phone_fax, 
                p.phone_other AS phone_other, p.phone_home AS phone_home, p.phone_mobile AS phone_mobile, 
                p.phone_work AS phone_work
            FROM prospect_lists_prospects plp
            INNER JOIN prospects p ON plp.related_id=p.id AND plp.prospect_list_id = %1\$s AND plp.related_type = '$module' AND plp.deleted=0";
        BeanFactory::newBean($module)->addVisibilityFrom($prospectsQuery, ['table_alias' => 'p']);
        $prospectsQuery .= "
            LEFT JOIN email_addr_bean_rel ear ON  ear.bean_id=p.id AND ear.bean_module = '$module' AND ear.deleted=0
            LEFT JOIN email_addresses ea ON ear.email_address_id=ea.id
            WHERE p.deleted=0
            AND (ear.deleted=0 OR ear.deleted IS NULL)";
        BeanFactory::newBean($module)->addVisibilityWhere($prospectsQuery, ['table_alias' => 'p']);

        $module = 'Accounts';
        $accountsQuery = "SELECT a.id AS id, '$module' AS related_type, a.name AS name, '' AS first_name, '' AS last_name,
                '' AS title, '' AS salutation,  a.billing_address_street AS primary_address_street,
                a.billing_address_city AS primary_address_city, a.billing_address_state AS primary_address_state, 
                a.billing_address_postalcode AS primary_address_postalcode, 
                a.billing_address_country AS primary_address_country, '' AS account_name, 
                ea.email_address AS email_address, ea.invalid_email AS invalid_email, ea.opt_out AS opt_out, 
                ea.deleted AS ea_deleted, ear.deleted AS ear_deleted, ear.primary_address AS primary_address, 
                0 AS do_not_call, a.phone_fax as phone_fax, a.phone_alternate AS phone_other, '' AS phone_home, 
                '' AS phone_mobile, a.phone_office AS phone_office
            FROM prospect_lists_prospects plp
            INNER JOIN accounts a ON plp.related_id=a.id AND plp.prospect_list_id = %1\$s AND plp.related_type = '$module' AND plp.deleted=0 ";
        BeanFactory::newBean($module)->addVisibilityFrom($accountsQuery, ['table_alias' => 'a']);
        $accountsQuery .= "
            LEFT JOIN email_addr_bean_rel ear ON  ear.bean_id=a.id AND ear.bean_module = '$module' AND ear.deleted=0
            LEFT JOIN email_addresses ea ON ear.email_address_id=ea.id
            WHERE a.deleted=0
            AND (ear.deleted=0 OR ear.deleted IS NULL)";

        BeanFactory::newBean($module)->addVisibilityWhere($accountsQuery, ['table_alias' => 'a']);
        return sprintf(
            $leadsQuery . '
        UNION ALL ' . $usersQuery . '
        UNION ALL ' . $contactsQuery . '
        UNION ALL ' . $prospectsQuery . '
        UNION ALL ' . $accountsQuery . '
        ORDER BY related_type, id, primary_address DESC',
            $this->db->quoted($record_id)
        );
    }


    /**
     * @param string $record_id
     * @return SugarQuery
     * @throws SugarQueryException
     */
    public function create_export_members_by_sugar_query(string $record_id)
    {
        // Leads
        $queryLeads = new SugarQuery();
        $module = 'Leads';

        $queryLeads->select->fieldRaw("l.id", 'id');
        $queryLeads->select->fieldRaw("'$module'", 'related_type');
        $queryLeads->select->fieldRaw("''", 'name');
        $queryLeads->select->fieldRaw("l.first_name", 'first_name');
        $queryLeads->select->fieldRaw("l.last_name", 'last_name');
        $queryLeads->select->fieldRaw("l.title", 'title');
        $queryLeads->select->fieldRaw("l.salutation", 'salutation');
        $queryLeads->select->fieldRaw("l.primary_address_street", 'primary_address_street');
        $queryLeads->select->fieldRaw("l.primary_address_city", 'primary_address_city');
        $queryLeads->select->fieldRaw("l.primary_address_state", 'primary_address_state');
        $queryLeads->select->fieldRaw("l.primary_address_postalcode", 'primary_address_postalcode');
        $queryLeads->select->fieldRaw("l.primary_address_country", 'primary_address_country');
        $queryLeads->select->fieldRaw("l.account_name", 'account_name');
        $queryLeads->select->fieldRaw("ea.email_address", 'primary_email_address');
        $queryLeads->select->fieldRaw("ea.invalid_email", 'invalid_email');
        $queryLeads->select->fieldRaw("ea.opt_out", 'opt_out');
        $queryLeads->select->fieldRaw("ea.deleted", 'ea_deleted');
        $queryLeads->select->fieldRaw("ear.deleted", 'ear_deleted');
        $queryLeads->select->fieldRaw("ear.primary_address", 'primary_address');
        $queryLeads->select->fieldRaw("l.do_not_call", 'do_not_call');
        $queryLeads->select->fieldRaw("l.phone_fax", 'phone_fax');
        $queryLeads->select->fieldRaw("l.phone_other", 'phone_other');
        $queryLeads->select->fieldRaw("l.phone_home", 'phone_home');
        $queryLeads->select->fieldRaw("l.phone_mobile", 'phone_mobile');
        $queryLeads->select->fieldRaw("l.phone_work", 'phone_work');

        $queryLeads->from(BeanFactory::newBean($module), ['alias' => 'l']);
        $this->setJoinsAndConditions($queryLeads, $record_id, $module, 'l');

        // Users
        $queryUsers = new SugarQuery();
        $module = 'Users';

        $queryUsers->select->fieldRaw('u.id', 'id');
        $queryUsers->select->fieldRaw("'$module'", 'related_type');
        $queryUsers->select->fieldRaw("''", 'name');
        $queryUsers->select->fieldRaw('u.first_name', 'first_name');
        $queryUsers->select->fieldRaw('u.last_name', 'last_name');
        $queryUsers->select->fieldRaw('u.title', 'title');
        $queryUsers->select->fieldRaw("''", 'salutation');
        $queryUsers->select->fieldRaw('u.address_street', 'primary_address_street');
        $queryUsers->select->fieldRaw('u.address_city', 'primary_address_city');
        $queryUsers->select->fieldRaw('u.address_state', 'primary_address_state');
        $queryUsers->select->fieldRaw('u.address_postalcode', 'primary_address_postalcode');
        $queryUsers->select->fieldRaw('u.address_country', 'primary_address_country');
        $queryUsers->select->fieldRaw("''", 'account_name');
        $queryUsers->select->fieldRaw('ea.email_address', 'email_address');
        $queryUsers->select->fieldRaw('ea.invalid_email', 'invalid_email');
        $queryUsers->select->fieldRaw('ea.opt_out', 'opt_out');
        $queryUsers->select->fieldRaw('ea.deleted', 'ea_deleted');
        $queryUsers->select->fieldRaw('ear.deleted', 'ear_deleted');
        $queryUsers->select->fieldRaw('ear.primary_address', 'primary_address');
        $queryUsers->select->fieldRaw('0', 'do_not_call');
        $queryUsers->select->fieldRaw('u.phone_fax', 'phone_fax');
        $queryUsers->select->fieldRaw('u.phone_other', 'phone_other');
        $queryUsers->select->fieldRaw('u.phone_home', 'phone_home');
        $queryUsers->select->fieldRaw('u.phone_mobile', 'phone_mobile');
        $queryUsers->select->fieldRaw('u.phone_work', 'phone_work');

        $queryUsers->from(BeanFactory::newBean($module), ['alias' => 'u']);
        $this->setJoinsAndConditions($queryUsers, $record_id, $module, 'u');

        // Contacts
        $queryContacts = new SugarQuery();
        $module = 'Contacts';

        $queryContacts->select->fieldRaw('c.id', 'id');
        $queryContacts->select->fieldRaw("'$module'", 'related_type');
        $queryContacts->select->fieldRaw("''", 'name');
        $queryContacts->select->fieldRaw('c.first_name', 'first_name');
        $queryContacts->select->fieldRaw('c.last_name', 'last_name');
        $queryContacts->select->fieldRaw('c.title', 'title');
        $queryContacts->select->fieldRaw('c.salutation', 'salutation');
        $queryContacts->select->fieldRaw('c.primary_address_street', 'primary_address_street');
        $queryContacts->select->fieldRaw('c.primary_address_city', 'primary_address_city');
        $queryContacts->select->fieldRaw('c.primary_address_state', 'primary_address_state');
        $queryContacts->select->fieldRaw('c.primary_address_postalcode', 'primary_address_postalcode');
        $queryContacts->select->fieldRaw('c.primary_address_country', 'primary_address_country');
        $queryContacts->select->fieldRaw('a.name', 'account_name');
        $queryContacts->select->fieldRaw('ea.email_address', 'email_address');
        $queryContacts->select->fieldRaw('ea.invalid_email', 'invalid_email');
        $queryContacts->select->fieldRaw('ea.opt_out', 'opt_out');
        $queryContacts->select->fieldRaw('ea.deleted', 'ea_deleted');
        $queryContacts->select->fieldRaw('ear.deleted', 'ear_deleted');
        $queryContacts->select->fieldRaw('ear.primary_address', 'primary_address');
        $queryContacts->select->fieldRaw('c.do_not_call', 'do_not_call');
        $queryContacts->select->fieldRaw('c.phone_fax', 'phone_fax');
        $queryContacts->select->fieldRaw('c.phone_other', 'phone_other');
        $queryContacts->select->fieldRaw('c.phone_home', 'phone_home');
        $queryContacts->select->fieldRaw('c.phone_mobile', 'phone_mobile');
        $queryContacts->select->fieldRaw('c.phone_work', 'phone_work');

        $queryContacts->from(BeanFactory::newBean($module), ['alias' => 'c']);
        $this->setJoinsAndConditions($queryContacts, $record_id, $module, 'c');
        $queryContacts->joinTable('accounts_contacts', ['alias' => 'ac', 'joinType' => 'LEFT', 'linkingTable' => true])
            ->on()->equalsField('c.id', 'ac.contact_id');
        $queryContacts->joinTable('accounts', ['alias' => 'a', 'joinType' => 'LEFT', 'linkingTable' => true])
            ->on()->equalsField('a.id', 'ac.account_id')->equals('ac.deleted', 0);

        // Prospects
        $queryProspects = new SugarQuery();
        $module = 'Prospects';

        $queryProspects->select->fieldRaw('p.id', 'id');
        $queryProspects->select->fieldRaw("'$module'", 'related_type');
        $queryProspects->select->fieldRaw("''", 'name');
        $queryProspects->select->fieldRaw('p.first_name', 'first_name');
        $queryProspects->select->fieldRaw('p.last_name', 'last_name');
        $queryProspects->select->fieldRaw('p.title', 'title');
        $queryProspects->select->fieldRaw('p.salutation', 'salutation');
        $queryProspects->select->fieldRaw('p.primary_address_street', 'primary_address_street');
        $queryProspects->select->fieldRaw('p.primary_address_city', 'primary_address_city');
        $queryProspects->select->fieldRaw('p.primary_address_state', 'primary_address_state');
        $queryProspects->select->fieldRaw('p.primary_address_postalcode', 'primary_address_postalcode');
        $queryProspects->select->fieldRaw('p.primary_address_country', 'primary_address_country');
        $queryProspects->select->fieldRaw('p.account_name', 'account_name');
        $queryProspects->select->fieldRaw('ea.email_address', 'email_address');
        $queryProspects->select->fieldRaw('ea.invalid_email', 'invalid_email');
        $queryProspects->select->fieldRaw('ea.opt_out', 'opt_out');
        $queryProspects->select->fieldRaw('ea.deleted', 'ea_deleted');
        $queryProspects->select->fieldRaw('ear.deleted', 'ear_deleted');
        $queryProspects->select->fieldRaw('ear.primary_address', 'primary_address');
        $queryProspects->select->fieldRaw('p.do_not_call', 'do_not_call');
        $queryProspects->select->fieldRaw('p.phone_fax', 'phone_fax');
        $queryProspects->select->fieldRaw('p.phone_other', 'phone_other');
        $queryProspects->select->fieldRaw('p.phone_home', 'phone_home');
        $queryProspects->select->fieldRaw('p.phone_mobile', 'phone_mobile');
        $queryProspects->select->fieldRaw('p.phone_work', 'phone_work');

        $queryProspects->from(BeanFactory::newBean($module), ['alias' => 'p']);
        $this->setJoinsAndConditions($queryProspects, $record_id, $module, 'p');

        // Accounts
        $module = 'Accounts';
        $queryAccounts = new SugarQuery();

        $queryAccounts->select->fieldRaw('a.id', 'id');
        $queryAccounts->select->fieldRaw("'$module'", 'related_type');
        $queryAccounts->select->fieldRaw('a.name', 'name');
        $queryAccounts->select->fieldRaw("''", 'first_name');
        $queryAccounts->select->fieldRaw("''", 'last_name');
        $queryAccounts->select->fieldRaw("''", 'title');
        $queryAccounts->select->fieldRaw("''", 'salutation');
        $queryAccounts->select->fieldRaw('a.billing_address_street', 'primary_address_street');
        $queryAccounts->select->fieldRaw('a.billing_address_city', 'primary_address_city');
        $queryAccounts->select->fieldRaw('a.billing_address_state', 'primary_address_state');
        $queryAccounts->select->fieldRaw('a.billing_address_postalcode', 'primary_address_postalcode');
        $queryAccounts->select->fieldRaw('a.billing_address_country', 'primary_address_country');
        $queryAccounts->select->fieldRaw("''", 'account_name');
        $queryAccounts->select->fieldRaw('ea.email_address', 'email_address');
        $queryAccounts->select->fieldRaw('ea.invalid_email', 'invalid_email');
        $queryAccounts->select->fieldRaw('ea.opt_out', 'opt_out');
        $queryAccounts->select->fieldRaw('ea.deleted', 'ea_deleted');
        $queryAccounts->select->fieldRaw('ear.deleted', 'ear_deleted');
        $queryAccounts->select->fieldRaw('ear.primary_address', 'primary_address');
        $queryAccounts->select->fieldRaw('0', 'do_not_call');
        $queryAccounts->select->fieldRaw('a.phone_fax', 'phone_fax');
        $queryAccounts->select->fieldRaw('a.phone_alternate', 'phone_other');
        $queryAccounts->select->fieldRaw("''", 'phone_home');
        $queryAccounts->select->fieldRaw("''", 'phone_mobile');
        $queryAccounts->select->fieldRaw('a.phone_office', 'phone_office');

        $queryAccounts->from(BeanFactory::newBean($module), ['alias' => 'a']);
        $this->setJoinsAndConditions($queryAccounts, $record_id, $module, 'a');

        $queryUnion = new SugarQuery();
        $queryUnion->union($queryLeads);
        $queryUnion->union($queryUsers);
        $queryUnion->union($queryContacts);
        $queryUnion->union($queryProspects);
        $queryUnion->union($queryAccounts);
        $queryUnion->orderBy('related_type');
        $queryUnion->orderBy('id');
        $queryUnion->orderBy('primary_address');

        return $queryUnion;
    }

    /**
     * Set joins and conditions for the SugarQuery object.
     *
     * @param SugarQuery $q The SugarQuery object to modify.
     * @param string $recordId The record ID of the prospect list.
     * @param string $module The module name of the related records.
     * @param string $alias The alias for the main table in the query.
     */
    private function setJoinsAndConditions(SugarQuery &$q, string $recordId, string $module, string $alias)
    {
        $q->joinTable('prospect_lists_prospects', ['alias' => 'plp'])->on()
            ->equalsField('plp.related_id', $alias . '.id')
            ->equals('plp.prospect_list_id', $recordId)
            ->equals('plp.related_type', $module)
            ->equals('plp.deleted', 0);

        $q->joinTable('email_addr_bean_rel', ['alias' => 'ear', 'joinType' => 'LEFT', 'linkingTable' => true])
            ->on()->equalsField($alias . '.id', 'ear.bean_id')->equals('ear.bean_module', $module)->equals('ear.deleted', 0);

        $q->joinTable('email_addresses', ['alias' => 'ea', 'joinType' => 'LEFT', 'linkingTable' => true])
            ->on()->equalsField('ear.email_address_id', 'ea.id');

        $q->where()->equals($alias . '.deleted', 0);
        $q->where()->queryOr()->equals('ear.deleted', 0)->isNull('ear.deleted');
    }

    public function save_relationship_changes($is_update, $exclude = [])
    {
        parent::save_relationship_changes($is_update, $exclude);
        if ($this->lead_id != '') {
            $this->set_prospect_relationship($this->id, $this->lead_id, 'lead');
        }
        if ($this->contact_id != '') {
            $this->set_prospect_relationship($this->id, $this->contact_id, 'contact');
        }
        if ($this->prospect_id != '') {
            $this->set_prospect_relationship($this->id, $this->contact_id, 'prospect');
        }
    }

    public function set_prospect_relationship($prospect_list_id, &$link_ids, $link_name)
    {
        $link_field = sprintf('%s_id', $link_name);

        foreach ($link_ids as $link_id) {
            $this->set_relationship('prospect_lists_prospects', [$link_field => $link_id, 'prospect_list_id' => $prospect_list_id]);
        }
    }

    public function set_prospect_relationship_single($prospect_list_id, $link_id, $link_name)
    {
        $link_field = sprintf('%s_id', $link_name);

        $this->set_relationship('prospect_lists_prospects', [$link_field => $link_id, 'prospect_list_id' => $prospect_list_id]);
    }


    public function clear_prospect_relationship($prospect_list_id, $link_id, $link_name)
    {
        $link_field = sprintf('%s_id', $link_name);
        $where_clause = " AND $link_field = '$link_id' ";

        $query = sprintf("DELETE FROM prospect_lists_prospects WHERE prospect_list_id='%s' AND deleted = '0' %s", $prospect_list_id, $where_clause);

        $this->db->query($query, true, 'Error clearing prospect/prospect_list relationship: ');
    }


    public function fill_in_additional_list_fields()
    {
    }

    public function updateRelatedCalcFields($linkName = '')
    {
        parent::updateRelatedCalcFields($linkName);
        $this->entry_count = $this->get_entry_count();
    }


    public function update_currency_id($fromid, $toid)
    {
    }


    public function get_entry_count()
    {
        $query = "SELECT count(*) AS num FROM prospect_lists_prospects WHERE prospect_list_id= ? AND deleted = '0'";
        $count = $this->db->getConnection()
            ->executeQuery($query, [$this->id])
            ->fetchOne();
        return $count;
    }


    public function get_list_view_data($filter_fields = [])
    {
        $nonce = Nonce::create();
        $mod_strings = [];
        $temp_array = $this->get_list_view_array();
        $temp_array['ENTRY_COUNT'] = $this->get_entry_count();
        $this->load_relationship('teams');
        require_once 'modules/Teams/TeamSetManager.php';
        $teams = TeamSetManager::getTeamsFromSet($this->team_set_id);

        if (safeCount($teams) > 1) {
            $temp_array['TEAM_NAME'] .= "<span id='div_{$this->id}_teams'>
						<a href=\"#\" data-onmouseover-{$nonce}=\"javascript:toggleMore('div_{$this->id}_teams','img_{$this->id}_teams', 'Teams', 'DisplayInlineTeams', 'team_set_id={$this->team_set_id}&team_id={$this->team_id}');\"  data-onfocus-{$nonce}=\"javascript:toggleMore('div_{$this->id}_teams','img_{$this->id}_teams', 'Teams', 'DisplayInlineTeams', 'team_set_id={$this->team_set_id}');\" id='more_feather' class=\"utilsLink\">
					  " . SugarThemeRegistry::current()->getImage('MoreDetail', "style='padding: 0px 0px 0px 0px' border='0'", 8, 7, '.gif', $mod_strings['LBL_MORE_DETAIL']) . '
						</a>
						</span>';
        }
        return $temp_array;
    }

    /**
     * builds a generic search based on the query string using or
     * do not include any $this-> because this is called on without having the class instantiated
     */
    public function build_generic_where_clause($the_query_string)
    {
        $where_clauses = [];
        $the_query_string = $GLOBALS['db']->quote($the_query_string);
        array_push($where_clauses, "prospect_lists.name like '$the_query_string%'");

        $the_where = '';
        foreach ($where_clauses as $clause) {
            if ($the_where != '') {
                $the_where .= ' or ';
            }
            $the_where .= $clause;
        }


        return $the_where;
    }

    public function save($check_notify = false)
    {

        return parent::save($check_notify);
    }

    public function bean_implements($interface)
    {
        switch ($interface) {
            case 'ACL':
                return true;
        }
        return false;
    }
}
