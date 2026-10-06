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

use GuzzleHttp\Client as GuzzleClient;
use Sugarcrm\Sugarcrm\GAI\Configuration\ConfigurationOnPremAdapter;

class ext_eapm_gai extends source
{
    protected $required_config_fields = [
        'oauth2_client_id',
        'oauth2_client_secret',
    ];

    /**
     * Overrides parent __construct to set new variable defaults
     */
    public function __construct()
    {
        parent::__construct();
        $this->_enable_in_wizard = false;
        $this->_enable_in_hover = false;
        $this->_has_testing_enabled = true;
    }

    /**
     * getItem is not used by this connector
     */
    public function getItem($args = [], $module = null)
    {
    }

    /**
     * getList is not used by this connector
     */
    public function getList($args = [], $module = null)
    {
    }

    /**
     * @inheritDoc
     */
    public function isRequiredConfigFieldsSet()
    {
        //Check if required fields are set
        foreach ($this->required_config_fields as $field) {
            if (isset($this->visibilityCheckBoxConfigForFields[$field])) {
                continue;
            }

            foreach ($this->visibilityCheckBoxConfigForFields as $checkBoxField => $checkBoxFields) {
                if (safeInArray($field, $checkBoxFields) && empty($this->_config['properties'][$checkBoxField])) {
                    continue(2);
                }
            }

            if (empty($this->_config['properties'][$field])) {
                return false;
            }
        }
        return true;
    }


     /**
     * This method is called from the administration interface to run a test of the service
     * It is up to subclasses to implement a test and set _has_testing_enabled to true so that
     * a test button is rendered in the administration interface
     *
     * @return result boolean result of the test function
     */
    public function test()
    {
        $configurationAdapter = null;
        $properties = $this->getProperties();

        if (!empty($properties) && !empty($properties['oauth2_client_id']) && !empty($properties['oauth2_client_secret'])) {
            $configurationAdapter = new ConfigurationOnPremAdapter(\SugarConfig::getInstance()) ;
        }

        $client = new GuzzleClient();

        if ($configurationAdapter) {
            try {
                $scope = $configurationAdapter->getScope();
                $grant_type = $configurationAdapter->getGrantType();
                $testingAccessToken = true;

                $response = $configurationAdapter->fetchAccessTokenInfo(
                    [
                        'grant_type' => $grant_type,
                        'client_id' => $properties['oauth2_client_id'],
                        'client_secret' => $properties['oauth2_client_secret'],
                        'scope' => $scope,
                    ],
                    $testingAccessToken
                );

                if (!isset($response['access_token'])) {
                    return false;
                }

                return true;
            } catch (Exception $e) {
                $GLOBALS['log']->error('Error getting request token for gai:' . $e->getMessage());
                return false;
            }
        } else {
            // Instance is not on-premise so can't test
            return false;
        }
    }
}
