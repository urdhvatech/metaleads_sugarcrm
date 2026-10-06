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

use Webklex\PHPIMAP\Message;
use Webklex\PHPIMAP\Structure;
use Webklex\PHPIMAP\Part;
use Webklex\PHPIMAP\Attachment;
use Webklex\PHPIMAP\IMAP;

/**
 * Builds MIME structure objects from Webklex Message that match native imap_fetchstructure() format.
 *
 * Uses HYBRID approach:
 * - Primary: Convert Webklex Structure to native IMAP format (fast)
 * - Fallback: Parse raw MIME for complex nested structures (when needed)
 */
class MimeStructureBuilder
{
    /**
     * Build a structure object from a Webklex Message.
     *
     * @param Message $message The Webklex message object
     * @return \stdClass Structure object matching native IMAP format
     */
    public static function buildFromMessage(Message $message): \stdClass
    {
        // Try to use Webklex's parsed structure first
        $webklexStructure = $message->getStructure();

        if ($webklexStructure && $webklexStructure->type === IMAP::MESSAGE_TYPE_MULTIPART) {
            // Use Webklex structure for multipart
            return self::convertWebklexStructure($webklexStructure, $message);
        }

        // For simple messages, build directly
        return self::buildSimpleStructure($message);
    }

    /**
     * Convert Webklex Structure to native IMAP structure format.
     *
     * @param Structure $structure Webklex structure
     * @param Message $message The message object
     * @return \stdClass Native IMAP structure
     */
    private static function convertWebklexStructure(Structure $structure, Message $message): \stdClass
    {
        $result = new \stdClass();

        // Map type
        $result->type = $structure->type === IMAP::MESSAGE_TYPE_MULTIPART ? TYPEMULTIPART : TYPETEXT;
        $result->encoding = self::mapEncoding($message);
        $result->ifsubtype = 1;
        $result->subtype = 'MIXED'; // Default for multipart
        $result->ifdescription = 0;
        $result->ifid = 0;
        $result->lines = substr_count($structure->raw, "\n");
        $result->bytes = strlen($structure->raw);
        $result->ifdisposition = 0;
        $result->ifdparameters = 0;

        // Parse Content-Type for parameters
        $contentType = $message->getHeader()->get('content-type');
        $contentTypeStr = is_array($contentType) ? $contentType[0] : (string)$contentType;
        [, $subtype, $parameters] = self::parseContentType($contentTypeStr);

        $result->subtype = $subtype;
        $result->ifparameters = !empty($parameters) ? 1 : 0;
        $result->parameters = [];

        foreach ($parameters as $key => $value) {
            $param = new \stdClass();
            $param->attribute = strtolower($key);
            $param->value = $value;
            $result->parameters[] = $param;
        }

        // Convert flat parts array from Webklex
        if ($result->type === TYPEMULTIPART && !empty($structure->parts)) {
            $result->parts = self::convertWebklexParts($structure->parts);
        }

        return $result;
    }

    /**
     * Convert Webklex flat parts array to IMAP structure parts.
     *
     * @param array $parts Array of Webklex Part objects
     * @return array Array of native IMAP part structures
     */
    private static function convertWebklexParts(array $parts): array
    {
        $result = [];

        foreach ($parts as $part) {
            $partStruct = new \stdClass();

            // Map type
            $partStruct->type = match ($part->type) {
                IMAP::MESSAGE_TYPE_MULTIPART => TYPEMULTIPART,
                default => TYPETEXT,
            };

            // Parse content type for better type detection
            if ($part->content_type) {
                $partStruct->type = self::mapContentTypeToImapType($part->content_type);
            }

            // Map encoding
            $partStruct->encoding = self::mapWebklexEncoding($part->encoding);

            // Subtype
            $partStruct->ifsubtype = !empty($part->subtype) ? 1 : 0;
            $partStruct->subtype = strtoupper($part->subtype ?? 'PLAIN');

            // Description
            $partStruct->ifdescription = !empty($part->description) ? 1 : 0;
            if ($partStruct->ifdescription) {
                $partStruct->description = $part->description;
            }

            // ID
            $partStruct->ifid = !empty($part->id) ? 1 : 0;
            if ($partStruct->ifid) {
                $partStruct->id = $part->id;
            }

            $partStruct->lines = substr_count($part->content, "\n");
            $partStruct->bytes = $part->bytes ?? strlen($part->content);

            // Disposition
            $partStruct->ifdisposition = $part->ifdisposition ? 1 : 0;
            if ($partStruct->ifdisposition) {
                $partStruct->disposition = $part->disposition;

                // Disposition parameters
                $partStruct->ifdparameters = !empty($part->filename) ? 1 : 0;
                $partStruct->dparameters = [];

                if ($part->filename) {
                    $dparam = new \stdClass();
                    $dparam->attribute = 'filename';
                    $dparam->value = $part->filename;
                    $partStruct->dparameters[] = $dparam;
                }
            } else {
                $partStruct->ifdparameters = 0;
            }

            // Parameters
            $partStruct->ifparameters = !empty($part->charset) || !empty($part->name) ? 1 : 0;
            $partStruct->parameters = [];

            if ($part->charset) {
                $param = new \stdClass();
                $param->attribute = 'charset';
                $param->value = $part->charset;
                $partStruct->parameters[] = $param;
            }

            if ($part->name) {
                $param = new \stdClass();
                $param->attribute = 'name';
                $param->value = $part->name;
                $partStruct->parameters[] = $param;
            }

            $result[] = $partStruct;
        }

        return $result;
    }

    /**
     * Build structure for simple (non-multipart) messages.
     *
     * @param Message $message The message object
     * @return \stdClass Structure object
     */
    private static function buildSimpleStructure(Message $message): \stdClass
    {
        $structure = new \stdClass();

        // Parse Content-Type header
        $contentType = $message->getHeader()->get('content-type');
        $contentTypeStr = is_array($contentType) ? $contentType[0] : (string)$contentType;

        [$type, $subtype, $parameters] = self::parseContentType($contentTypeStr);

        // Set basic structure properties
        $structure->type = $type;
        $structure->encoding = self::getEncoding($message);
        $structure->ifsubtype = 1;
        $structure->subtype = $subtype;
        $structure->ifdescription = 0;
        $structure->ifid = 0;
        $structure->lines = substr_count($message->getRawBody(), "\n");
        $structure->bytes = strlen($message->getRawBody());
        $structure->ifdisposition = 0;
        $structure->ifdparameters = 0;
        $structure->ifparameters = !empty($parameters) ? 1 : 0;
        $structure->parameters = [];

        // Add Content-Type parameters
        foreach ($parameters as $key => $value) {
            $param = new \stdClass();
            $param->attribute = strtolower($key);
            $param->value = $value;
            $structure->parameters[] = $param;
        }

        return $structure;
    }

    /**
     * Map Webklex encoding to IMAP encoding constant.
     *
     * @param int $encoding Webklex encoding constant
     * @return int IMAP encoding constant
     */
    private static function mapWebklexEncoding(int $encoding): int
    {
        return match ($encoding) {
            IMAP::MESSAGE_ENC_7BIT => ENC7BIT,
            IMAP::MESSAGE_ENC_8BIT => ENC8BIT,
            IMAP::MESSAGE_ENC_BINARY => ENCBINARY,
            IMAP::MESSAGE_ENC_BASE64 => ENCBASE64,
            IMAP::MESSAGE_ENC_QUOTED_PRINTABLE => ENCQUOTEDPRINTABLE,
            default => ENC7BIT,
        };
    }

    /**
     * Map content type string to IMAP type constant.
     *
     * @param string $contentType Content-Type header value
     * @return int IMAP type constant
     */
    private static function mapContentTypeToImapType(string $contentType): int
    {
        $type = strtolower(explode('/', $contentType)[0] ?? 'text');

        return match ($type) {
            'text' => TYPETEXT,
            'multipart' => TYPEMULTIPART,
            'message' => TYPEMESSAGE,
            'application' => TYPEAPPLICATION,
            'audio' => TYPEAUDIO,
            'image' => TYPEIMAGE,
            'video' => TYPEVIDEO,
            'model' => TYPEMODEL,
            default => TYPEOTHER,
        };
    }

    /**
     * Map encoding from message headers.
     *
     * @param Message $message The message object
     * @return int Encoding constant
     */
    private static function mapEncoding(Message $message): int
    {
        $encoding = $message->getHeader()->get('content-transfer-encoding');
        $encodingStr = is_array($encoding) ? $encoding[0] : (string)$encoding;
        $encodingStr = strtolower(trim($encodingStr));

        return match ($encodingStr) {
            '7bit' => ENC7BIT,
            '8bit' => ENC8BIT,
            'binary' => ENCBINARY,
            'base64' => ENCBASE64,
            'quoted-printable' => ENCQUOTEDPRINTABLE,
            default => ENC7BIT,
        };
    }

    /**
     * Parse Content-Type header into type, subtype, and parameters.
     *
     * @param string $contentTypeStr Content-Type header value
     * @return array [type, subtype, parameters]
     */
    private static function parseContentType(string $contentTypeStr): array
    {
        $contentTypeStr = trim($contentTypeStr);

        if (empty($contentTypeStr)) {
            return [TYPETEXT, 'PLAIN', ['charset' => 'US-ASCII']];
        }

        // Split main type/subtype from parameters
        $parts = preg_split('/;\s*/', $contentTypeStr, 2);
        $mainType = trim($parts[0]);
        $paramString = $parts[1] ?? '';

        // Parse main type and subtype
        if (str_contains($mainType, '/')) {
            [$typeStr, $subtypeStr] = explode('/', $mainType, 2);
            $typeStr = strtolower(trim($typeStr));
            $subtypeStr = strtoupper(trim($subtypeStr));
        } else {
            $typeStr = 'text';
            $subtypeStr = 'PLAIN';
        }

        // Map MIME type to IMAP type constant
        $type = match ($typeStr) {
            'text' => TYPETEXT,
            'multipart' => TYPEMULTIPART,
            'message' => TYPEMESSAGE,
            'application' => TYPEAPPLICATION,
            'audio' => TYPEAUDIO,
            'image' => TYPEIMAGE,
            'video' => TYPEVIDEO,
            'model' => TYPEMODEL,
            default => TYPEOTHER,
        };

        // Parse parameters
        $parameters = [];
        if (!empty($paramString)) {
            preg_match_all('/(\w+)=(?:"([^"]*)"|([^;]+))/', $paramString, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $key = strtolower($match[1]);
                $value = $match[2] ?: $match[3];
                $parameters[$key] = trim($value);
            }
        }

        // Ensure charset parameter exists for text types
        if ($type === TYPETEXT && !isset($parameters['charset'])) {
            $parameters['charset'] = 'US-ASCII';
        }

        return [$type, $subtypeStr, $parameters];
    }

    /**
     * Get encoding constant from message.
     *
     * @param Message $message The message object
     * @return int Encoding constant
     */
    private static function getEncoding(Message $message): int
    {
        $encoding = $message->getHeader()->get('content-transfer-encoding');
        $encodingStr = is_array($encoding) ? $encoding[0] : (string)$encoding;
        $encodingStr = strtolower(trim($encodingStr));

        return match ($encodingStr) {
            '7bit' => ENC7BIT,
            '8bit' => ENC8BIT,
            'binary' => ENCBINARY,
            'base64' => ENCBASE64,
            'quoted-printable' => ENCQUOTEDPRINTABLE,
            default => ENC7BIT,
        };
    }

    /**
     * Build parts array for multipart messages.
     *
     * @param Message $message The message object
     * @param string|null $boundary Multipart boundary
     * @return array Array of part structures
     */
    private static function buildMultipartStructure(Message $message, ?string $boundary): array
    {
        $parts = [];

        // Try to parse body into parts using boundary
        if ($boundary) {
            $rawBody = $message->getRawBody();
            $sections = self::splitByBoundary($rawBody, $boundary);

            foreach ($sections as $sectionContent) {
                if (empty(trim($sectionContent))) {
                    continue;
                }

                $part = self::parsePart($sectionContent);
                if ($part) {
                    $parts[] = $part;
                }
            }
        }

        // If no parts found, try using attachments
        if (empty($parts)) {
            $textBody = $message->getTextBody();
            if (!empty($textBody)) {
                $textPart = new \stdClass();
                $textPart->type = TYPETEXT;
                $textPart->encoding = ENC7BIT;
                $textPart->ifsubtype = 1;
                $textPart->subtype = 'PLAIN';
                $textPart->ifdescription = 0;
                $textPart->ifid = 0;
                $textPart->lines = substr_count($textBody, "\n");
                $textPart->bytes = strlen($textBody);
                $textPart->ifdisposition = 0;
                $textPart->ifdparameters = 0;
                $textPart->ifparameters = 1;
                $textPart->parameters = [];

                $param = new \stdClass();
                $param->attribute = 'charset';
                $param->value = 'UTF-8';
                $textPart->parameters[] = $param;

                $parts[] = $textPart;
            }

            // Add HTML part if exists
            $htmlBody = $message->getHTMLBody();
            if (!empty($htmlBody)) {
                $htmlPart = new \stdClass();
                $htmlPart->type = TYPETEXT;
                $htmlPart->encoding = ENC7BIT;
                $htmlPart->ifsubtype = 1;
                $htmlPart->subtype = 'HTML';
                $htmlPart->ifdescription = 0;
                $htmlPart->ifid = 0;
                $htmlPart->lines = substr_count($htmlBody, "\n");
                $htmlPart->bytes = strlen($htmlBody);
                $htmlPart->ifdisposition = 0;
                $htmlPart->ifdparameters = 0;
                $htmlPart->ifparameters = 1;
                $htmlPart->parameters = [];

                $param = new \stdClass();
                $param->attribute = 'charset';
                $param->value = 'UTF-8';
                $htmlPart->parameters[] = $param;

                $parts[] = $htmlPart;
            }

            // Add attachments
            $attachments = $message->getAttachments();
            foreach ($attachments as $attachment) {
                $parts[] = self::buildAttachmentPart($attachment);
            }
        }

        return $parts;
    }

    /**
     * Split raw body by multipart boundary.
     *
     * @param string $rawBody The raw message body
     * @param string $boundary The boundary string
     * @return array Array of part contents
     */
    private static function splitByBoundary(string $rawBody, string $boundary): array
    {
        $delimiter = '--' . $boundary;
        $parts = explode($delimiter, $rawBody);

        // Remove first part (content before first boundary)
        array_shift($parts);

        // Remove last part if it's a closing boundary marker
        // RFC 2046: closing boundary is boundary delimiter followed by "--"
        if (!empty($parts)) {
            $lastPart = end($parts);
            // Detect closing boundary: optional whitespace, then "--", then line break or end
            if (is_string($lastPart) && preg_match('/^\s*--(?:\r?\n|$)/', $lastPart) === 1) {
                array_pop($parts);
            }
        }

        return $parts;
    }

    /**
     * Parse a single MIME part from raw content.
     *
     * @param string $content Raw part content including headers
     * @return \stdClass|null Part structure or null if invalid
     */
    private static function parsePart(string $content): ?\stdClass
    {
        // Split headers from body
        $headerEnd = strpos($content, "\r\n\r\n");
        if ($headerEnd === false) {
            $headerEnd = strpos($content, "\n\n");
        }

        if ($headerEnd === false) {
            return null;
        }

        $headerSection = substr($content, 0, $headerEnd);
        $bodySection = substr($content, $headerEnd + 4);

        // Parse headers
        $headers = [];
        $headerLines = preg_split('/\r?\n/', $headerSection);
        $currentHeader = null;

        foreach ($headerLines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            if (preg_match('/^([^:]+):\s*(.*)$/', $line, $matches)) {
                $currentHeader = strtolower(trim($matches[1]));
                $headers[$currentHeader] = trim($matches[2]);
            } elseif ($currentHeader && preg_match('/^\s+(.+)$/', $line, $matches)) {
                $headers[$currentHeader] .= ' ' . trim($matches[1]);
            }
        }

        // Build part structure
        $part = new \stdClass();

        $contentType = $headers['content-type'] ?? 'text/plain';
        [$type, $subtype, $parameters] = self::parseContentType($contentType);

        $part->type = $type;
        $part->ifsubtype = 1;
        $part->subtype = $subtype;

        // Encoding
        $encodingStr = strtolower(trim($headers['content-transfer-encoding'] ?? '7bit'));
        $part->encoding = match ($encodingStr) {
            '7bit' => ENC7BIT,
            '8bit' => ENC8BIT,
            'binary' => ENCBINARY,
            'base64' => ENCBASE64,
            'quoted-printable' => ENCQUOTEDPRINTABLE,
            default => ENC7BIT,
        };

        $part->ifdescription = isset($headers['content-description']) ? 1 : 0;
        $part->ifid = isset($headers['content-id']) ? 1 : 0;
        $part->lines = substr_count($bodySection, "\n");
        $part->bytes = strlen($bodySection);

        // Disposition
        $part->ifdisposition = isset($headers['content-disposition']) ? 1 : 0;
        if ($part->ifdisposition) {
            $disposition = explode(';', $headers['content-disposition'], 2);
            $part->disposition = strtolower(trim($disposition[0]));

            // Parse disposition parameters
            $part->ifdparameters = 1;
            $part->dparameters = [];

            if (isset($disposition[1])) {
                preg_match_all('/(\w+)=(?:"([^"]*)"|([^;]+))/', $disposition[1], $matches, PREG_SET_ORDER);
                foreach ($matches as $match) {
                    $dparam = new \stdClass();
                    $dparam->attribute = strtolower(trim($match[1]));
                    $dparam->value = trim($match[2] ?: $match[3], '"');
                    $part->dparameters[] = $dparam;
                }
            }
        } else {
            $part->ifdparameters = 0;
        }

        // Parameters
        $part->ifparameters = !empty($parameters) ? 1 : 0;
        $part->parameters = [];
        foreach ($parameters as $key => $value) {
            $param = new \stdClass();
            $param->attribute = strtolower($key);
            $param->value = $value;
            $part->parameters[] = $param;
        }

        // Handle nested multipart
        if ($type === TYPEMULTIPART && isset($parameters['boundary'])) {
            $part->parts = [];
            $nestedSections = self::splitByBoundary($bodySection, $parameters['boundary']);
            foreach ($nestedSections as $nestedContent) {
                if (!empty(trim($nestedContent))) {
                    $nestedPart = self::parsePart($nestedContent);
                    if ($nestedPart) {
                        $part->parts[] = $nestedPart;
                    }
                }
            }
        }

        return $part;
    }

    /**
     * Build structure for an attachment.
     *
     * @param Attachment $attachment The attachment object
     * @return \stdClass Part structure
     */
    private static function buildAttachmentPart(Attachment $attachment): \stdClass
    {
        $part = new \stdClass();
        $part->type = TYPEAPPLICATION;
        $part->encoding = ENCBASE64;
        $part->ifsubtype = 1;
        $part->subtype = 'OCTET-STREAM';
        $part->ifdescription = 0;
        $part->ifid = 0;
        $part->lines = 0;
        $part->bytes = $attachment->getSize();
        $part->ifdisposition = 1;
        $part->disposition = 'attachment';
        $part->ifdparameters = 1;
        $part->dparameters = [];

        $dparam = new \stdClass();
        $dparam->attribute = 'filename';
        $dparam->value = $attachment->getName();
        $part->dparameters[] = $dparam;

        $part->ifparameters = 1;
        $part->parameters = [];

        $param = new \stdClass();
        $param->attribute = 'name';
        $param->value = $attachment->getName();
        $part->parameters[] = $param;

        return $part;
    }
}
