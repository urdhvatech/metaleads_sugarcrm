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
namespace Sugarcrm\Sugarcrm\GAI\Configuration;

use SugarAutoLoader;
use Sugarcrm\Sugarcrm\IdentityProvider\Authentication\Config as IdmConfig;
use Sugarcrm\IdentityProvider\Srn\Converter as SrnConverter;
use Sugarcrm\Sugarcrm\GAI\Configuration\ConfigurationInterface;
use Sugarcrm\Sugarcrm\GAI\Exception\BackendNotConfiguredException;

class ConfigurationOnDemandAdapter implements ConfigurationInterface
{
    /**
     * @var array
     */
    private $secrets;

    public function __construct(\SugarConfig $sugarConfig)
    {
        $this->secrets = $this->getSecrets();
    }

    /**
     * Get the secrets from env
     *
     * @return array
     */
    private function getSecrets(): array
    {
        if (!SugarAutoLoader::load(getenv('FROM_THIS_TIME_FORWARD'))) {
            $GLOBALS['log']->fatal('GAI: No env file found');

            throw new BackendNotConfiguredException();
        }

        if (!function_exists('getMySecret')) {
            $GLOBALS['log']->fatal('GAI: No getMySecret function found in env file');

            throw new BackendNotConfiguredException();
        }

        $secrets = getMySecret('PredictGAI');

        if (empty($secrets)) {
            $GLOBALS['log']->fatal('GAI: No secrets found in env file');

            throw new BackendNotConfiguredException();
        }

        if (!array_key_exists('url', $secrets) || !array_key_exists('key', $secrets)) {
            $GLOBALS['log']->fatal('GAI: No url or key found in env file');

            throw new BackendNotConfiguredException();
        }

        return $secrets;
    }

    public function getServiceURL(): string
    {
        return $this->secrets['url'];
    }

    public function getApiKey(): string
    {
        return $this->secrets['key'];
    }

    public function getHeaders(): array
    {
        return ['x-api-key' => $this->getApiKey()];
    }

    /**
     * Max retries for the request
     */
    public function getMaxRetries(): int
    {
        return 3;
    }

    public function getTenant(): ?string
    {
        $tenantSrn = null;
        $idmConfig = new IdmConfig(\SugarConfig::getInstance());

        if (!$idmConfig->isIDMModeEnabled()) {
            $GLOBALS['log']->fatal('GAI: This method works only in IDM mode');

            throw new BackendNotConfiguredException();
        }

        $idmModeConfig = $idmConfig->getIDMModeConfig();

        if ($idmModeConfig && !empty($idmModeConfig['tid'])) {
            $tenantSrn = $idmModeConfig['tid'];
        }

        return $tenantSrn;
    }
}
