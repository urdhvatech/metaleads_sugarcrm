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

namespace Sugarcrm\Sugarcrm\InboundEmail\Polyfill;

/**
 * Handles MIME encoding/decoding including RFC2047 MIME words and UTF-7 mailbox names.
 */
class MimeEncoder
{
    /**
     * Decode MIME encoded-word format (RFC2047).
     * Handles: =?charset?encoding?encoded-text?=
     *
     * @param string $text Text to decode
     * @return string Decoded text in UTF-8
     */
    public static function decodeMimeWords(string $text): string
    {
        // Pattern for RFC2047 encoded words: =?charset?encoding?text?=
        $pattern = '/=\?([^?]+)\?([BQbq])\?([^?]*)\?=/';

        return preg_replace_callback($pattern, function ($matches) {
            $charset = $matches[1];
            $encoding = strtoupper($matches[2]);
            $encodedText = $matches[3];

            // Decode based on encoding type
            if ($encoding === 'B') {
                $decoded = base64_decode($encodedText);
            } elseif ($encoding === 'Q') {
                // Quoted-printable with underscore as space
                $decoded = str_replace('_', ' ', $encodedText);
                $decoded = quoted_printable_decode($decoded);
            } else {
                return $matches[0]; // Unknown encoding, return as-is
            }

            // Convert to UTF-8
            return self::convertToUtf8($decoded, $charset);
        }, $text);
    }

    /**
     * Convert text from source charset to UTF-8.
     *
     * @param string $text Text to convert
     * @param string $charset Source charset
     * @return string UTF-8 encoded text
     */
    public static function convertToUtf8(string $text, string $charset): string
    {
        $charset = strtoupper(trim($charset));

        // Already UTF-8
        if ($charset === 'UTF-8' || $charset === 'UTF8') {
            return $text;
        }

        // Try mb_convert_encoding
        if (function_exists('mb_convert_encoding')) {
            $result = @mb_convert_encoding($text, 'UTF-8', $charset);
            if ($result !== false) {
                return $result;
            }
            if (isset($GLOBALS['log'])) {
                $GLOBALS['log']->debug(
                    sprintf('mb_convert_encoding failed for charset: %s', $charset)
                );
            }
        }

        // Fallback to iconv
        if (function_exists('iconv')) {
            $result = @iconv($charset, 'UTF-8//IGNORE', $text);
            if ($result !== false) {
                return $result;
            }
            if (isset($GLOBALS['log'])) {
                $GLOBALS['log']->warning(
                    sprintf('Both mb_convert_encoding and iconv failed for charset: %s', $charset)
                );
            }
        }

        // Last resort: return as-is
        return $text;
    }

    /**
     * Encode text to modified UTF-7 (for IMAP mailbox names).
     * Modified UTF-7 uses & instead of + and - as shift characters.
     *
     * @param string $text Text to encode
     * @return string UTF-7 encoded text
     */
    public static function encodeUtf7(string $text): string
    {
        // Modified UTF-7 requires special handling of & character
        // & is encoded as &- in IMAP UTF-7

        $result = '';
        $len = mb_strlen($text, 'UTF-8');

        for ($i = 0; $i < $len; $i++) {
            $char = mb_substr($text, $i, 1, 'UTF-8');

            // Handle ampersand specially
            if ($char === '&') {
                $result .= '&-';
                continue;
            }

            // ASCII printable characters (0x20-0x7E except &)
            $ord = ord($char);
            if ($ord >= 0x20 && $ord <= 0x7E) {
                $result .= $char;
                continue;
            }

            // Non-ASCII needs UTF-7 encoding
            // Collect consecutive non-ASCII characters
            $nonAscii = $char;
            while ($i + 1 < $len) {
                $nextChar = mb_substr($text, $i + 1, 1, 'UTF-8');
                $nextOrd = ord($nextChar);
                if ($nextOrd < 0x20 || $nextOrd > 0x7E || $nextChar === '&') {
                    break;
                }
                if ($nextOrd >= 0x20 && $nextOrd <= 0x7E) {
                    break;
                }
                $i++;
                $nonAscii .= $nextChar;
            }

            // Convert to UTF-7 and replace + with &
            if (function_exists('iconv')) {
                $utf7 = iconv('UTF-8', 'UTF-7', $nonAscii);
                if ($utf7 !== false) {
                    $result .= str_replace('+', '&', $utf7);
                    continue;
                }
            }

            // Fallback
            $result .= $char;
        }

        return $result;
    }

    /**
     * Decode modified UTF-7 (from IMAP mailbox names).
     *
     * @param string $text UTF-7 encoded text
     * @return string Decoded UTF-8 text
     */
    public static function decodeUtf7(string $text): string
    {
        // No encoding if no &
        if (strpos($text, '&') === false) {
            return $text;
        }

        // Replace &- with a placeholder for literal &
        $placeholder = "\x00AMPERSAND\x00";
        $text = str_replace('&-', $placeholder, $text);

        // Convert modified UTF-7 back to UTF-7 (& → +)
        $utf7 = str_replace('&', '+', $text);

        if (function_exists('mb_convert_encoding')) {
            $decoded = mb_convert_encoding($utf7, 'UTF-8', 'UTF-7');
            // Restore literal ampersands
            return str_replace($placeholder, '&', $decoded);
        }

        // Fallback
        return str_replace($placeholder, '&', $text);
    }

    /**
     * Decode base64 content.
     * Note: imap_base64() returns false for non-base64 strings.
     *
     * @param string $text Base64 encoded text
     * @return string|false Decoded text or false on failure
     */
    public static function decodeBase64(string $text): string|false
    {
        // Native imap_base64 returns false for empty strings
        if ($text === '') {
            return false;
        }

        // Native imap_base64 returns false for strings that aren't valid base64
        $decoded = base64_decode($text, true);
        return $decoded !== false ? $decoded : false;
    }

    /**
     * Decode quoted-printable content.
     *
     * @param string $text Quoted-printable encoded text
     * @return string Decoded text
     */
    public static function decodeQuotedPrintable(string $text): string
    {
        return quoted_printable_decode($text);
    }

    /**
     * Convert 8bit text to quoted-printable encoding (what native imap_8bit does).
     *
     * @param string $text 8bit text
     * @return string Quoted-printable encoded text
     */
    public static function decode8Bit(string $text): string
    {
        // Native imap_8bit ENCODES to quoted-printable
        return quoted_printable_encode($text);
    }

    /**
     * Convert binary string to base64 encoding (what native imap_binary does).
     *
     * Native behavior: Encodes input to base64 and appends CRLF line ending
     *
     * @param string $text Binary text
     * @return string Base64 encoded text with CRLF line ending
     */
    public static function decodeBinary(string $text): string
    {
        // Native imap_binary ENCODES to base64 and adds CRLF
        return base64_encode($text) . "\r\n";
    }
}
