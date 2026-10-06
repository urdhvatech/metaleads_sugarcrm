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

class PdfManagerHooks
{
    /**
     * Image file extensions supported by TCPDF for PDF header logos.
     */
    private const SUPPORTED_LOGO_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'bmp'];

    /**
     * Fixes TinyMCE converting & to &amp;, protecting smarty templates.
     *
     * @param PdfManager $bean
     * @param [type] $event
     * @param array $params
     * @return void
     */
    public function fixAmp(PdfManager $bean, $event, $params = [])
    {
        $bean->body_html = str_replace('&amp;', '&', $bean->body_html);
    }

    /**
     * Validates that the header_logo file type is supported by TCPDF.
     *
     * Unsupported formats (e.g. WebP, TIFF, SVG, HEIC) cause a fatal
     * "TCPDF ERROR: Can not get image size" when previewing or generating
     * the PDF. This hook rejects them at save time with a clear error.
     *
     * In BWC context, SugarApplication::appendErrorMessage() displays the
     * error on the next page load. The field value is cleared to prevent
     * the unsupported file from persisting on the bean.
     *
     * In API context, SugarApiExceptionInvalidParameter is thrown so the
     * REST framework can return a proper HTTP 422 response.
     *
     * @param PdfManager $bean
     * @param string $event
     * @param array $params
     * @throws SugarApiExceptionInvalidParameter when called via REST API
     */
    public function validateHeaderLogo(PdfManager $bean, string $event, array $params = []): void
    {
        if (empty($bean->header_logo)) {
            return;
        }

        $extension = strtolower(pathinfo($bean->header_logo, PATHINFO_EXTENSION));

        if ($this->isLogoExtensionSupported($extension)) {
            return;
        }

        if ($extension === '') {
            $errorMessage = sprintf(
                'Header logo file "%s" has no file extension. Supported types: %s.',
                $bean->header_logo,
                implode(', ', self::SUPPORTED_LOGO_EXTENSIONS)
            );
        } else {
            $errorMessage = sprintf(
                'Unsupported header logo file type ".%s". Supported types: %s.',
                $extension,
                implode(', ', self::SUPPORTED_LOGO_EXTENSIONS)
            );
        }

        if ($this->isFromApi()) {
            throw new SugarApiExceptionInvalidParameter($errorMessage);
        }

        // BWC context: show error message and clear the invalid value
        \SugarApplication::appendErrorMessage($errorMessage);
        $bean->header_logo = '';
    }

    /**
     * Check whether the given file extension is supported by TCPDF.
     */
    public function isLogoExtensionSupported(string $extension): bool
    {
        return in_array(strtolower($extension), self::SUPPORTED_LOGO_EXTENSIONS, true);
    }

    /**
     * Wrapper around global isFromApi() for testability.
     */
    protected function isFromApi(): bool
    {
        return isFromApi();
    }
}
