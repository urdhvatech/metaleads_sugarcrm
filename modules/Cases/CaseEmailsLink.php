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
 * Links connected emails for a case
 */
class CaseEmailsLink extends ArchivedEmailsBeanLink
{
    public function __construct($linkName, $bean, $linkDef = false)
    {
        parent::__construct($linkName, $bean, $linkDef);
        $this->addSource = true;
    }

    protected function joinEmails(SugarQuery $query, $fromAlias, $alias)
    {
        $unionQuery = $this->buildCaseEmailsUnionQuery();

        if ($unionQuery !== null) {
            $join = $query->joinTable($unionQuery, ['alias' => $alias]);
            $join->on()
                ->equalsField($fromAlias . '.id', $alias . '.email_id')
                ->equals($alias . '.id', $this->focus->id);
            return $join;
        }

        return parent::joinEmails($query, $fromAlias, $alias);
    }

    /**
     * Build the UNION subquery for case emails
     * @return string|null The UNION query or null if not applicable
     */
    protected function buildCaseEmailsUnionQuery()
    {
        if (!($this->focus instanceof aCase) || empty($this->focus->case_number)) {
            return null;
        }

        // Ensure proper escaping of all values
        $db = DBManagerFactory::getInstance();
        $bean_id = $db->quoted($this->focus->id);
        $bean_module = $db->quoted($this->focus->module_dir);

        // Build the case pattern with proper escaping
        $case_macro = str_replace('%1', $this->focus->case_number, $this->focus->getEmailSubjectMacro());
        $case_pattern = $db->sqlLikeString($case_macro, '%', false);
        $case_pattern_quoted = $db->quoted('%' . $case_pattern . '%');
        $existsSubQuery = self::getExistsSubQuery($bean_id);

        // Get relation for contact emails
        $relation = $this->def['link'];
        $this->focus->load_relationship($relation);

        // Build UNION query that combines:
        // 1. Direct emails (source=1 equivalent)
        // 2. Subject-matched emails with all related sources
        $sql = "
            SELECT eb.bean_id AS id, eb.email_id
            FROM emails_beans eb
            WHERE 
                eb.bean_module = {$bean_module} AND
                eb.bean_id = {$bean_id} AND
                eb.deleted = 0
            UNION
            SELECT {$bean_id} AS id, emails.id AS email_id
            FROM emails
            WHERE 
                emails.deleted = 0 AND
                emails.name LIKE {$case_pattern_quoted} AND
                EXISTS ($existsSubQuery)
        ";

        return "($sql)";
    }

    /**
     * We need this one because cases have match by subject macro
     * @see ArchivedEmailsBeanLink::getEmailsJoin()
     */
    protected function getEmailsJoin($params = [])
    {
        $unionQuery = $this->buildCaseEmailsUnionQuery();

        if ($unionQuery !== null) {
            $table_name = !empty($params['join_table_alias']) ? $params['join_table_alias'] : 'emails';
            $bean_id = $this->db->quoted($this->focus->id);

            return "INNER JOIN ({$unionQuery}) email_ids ON {$table_name}.id = email_ids.email_id AND email_ids.id = {$bean_id}";
        }

        return parent::getEmailsJoin($params);
    }

    /**
     * Build the complex EXISTS subquery with 3 UNION ALL parts to match Emails related to a Case.
     *
     * This includes:
     *  - Direct email-address relationship to the Case
     *  - Emails linked to Contacts related to the Case
     *  - Emails related via shared email addresses with linked Contacts
     *
     * @param string $quotedCaseId Case ID, already quoted via DBManager (e.g., $db->quoted($id))
     * @param string|null $emailAlias Optional alias of the outer emails table to correlate (default 'emails')
     * @return string SQL subquery string to be used inside an EXISTS clause
     */
    public static function getExistsSubQuery(string $quotedCaseId, ?string $emailAlias = 'emails'): string
    {
        $parts = [];
        $emailLinkSql = $emailAlias ? $emailAlias . '.id = email_link.email_id AND' : '';

        // 1. Related directly by email address
        $parts[] = "
            SELECT email_id FROM emails_email_addr_rel email_link
            INNER JOIN email_addr_bean_rel eabr ON 
                eabr.bean_id = {$quotedCaseId} AND 
                eabr.bean_module = 'Cases' AND 
                eabr.email_address_id = email_link.email_address_id AND 
                eabr.deleted = 0
            WHERE {$emailLinkSql}
                email_link.deleted = 0
        ";

        // Skip contact-related logic if disabled in config
        if (!empty($GLOBALS['sugar_config']['hide_history_contacts_emails']['Cases'])) {
            return $parts[0];
        }

        // 2. Emails linked to contacts assigned to the case
        $parts[] = "
            SELECT email_id FROM emails_beans email_link
            INNER JOIN contacts_cases linkt ON 
                {$quotedCaseId} = linkt.case_id AND 
                linkt.deleted = 0
            INNER JOIN contacts link_bean ON 
                link_bean.id = linkt.contact_id AND 
                link_bean.deleted = 0 AND 
                link_bean.id = email_link.bean_id
            WHERE {$emailLinkSql}
                email_link.bean_module = 'Contacts' AND 
                email_link.deleted = 0
        ";

        // 3. Emails related by email address to linked contacts
        $parts[] = "
            SELECT email_id FROM emails_email_addr_rel email_link
            INNER JOIN email_addr_bean_rel eabr ON 
                eabr.email_address_id = email_link.email_address_id AND 
                eabr.bean_module = 'Contacts' AND 
                eabr.deleted = 0
            INNER JOIN contacts_cases linkt ON 
                {$quotedCaseId} = linkt.case_id AND 
                linkt.deleted = 0
            INNER JOIN contacts link_bean ON 
                link_bean.id = linkt.contact_id AND 
                link_bean.deleted = 0 AND 
                link_bean.id = eabr.bean_id
            WHERE {$emailLinkSql}
                email_link.deleted = 0 
        ";

        return implode("UNION ALL", $parts);
    }
}
