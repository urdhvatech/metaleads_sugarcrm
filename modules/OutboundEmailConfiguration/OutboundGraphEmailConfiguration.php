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

//use MailerException;
//use OutboundEmailConfiguration;
//use OutboundEmailConfigurationPeer;

/**
 * Represents the configurations and contains the logic for setting the configurations for an Graph API Mailer.
 *
 * @extends OutboundEmailConfiguration
 */
class OutboundGraphEmailConfiguration extends OutboundEmailConfiguration
{
    private $eapmId;  // the ID of the EAPM bean holding Oauth2 information to use if applicable

    /**
     * Extends the default configurations for this sending strategy. Adds default SMTP configurations needed to send
     * email over SMTP using PHPMailer.
     *
     * @access public
     */
    public function loadDefaultConfigs()
    {
        parent::loadDefaultConfigs(); // load the base defaults
        $this->setEAPMId();
    }

    /**
     * @param null|string $mode
     * @throws MailerException
     */
    public function setMode($mode = null)
    {
        if (empty($mode)) {
            // this is the default mode
            $mode = OutboundEmailConfigurationPeer::MODE_GRAPH_API;
        }

        parent::setMode($mode);
    }

    /**
     * Sets the ID of the EAPM bean storing any Oauth2 token credentials
     *
     * @param string $eapmId the ID of the EAPM bean
     * @throws MailerException
     */
    public function setEAPMId($eapmId = '')
    {
        if (!is_string($eapmId) && !is_null($eapmId)) {
            throw new MailerException(
                'Invalid Configuration: eapmId must be a string',
                MailerException::InvalidConfiguration
            );
        }

        if (empty($eapmId)) {
            $eapm = EAPM::getLoginInfo('Microsoft');
            $eapmId = $eapm->id ?? '';
        }
        $this->eapmId = trim((string)$eapmId);
    }

    /**
     * Gets the ID of the EAPM bean storing any Oauth2 token credentials
     *
     * @return mixed
     */
    public function getEAPMId()
    {
        return $this->eapmId;
    }


    /**
     * @access public
     * @return array
     */
    public function toArray()
    {
        $fields = [
            'eapmId' => $this->getEAPMId(),
        ];
        return array_merge(parent::toArray(), $fields);
    }
}
