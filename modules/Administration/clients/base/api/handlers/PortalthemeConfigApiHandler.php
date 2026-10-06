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

use Sugarcrm\Sugarcrm\Security\Validator\Constraints\HexColor;
use Sugarcrm\Sugarcrm\Security\Validator\Validator;

/**
 * API Handler for Portal Theme Config
 */
class PortalthemeConfigApiHandler extends ConfigApiHandler
{
    /**
     * Mapping of variables defined in styleguide
     * @var array
     */
    public static $STYLEGUIDE_VARIABLES = [
        'portaltheme_button_color' => 'PrimaryButton',
        'portaltheme_text_link_color' => 'LinkColor',
    ];

    /**
     * @inheritdoc
     */
    public function setConfig(ServiceBase $api, array $args)
    {
        $args['platform'] ??= 'portal';
        $this->validateConfig($args);
        $result = parent::setConfig($api, $args);
        $this->setStyleguideConfig($api, $args);
        $this->clearCache();
        return $result;
    }

    protected function validateConfig(array $args)
    {
        $validator = Validator::getService();
        foreach ($args as $name => $value) {
            switch ($name) {
                case 'portaltheme_banner_background_color':
                case 'portaltheme_button_color':
                case 'portaltheme_text_link_color':
                    $violations = $validator->validate($value, new HexColor());
                    if ($violations->count()) {
                        throw new SugarApiExceptionInvalidParameter('Invalid HexColor', $violations);
                    }
                    break;
            }
        }
    }

    /**
     * Update styleguide configs
     *
     * @param ServiceBase $api
     * @param array $args
     */
    protected function setStyleguideConfig($api, $args)
    {
        $themeApi = $this->getThemeApi();
        $themeArgs = [
            'platform' => 'portal',
            'themeName' => 'default',
        ];
        foreach (self::$STYLEGUIDE_VARIABLES as $key => $value) {
            if (!empty($args[$key])) {
                $themeArgs[$value] = $args[$key];
            }
        }
        $themeApi->updateCustomTheme($api, $themeArgs);
    }

    /**
     * Get ThemeApi instance
     * @return ThemeApi
     */
    protected function getThemeApi()
    {
        return new ThemeApi();
    }
}
