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

use Sugarcrm\Sugarcrm\IdentityProvider\Authentication\Config;
use Sugarcrm\IdentityProvider\Srn;

class UsersApiHelper extends SugarBeanApiHelper
{
    /**
     * @var Config
     */
    protected $idpConfig = null;

    /**
     * Fields that should only be returned to admin users
     * @var array
     */
    protected $adminOnlyFields = [
        'calendar_publish_key',
    ];

    /**
     * Fields that should not be returned to any users
     * @var array
     */
    protected $noAccessFields = [
        'user_hash',
    ];

    /**
     * Formats the bean so it is ready to be handed back to the API's client.
     * Checks if user has access to a given record (if record module/id is specified in the api args)
     *
     * @param $bean SugarBean The bean you want formatted
     * @param $fieldList array Which fields do you want formatted and returned (leave blank for all fields)
     * @param $options array Currently no options are supported
     * @return array The bean in array format, ready for passing out the API to clients.
     */
    public function formatForApi(SugarBean $bean, array $fieldList = [], array $options = [])
    {
        if ($bean instanceof User) {
            $bean->populateEmailCredentials();
        }

        $data = parent::formatForApi($bean, $fieldList, $options);
        $data = array_merge($data, $this->getUserPreferenceFields($bean, $fieldList));

        $cloudConsoleUserLink = $this->getCloudConsoleLink($bean);
        if (is_string($cloudConsoleUserLink)) {
            $data['cloud_console_user_link'] = $cloudConsoleUserLink;
        }

        $args = $options['args'] ?? [];
        if (!empty($args['has_access_module']) && !empty($args['has_access_record'])) {
            $data['has_access'] = $this->checkUserAccess($bean, $args['has_access_module'], $args['has_access_record']);
        }

        // filter out calendar_publish_key for non-admin users
        return $this->removeSensitiveFields($data, $bean);
    }

    /**
     * Checks if the current user has admin privilige to access to the Users module
     *
     * @return bool
     */
    protected function hasAdminAccess(): bool
    {
        global $current_user;
        if (empty($current_user)) {
            return false;
        }
        return $current_user->isAdminForModule('Users') || $current_user->isDeveloperForModule('Users');
    }

    /**
     * Checks if the current user is the same as the user being formatted
     *
     * @param SugarBean $bean
     * @return bool
     */
    protected function sameUser(SugarBean $bean): bool
    {
        global $current_user;
        return !empty($current_user) && $current_user->id === $bean->id;
    }

    /**
     * remove sensitive fields for non-admin users
     * @param array $data
     * @return array
     */
    protected function removeSensitiveFields(array $data, SugarBean $bean): array
    {
        foreach ($this->noAccessFields as $field) {
            if (isset($data[$field])) {
                unset($data[$field]);
            }
        }

        if ($this->hasAdminAccess() || $this->sameUser($bean)) {
            return $data;
        }

        foreach ($this->adminOnlyFields as $field) {
            if (isset($data[$field])) {
                unset($data[$field]);
            }
        }
        return $data;
    }

    /**
     * Retrieves the link to the IDM cloud console page for the given user
     *
     * @param SugarBean $bean the User for which to build the cloud console User link
     * @return string|null the cloud console link
     */
    protected function getCloudConsoleLink(SugarBean $bean) : ?string
    {
        global $current_user;

        $idpConfig = $this->getIdpConfig();
        if ($idpConfig->isIDMModeEnabled() && $current_user->isAdmin()) {
            $tenantSrn = Srn\Converter::fromString($idpConfig->getIDMModeConfig()['tid']);
            $srnManager = new Srn\Manager([
                'partition' => $tenantSrn->getPartition(),
                'region' => $tenantSrn->getRegion(),
            ]);
            $userSrn = $srnManager->createUserSrn($tenantSrn->getTenantId(), $bean->id);

            return $idpConfig->buildCloudConsoleUrl(
                'userProfile',
                [Srn\Converter::toString($userSrn)],
                $current_user->id
            );
        }

        return null;
    }

    /**
     * Retrieves values for any user preference fields that have been requested
     *
     * @param SugarBean $bean the User bean being updated
     * @param array $fieldList the list of fields requested through the API
     * @return array the map of {User preference field} => {Preference value}
     */
    protected function getUserPreferenceFields(SugarBean $bean, array $fieldList = [])
    {
        $result = [];
        $settingsHelper = new UserPreferenceFieldsHelper();
        $settingsFields = $bean->getFieldDefinitions('user_preference', [true]);
        foreach ($fieldList as $fieldName) {
            if (!empty($settingsFields[$fieldName]) && $bean->ACLFieldAccess($fieldName, 'read')) {
                $result[$fieldName] = $settingsHelper->getPreferenceField($bean, $fieldName) ?? '';
            }
        }
        return $result;
    }

    /**
     * Uses the checkUserAccess SugarBean method to see if the user being formatted has access to the given record
     *
     * @param SugarBean $user The user to check access for
     * @param $module The module of the record we're checking access to
     * @param $recordId The id of the record we're checking access to
     * @return bool True if the user has access, false otherwise
     */
    protected function checkUserAccess(SugarBean $user, $module, $recordId)
    {
        $record = $this->getBean($module, $recordId);
        $record->disable_row_level_security = false;

        return $record->checkUserAccess($user);
    }

    /**
     * Wrapper for BeanFactory::getBean
     *
     * @param string $module The module name
     * @param string $recordId The record id
     * @return SugarBean|null
     */
    protected function getBean(string $module, $recordId): SugarBean
    {
        return BeanFactory::getBean($module, $recordId);
    }

    public function populateFromApi(SugarBean $bean, array $submittedData, array $options = [])
    {
        if ($this->getIdpConfig()->isIDMModeEnabled()
            && empty($submittedData['skip_idm_mode_restrictions'])) {
            $submittedData = $this->filterIDMModeDisabledFields($bean, $submittedData);
        }
        parent::populateFromApi($bean, $submittedData, $options);
        if (!$bean->new_with_id && !empty($bean->id)) {
            return true;
        }

        if (empty($submittedData) || empty($submittedData['user_name'])) {
            throw new SugarApiExceptionMissingParameter('Missing username');
        }

        return true;
    }

    /**
     * deny edit non-editable fields
     * @param SugarBean $bean
     * @param array $submittedData
     * @return array
     */
    protected function filterIDMModeDisabledFields(SugarBean $bean, array $submittedData)
    {
        $submittedData = array_diff_key($submittedData, $this->getIdpConfig()->getIDMModeDisabledFields(['email']));
        if (!empty($submittedData['email'])) {
            $submittedData['email'] = $this->filterEmailField($bean, $submittedData['email']);
        }
        return $submittedData;
    }

    /**
     * filter emails
     * @param SugarBean $bean
     * @param array $emails
     * @return array
     */
    protected function filterEmailField(SugarBean $bean, array $emails)
    {
        $primaryAddress = $bean->emailAddress->getPrimaryAddress($bean);
        $primaryAddressExists = false;
        foreach ($emails as $key => $email) {
            if (empty($email['email_address'])) {
                unset($emails[$key]);
                continue;
            }

            if (!empty($email['primary_address']) && strcasecmp($primaryAddress, $email['email_address']) != 0) {
                unset($emails[$key]);
                continue;
            }

            $emails[$key]['primary_address'] = false;
            if (strcasecmp($primaryAddress, $email['email_address']) == 0) {
                $emails[$key]['primary_address'] = true;
                $primaryAddressExists = true;
            }
        }
        if (!$primaryAddressExists) {
            $emails[] = $this->getPrimaryEmailAddressInApiFormat($bean);
        }
        return $emails;
    }

    /**
     * return user primary address in API format
     * @param SugarBean $bean
     * @return array
     */
    protected function getPrimaryEmailAddressInApiFormat(SugarBean $bean)
    {
        $bean->load_relationship('email_addresses_primary');
        $primaryEmailAddresses = $bean->email_addresses_primary->getBeans();
        $primaryEmailAddress = array_pop($primaryEmailAddresses);
        $rawData = array_pop($bean->email_addresses_primary->rows);
        return [
            'email_address_id' => $primaryEmailAddress->id,
            'email_address' => $primaryEmailAddress->email_address,
            'invalid_email' => (bool)$primaryEmailAddress->invalid_email,
            'opt_out' => (bool)$primaryEmailAddress->opt_out,
            'reply_to_address' => (bool)$rawData['reply_to_address'],
            'primary_address' => true,
        ];
    }

    /**
     * Return idp config
     * @return Config
     */
    protected function getIdpConfig()
    {
        if (is_null($this->idpConfig)) {
            $this->idpConfig = new Config(\SugarConfig::getInstance());
        }
        return $this->idpConfig;
    }
}
