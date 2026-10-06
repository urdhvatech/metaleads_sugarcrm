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

/**
 * PHP wrapper class for Javascript driven TinyMCE WYSIWYG HTML editor
 */
class SugarTinyMCE
{
    public string $version = '6.8.6';
    public $jsroot = 'include/javascript/tinymce/';
    public $customConfigFile = 'custom/include/tinyButtonConfig.php';
    public $customPluginConfigFile = 'custom/include/tinyPluginConfig.php';
    public $customDefaultConfigFile = 'custom/include/tinyMCEDefaultConfig.php';
    public $buttonConfigs
        = [
            'default' => [
                'buttonConfig' => 'code | bold italic underline strikethrough | alignleft aligncenter alignright ' .
                    'alignjustify | forecolor backcolor | fontfamily fontsize blocks | ' .
                    'cut copy paste pastetext | search searchreplace | bullist numlist | ' .
                    'outdent indent | ltr rtl | undo redo | link unlink anchor image | subscript ' .
                    'superscript | charmap visualaid | table | hr removeformat | insertdatetime',
                'buttonConfig2' => '',
                'buttonConfig3' => '',
            ],
            'email_compose' => [
                'buttonConfig' => 'code help | bold italic underline strikethrough | bullist numlist | alignleft ' .
                    'aligncenter alignright alignjustify | forecolor backcolor | ' .
                    'styleselect blocks fontfamily fontsize',
                'buttonConfig2' => '',
                'buttonConfig3' => '',
            ],
            'email_compose_light' => [
                'buttonConfig' => 'code help | bold italic underline strikethrough | bullist numlist | alignleft ' .
                    'aligncenter alignright alignjustify | forecolor backcolor | ' .
                    'styleselect blocks fontfamily fontsize',
                'buttonConfig2' => '',
                'buttonConfig3' => '',
            ],
        ];

    public $pluginsConfig
        = [
            'email_compose_light' => 'insertdatetime,paste,directionality,safari',
            'email_compose' => 'insertdatetime,table,preview,paste,searchreplace,directionality,fullpage',
        ];


    public $defaultConfig
        = [
            'valid_children' => '+body[style]',
            'height' => 300,
            'width' => '100%',
            'theme' => 'silver',
            'language' => 'en',
            'extended_valid_elements' => 'style[dir|lang|media|title|type],hr[class|width|size|noshade],@[class|style]',
            'content_css' => [
                'default',
                'styleguide/assets/css/sugar-theme-variables.css',
                'styleguide/assets/css/iframe-sugar.css',
                'maple-syrup/build/theme/sugar-base.css',
                'maple-syrup/build/theme/sugar-light.css',
            ],
            'body_class' => 'sugar-light-theme',
            'plugins' => 'code,help,insertdatetime,table,charmap,image,link,anchor,directionality,searchreplace,lists',
            'browser_spellcheck' => true,
            'menubar' => false,
            'statusbar' => false,
            'resize' => false,
            'toolbar_mode' => 'wrap',
            'entity_encoding' => 'raw',
            'relative_urls' => false,
            'convert_urls' => false,
            'element_format' => 'xhtml',
        ];


    /**
     * Sole constructor
     */
    public function __construct()
    {
        $this->overloadButtonConfigs();
        $this->overloadDefaultConfigs();
        $this->overloadPluginConfigs();
    }

    /**
     * Returns the Javascript necessary to initialize a TinyMCE instance for a given <textarea> or <div>
     *
     * @param string target Comma delimited list of DOM ID's, <textarea id='someTarget'>
     * @param string $skinMode Define Dark mode behavior for TinyMCE ('default' or 'combine').
     * TinyMCE v3 used white mode styles for Dark mode, so some content might be not adapted for dark mode.
     * We had to use white mode content styles ('combine') for Dark mode for backward compatibility
     * @return string
     */
    public function getInstance($targets = '', string $skinMode = 'default')
    {
        global $json;
        $nonce = Nonce::create();

        if (empty($json)) {
            $json = getJSONobj();
        }

        $config = $this->defaultConfig;
        $config['directionality'] = SugarThemeRegistry::current()->directionality;
        $config['selector'] = '#' . str_replace(',', '#', $targets);
        $config['toolbar1'] = $this->buttonConfigs['default']['buttonConfig'];
        $config['toolbar2'] = $this->buttonConfigs['default']['buttonConfig2'];
        $config['toolbar3'] = $this->buttonConfigs['default']['buttonConfig3'];

        $jsConfig = $json->encode($config);

        $instantiateCall = '';
        $path = getJSPath('include/javascript/tinymce/tinymce.min.js?v=' . $this->version);

        $exTargets = explode(',', $targets);

        $ret
            = <<<eoq
<script type="text/javascript" language="Javascript" src="$path" nonce="{$nonce}"></script>
<script type="text/javascript" language="Javascript" nonce="{$nonce}">
<!--
function onTinyMCEInit() {
eoq;
        foreach ($exTargets as $instance) {
            $ret .= "$('#" . $instance . "_ifr').contents().find('iframe[data-mce-src]').attr('src','').attr('sandbox','');\n";
        }

        $ret .= <<<eoq
}

if (!SUGAR.util.isTouchScreen()) {
    const config = {$jsConfig};
    config.init_instance_callback = onTinyMCEInit;
    if (typeof app !== 'undefined') {
        config.link_target_list = [
            {
                text: app.lang.getAppString('LBL_TINYMCE_TARGET_SAME'),
                value: '',
            },
            {
                text: app.lang.getAppString('LBL_TINYMCE_TARGET_NEW'),
                value: '_blank',
            },
        ];
        config.skin = app.utils.isDarkMode() ? 'oxide-dark' : 'oxide';
eoq;
        if ($skinMode === 'default') {
            $ret .= <<<eoq
                if (app.utils.isDarkMode()) {
                    config.body_class = 'sugar-dark-theme';
                    config.content_css = [
                        'dark',
                        'styleguide/assets/css/sugar-theme-variables.css',
                        'styleguide/assets/css/iframe-sugar.css',
                        'maple-syrup/build/theme/sugar-base.css',
                        'maple-syrup/build/theme/sugar-dark.css',
                    ];
                }
            eoq;
        }

        $ret
            .= <<<eoq
    }
    tinyMCE.init(config);
	{$instantiateCall}
}
else {
eoq;
        foreach ($exTargets as $instance) {
            $ret
                .= <<<eoq
    document.getElementById('$instance').style.width = '100%';
    document.getElementById('$instance').style.height = '100px';
eoq;
        }
        $ret
            .= <<<eoq
}
-->
</script>

eoq;
        return $ret;
    }

    public function getConfig($type = 'default')
    {
        global $json;

        if (empty($json)) {
            $json = getJSONobj();
        }

        $config = $this->defaultConfig;
        $config['toolbar1'] = $this->buttonConfigs[$type]['buttonConfig'];
        $config['toolbar2'] = $this->buttonConfigs[$type]['buttonConfig2'];
        $config['toolbar3'] = $this->buttonConfigs[$type]['buttonConfig3'];

        if (isset($this->pluginsConfig[$type])) {
            $config['plugins'] = $this->pluginsConfig[$type];
        }

        $jsConfig = $json->encode($config);
        return 'var tinyConfig = ' . $jsConfig . ';';
    }

    /**
     * This function takes in html code that has been produced (and somewhat mauled) by TinyMCE
     * and returns a cleaned copy of it.
     *
     * @param $html
     *
     * @return $html with all the tinyMCE specific html removed
     */
    public function cleanEncodedMCEHtml($html)
    {
        $html = str_replace('mce:script', 'script', $html);
        $html = str_replace('mce_src=', 'src=', $html);
        $html = str_replace('mce_href=', 'href=', $html);
        return $html;
    }

    /**
     * Reload the default button configs by allowing admins to specify
     * which tinyMCE buttons will be displayed in a separate config file.
     *
     */
    private function overloadButtonConfigs()
    {
        if (SugarAutoLoader::existing($this->customConfigFile)) {
            require_once $this->customConfigFile;
        }

        $defs = SugarAutoLoader::loadExtension('tinymce');
        if ($defs) {
            require $defs;
        }

        if (!isset($buttonConfigs)) {
            return;
        }

        foreach ($buttonConfigs as $k => $v) {
            $this->buttonConfigs[$k] = $this->patchCustomLegacyConfig($v);
        }
    }

    /**
     * This function patches the custom legacy config to ensure that the button
     * configurations are compatible with the new TinyMCE version.
     *
     * @param array $conf The configuration array to patch.
     * @return array The patched configuration array.
     */
    protected function patchCustomLegacyConfig($conf)
    {
        return $this->patchLegacyButtons($conf);
    }

    /**
     * This function patches the legacy button configurations to ensure compatibility
     * with the new TinyMCE version.
     *
     * @param array $conf The configuration array to patch.
     * @return array The patched configuration array.
     */
    private function patchLegacyButtons($conf)
    {
        if (!isset($conf['buttonConfig'])) {
            return $conf;
        }

        $replaceMap = [
            ',' => ' ',
            'separator' => '|',
            'replace' => 'searchreplace',
            'justifyleft' => 'alignleft',
            'justifycenter' => 'aligncenter',
            'justifyright' => 'alignright',
            'justifyfull' => 'alignjustify',
            'insertdate' => 'insertdatetime',
            'sub' => 'subscript',
            'sup' => 'superscript',
            'formatselect' => 'blocks',
            'fontselect' => 'fontfamily',
            'fontsizeselect' => 'fontsize',
            'tablecontrols' => 'table',
        ];

        foreach (['buttonConfig', 'buttonConfig2', 'buttonConfig3'] as $key) {
            if (isset($conf[$key])) {
                $str = preg_replace('/\s+/', '', $conf[$key]);
                $conf[$key] = strtr($str, $replaceMap);
            }
        }

        return $conf;
    }


    /**
     * Reload the default tinyMCE plugin config
     *
     */
    private function overloadPluginConfigs()
    {
        if (SugarAutoLoader::existing($this->customPluginConfigFile)) {
            require_once $this->customPluginConfigFile;
        }

        $defs = SugarAutoLoader::loadExtension('tinymce');
        if ($defs) {
            require $defs;
        }

        if (!isset($pluginsConfig)) {
            return;
        }

        foreach ($pluginsConfig as $k => $v) {
            $this->pluginsConfig[$k] = $v;
        }
    }

    /**
     * Reload the default tinyMCE config
     *
     */
    private function overloadDefaultConfigs()
    {
        if (SugarAutoLoader::existing($this->customDefaultConfigFile)) {
            require_once $this->customDefaultConfigFile;
        }

        $defs = SugarAutoLoader::loadExtension('tinymce');
        if ($defs) {
            require $defs;
        }

        if (!isset($defaultConfig)) {
            return;
        }

        foreach ($defaultConfig as $k => $v) {
            if ($k == 'extended_valid_elements') {
                $this->defaultConfig[$k] .= ',' . $v;
            } else {
                $this->defaultConfig[$k] = $v;
            }
        }
    }
} // end class def
