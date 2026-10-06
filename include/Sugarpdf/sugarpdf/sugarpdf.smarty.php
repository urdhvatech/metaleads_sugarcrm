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
 * This is an helper class to generate PDF using smarty template.
 * You have to extend this class, set the templateLocation to your smarty template
 * location and assign the Smarty variables ($this->ss->assign()) in the overriden
 * preDisplay method (don't forget to call the parent).
 *
 * @author bsoufflet
 *
 */
class SugarpdfSmarty extends Sugarpdf
{
    /**
     *
     * @var String
     */
    protected $templateLocation = '';
    /**
     * The Sugar_Smarty object
     * @var Sugar_Smarty
     */
    protected $ss;
    /**
     * These 5 variables are use for the writeHTML method.
     * @see vendor/tcpdf/tcpdf.php writeHTML()
     */
    protected $smartyLn = true;
    protected $smartyFill = false;
    protected $smartyReseth = false;
    protected $smartyCell = false;
    protected $smartyAlign = '';

    public function preDisplay()
    {
        parent::preDisplay();
        $this->print_header = false;
        $this->print_footer = false;
        $this->initSmartyInstance();
    }

    public function display()
    {
        //turn off all error reporting so that PHP warnings don't munge the PDF code
        error_reporting(E_ALL);
        set_time_limit(1800);

        //Create new page
        $this->AddPage();
        $this->SetFont(PDF_FONT_NAME_MAIN, '', 8);

        if (!empty($this->templateLocation)) {
            $str = $this->ss->fetch($this->templateLocation);
            $this->writeHTML($str, $this->smartyLn, $this->smartyFill, $this->smartyReseth, $this->smartyCell, $this->smartyAlign);
        } else {
            $this->Error('The class SugarpdfSmarty has to be extended and you have to set a location for the Smarty template.');
        }
    }

    /**
     * Init the Sugar_Smarty object.
     */
    private function initSmartyInstance()
    {
        if (!($this->ss instanceof Sugar_Smarty)) {
            $this->ss = new Sugar_Smarty();
            $securityPolicy = new Smarty_Security($this->ss);
            // forbid all static calls
            $securityPolicy->static_classes = [null];
            // explicitly allow list of PHP functions (equals to defaults):
            $securityPolicy->php_functions = ['isset', 'empty', 'count', 'sizeof', 'in_array', 'is_array', 'time',];
            $securityPolicy->allow_super_globals = false;
            $securityPolicy->allow_constants = false;
            // disable all stream wrappers
            $securityPolicy->streams = null;
            // 'math' internally uses eval()
            $securityPolicy->disabled_tags = ['eval', 'fetch', 'include_php', 'math', 'php',];
            /**
             * Disable 'template_object' to prevent tricks like:  {$name=$smarty.template_object->disableSecurity()} {include "file://etc/passwd"}
             * Disable 'current_dir' to prevent filesystem info leakage
             */
            $securityPolicy->disabled_special_smarty_vars = ['template_object', 'current_dir',];
            if (defined('SUGAR_SHADOW_PATH')) {
                $securityPolicy->secure_dir[] = SUGAR_SHADOW_PATH;
            }
            $this->ss->enableSecurity($securityPolicy);

            $this->ss->assign('MOD', $GLOBALS['mod_strings']);
            $this->ss->assign('APP', $GLOBALS['app_strings']);
        }
    }

    /*
     * @see TCPDF::Image()
     */
    public function Image($file, $x = '', $y = '', $w = 0, $h = 0, $type = '', $link = '', $align = '', $resize = false, $dpi = 300, $palign = '', $ismask = false, $imgmask = false, $border = 0, $fitbox = false)
    {
        $file = $this->resolveSugarDownloadUrl($file);
        // strip query string from the image if it exists in the file name, tcpdf doesn't like 'query strings as part of the image file name
        //a sample image string is:  http://d2owqhhe2x3j50.cloudfront.net/images/sugar7/home/concept1_1.png?version=3.0.3

        //grab the base name and search for the '?' character
        $fileinfo = pathinfo($file);
        $q_pos = strpos($fileinfo['basename'], '?');

        if (!empty($fileinfo['basename']) && $q_pos !== false) {
            //split the file name and reassemble
            $splitName = substr($fileinfo['basename'], 0, $q_pos);
            $file = $fileinfo['dirname'] . '/' . $splitName;
        }
        if (empty($type)) {
            $type = $this->getImageTypeForTcpdf($file);
        }

        return parent::Image($file, $x, $y, $w, $h, $type, $link, $align, $resize, $dpi, $palign, $ismask, $imgmask, $border, $fitbox);
    }

    /**
     * Resolves SugarCRM download URLs to direct file paths for TCPDF processing
     *
     * @param string $file The file path or URL
     * @return string The resolved file path
     */
    protected function resolveSugarDownloadUrl($file)
    {
        try {
            // Parse the URL to extract parameters
            $urlParts = parse_url($file);

            // Handle REST API URLs (fallback for unprocessed URLs)
            if (isset($urlParts['path']) && preg_match('#/rest/v[^/]+/(Documents|Notes)/([^/]+)/file/#', $urlParts['path'], $matches)) {
                // Security check: Only process URLs from this SugarCRM instance
                if (!$this->isLocalSugarUrl($urlParts)) {
                    $this->logSecurityWarning("External domain REST URL not allowed", compact('file'));
                    return $file;
                }

                $type = $matches[1];
                $id = $matches[2];

                // Convert to upload:// path based on type
                $uploadPath = $this->getUploadPathFromDownloadParams($type, $id);

                if ($uploadPath && file_exists($uploadPath)) {
                    return $uploadPath;
                } else {
                    return $file;
                }
            }
        } catch (Exception $e) {
            $GLOBALS['log']->error("SugarPDF: Error resolving download URL {$file}: " . $e->getMessage());
            return $file;
        }
        return $file;
    }


    /**
     * Gets the upload:// path from download parameters
     *
     * @param string $type The type parameter (Documents, Notes, etc.)
     * @param string $id The ID parameter
     * @return string|null The upload path or null if not found
     */
    protected function getUploadPathFromDownloadParams($type, $id)
    {
        // Validate inputs
        if ($error = $this->validateDownloadParams($type, $id)) {
            $this->logSecurityWarning($error, compact('type', 'id'));
            return null;
        }

        // Load bean and validate access
        $bean = BeanFactory::getBean($type, $id);
        if ($error = $this->validateBeanAccess($bean, $type, $id)) {
            $this->logSecurityWarning($error, compact('type', 'id'));
            return null;
        }

        // Get file information
        $fileId = $this->getFileIdFromBean($bean, $type);
        if ($error = $this->validateFileId($fileId)) {
            $this->logSecurityWarning($error, compact('type', 'id', 'fileId'));
            return null;
        }

        // Check file existence and mime type
        $uploadPath = "upload://{$fileId}";
        if ($error = $this->validateFileAccess($uploadPath, $bean, $type, $fileId)) {
            $this->logSecurityWarning($error, compact('type', 'id', 'fileId', 'uploadPath'));
            return null;
        }

        return $uploadPath;
    }

    /**
     * Gets TCPDF image type from file path using getimagesize()
     *
     * @param string $filePath The file path
     * @return string The TCPDF image type
     */
    protected function getImageTypeForTcpdf($filePath)
    {
        $imageInfo = @getimagesize($filePath);
        if ($imageInfo === false || !isset($imageInfo[2])) {
            return '';
        }

        // Convert PHP image type constants to TCPDF types
        switch ($imageInfo[2]) {
            case IMAGETYPE_JPEG:
                return 'JPEG';
            case IMAGETYPE_PNG:
                return 'PNG';
            case IMAGETYPE_GIF:
                return 'GIF';
            case IMAGETYPE_BMP:
                return 'BMP';
            case IMAGETYPE_TIFF_II:
            case IMAGETYPE_TIFF_MM:
                return 'TIFF';
            default:
                return '';
        }
    }


    /**
     * Validates if the mime type is an allowed image type
     *
     * @param string $mimeType The mime type to validate
     * @return bool True if it's a valid image mime type
     */
    protected function isValidImageMimeType($mimeType)
    {
        $allowedImageTypes = [
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/bmp',
            'image/tiff',
            'image/tif',
        ];

        return in_array(strtolower($mimeType), $allowedImageTypes);
    }

    /**
     * Gets the file ID from a bean based on module type
     *
     * @param SugarBean $bean The bean object
     * @param string $type The module type
     * @return string|null The file ID or null if not found
     */
    protected function getFileIdFromBean($bean, $type)
    {
        switch ($type) {
            case 'Documents':
                return !empty($bean->document_revision_id) ? $bean->document_revision_id : null;
            case 'Notes':
                return $bean->id;
            default:
                return null;
        }
    }

    /**
     * Gets the mime type from a bean based on module type
     *
     * @param SugarBean $bean The bean object
     * @param string $type The module type
     * @param string $fileId The file ID
     * @return string|null The mime type or null if not found
     */
    protected function getMimeTypeFromBean($bean, $type, $fileId)
    {
        switch ($type) {
            case 'Documents':
                // For documents, we need to load the revision to get mime type
                $revision = BeanFactory::getBean('DocumentRevisions', $fileId);
                return $revision ? $revision->file_mime_type : null;
            case 'Notes':
                // For notes, mime type is stored directly on the note
                return $bean->file_mime_type;
            default:
                return null;
        }
    }

    /**
     * Validates download parameters
     *
     * @param string $type Module type
     * @param string $id Record ID
     * @return string|null Error message or null if valid
     */
    protected function validateDownloadParams($type, $id)
    {
        if (!is_guid($id)) {
            return "Invalid ID format for security: {$id}";
        }

        if (!in_array($type, ['Documents', 'Notes'])) {
            return "Unauthorized module type: {$type}";
        }

        return null;
    }

    /**
     * Validates bean access
     *
     * @param mixed $bean The loaded bean
     * @param string $type Module type
     * @param string $id Record ID
     * @return string|null Error message or null if valid
     */
    protected function validateBeanAccess($bean, $type, $id)
    {
        if (!$bean || !$bean->ACLAccess('view')) {
            return "No access to {$type}: {$id}";
        }

        return null;
    }

    /**
     * Validates file ID
     *
     * @param string|null $fileId The file ID
     * @return string|null Error message or null if valid
     */
    protected function validateFileId($fileId)
    {
        if (!$fileId) {
            return "No file ID found";
        }

        if (!is_guid($fileId)) {
            return "Invalid file ID format: {$fileId}";
        }

        return null;
    }

    /**
     * Validates file access and mime type
     *
     * @param string $uploadPath The upload path
     * @param mixed $bean The bean object
     * @param string $type Module type
     * @param string $fileId File ID
     * @return string|null Error message or null if valid
     */
    protected function validateFileAccess($uploadPath, $bean, $type, $fileId)
    {
        if (!file_exists($uploadPath)) {
            return "File does not exist: {$uploadPath}";
        }

        $mimeType = $this->getMimeTypeFromBean($bean, $type, $fileId);
        if (!$this->isValidImageMimeType($mimeType)) {
            return "Non-image mime type not allowed: {$mimeType}";
        }

        return null;
    }

    /**
     * Validates if a URL belongs to this SugarCRM instance
     *
     * @param array $urlParts Parsed URL components from parse_url()
     * @return bool True if URL is from this SugarCRM instance
     */
    protected function isLocalSugarUrl($urlParts)
    {
        // If no host is specified, assume it's a relative URL (local)
        if (!isset($urlParts['host'])) {
            return true;
        }

        $urlHost = $urlParts['host'];

        // Primary validation: Check against site_url configuration (most trusted)
        global $sugar_config;
        if (!empty($sugar_config['site_url'])) {
            $siteUrlParts = parse_url($sugar_config['site_url']);
            if (isset($siteUrlParts['host']) && strcasecmp($urlHost, $siteUrlParts['host']) === 0) {
                return true;
            }
        }

        // Fallback: Use SERVER_NAME (not HTTP_HOST which is user-controlled)
        $serverName = $_SERVER['SERVER_NAME'] ?? null;
        if ($serverName && strcasecmp($urlHost, $serverName) === 0) {
            return true;
        }

        return false;
    }

    /**
     * Logs security warnings with context
     *
     * @param string $message Error message
     * @param array $context Context data
     */
    protected function logSecurityWarning($message, array $context = [])
    {
        $contextStr = empty($context) ? '' : ' [' . json_encode($context) . ']';
        $GLOBALS['log']->warn("SugarPDF: {$message}{$contextStr}");
    }
}
