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

use Microsoft\Graph\Graph;
use Microsoft\Graph\Generated\Models\Message as GraphMessage;
use Microsoft\Graph\Exception\GraphException;
use Microsoft\Graph\Generated\Users\Item\SendMail\SendMailPostRequestBody;
use Microsoft\Graph\Generated\Models\ItemBody;
use Microsoft\Graph\Generated\Models\Importance;
use Microsoft\Graph\Generated\Models\BodyType;
use Microsoft\Graph\Generated\Models\Recipient;
use Microsoft\Graph\Generated\Models\EmailAddress;
use Microsoft\Graph\Generated\Models\Attachment as GraphAttachment;
use Microsoft\Graph\Generated\Models\FileAttachment as GraphFileAttachment;

/**
 * Class GraphMailer
 * Sends email using Microsoft Graph API.
 *
 * @extends BaseMailer
 */
class GraphMailer extends BaseMailer
{
    /**
     * Microsoft Graph client instance.
     *
     * @var GraphProxy
     */
    protected $graphClient;

    /**
     * @var GraphMessage
     */
    protected $graphMessage;

    /**
     * @var ExtAPIMicrosoftEmail
     */
    protected $api;

    /**
     * Sets up the Microsoft Graph client with an access token.
     *
     * @param OutboundEmailConfiguration $config
     */
    public function __construct(OutboundEmailConfiguration $config)
    {
        parent::__construct($config);
        $this->api = new ExtAPIMicrosoftEmail();
        $this->graphClient = $this->getClient();
    }

    /**
     * Returns the Microsoft client used to query the Microsoft Graph API
     *
     * @return GraphProxy
     */
    public function getClient(string $refreshToken = 'token'): GraphProxy
    {
        $eapmId = $this->config->getEAPMId();
        $refreshToken = $this->api->getRefreshToken($eapmId);
        $refreshToken = !empty($refreshToken) ? $refreshToken : 'token';
        return $this->api->getClient($refreshToken);
    }

    /**
     * Sends an email using Microsoft Graph API.
     *
     * @throws MailerException
     */
    public function send()
    {
        try {
            $this->graphMessage = new GraphMessage();

            // Build the email message
            $this->buildMessage();

            $requestBody = new SendMailPostRequestBody();
            $requestBody->setMessage($this->graphMessage);

            // Send the email via Microsoft Graph API
            $this->graphClient->me()->sendMail()->post($requestBody)->wait();

            $GLOBALS['log']->info('Email sent successfully using Microsoft Graph API.');
        } catch (GraphException $e) {
            $errorMessage = $this->getDetailedErrorMessage($e);
            $GLOBALS['log']->fatal('Failed to send email via Microsoft Graph API: ' . $errorMessage);
            throw new MailerException($errorMessage, MailerException::FailedToSend);
        } catch (\InvalidArgumentException $e) {
            $errorMessage = 'Microsoft Graph API configuration error: ' . $e->getMessage();
            $GLOBALS['log']->fatal($errorMessage);
            throw new MailerException($errorMessage, MailerException::InvalidConfiguration);
        } catch (\Exception $e) {
            $errorMessage = 'Unexpected error sending email via Microsoft Graph API: ' . $e->getMessage();
            $GLOBALS['log']->fatal($errorMessage);
            throw new MailerException($errorMessage, MailerException::FailedToSend);
        }
    }
    
    /**
     * Extracts detailed error information from GraphException
     *
     * @param GraphException $e
     * @return string
     */
    private function getDetailedErrorMessage(GraphException $e): string
    {
        $message = $e->getMessage();
        
        // Check for common authentication/authorization errors
        if (strpos($message, 'Unauthorized') !== false || strpos($message, '401') !== false) {
            return 'Authentication failed. Please check your OAuth2 configuration and re-authorize the account.';
        }
        
        if (strpos($message, 'Forbidden') !== false || strpos($message, '403') !== false) {
            return 'Access forbidden. Please ensure the application has the required permissions to send emails.';
        }
        
        if (strpos($message, 'tenant') !== false && strpos($message, 'not found') !== false) {
            return 'Invalid tenant configuration. Please check your Single Tenant settings.';
        }
        
        // Return the original message if no specific pattern is found
        return 'Microsoft Graph API error: ' . $message;
    }

    /**
     *
     * transfer headers
     * @return void
     *
     */
    protected function transferHeaders()
    {
        // will throw an exception if an error occurs; will let it bubble up
        $headers = $this->headers->packageHeaders();

        foreach ($headers as $key => $value) {
            switch ($key) {
                case EmailHeaders::From:
                    $parsedValue = $this->getEmailAndName($value);
                    $name = $parsedValue[1] ?? '';
                    $recipient = new Recipient();
                    $emailAddress = new EmailAddress();
                    $emailAddress->setAddress($parsedValue[0] ?? null);
                    $emailAddress->setName($name);
                    $recipient->setEmailAddress($emailAddress);
                    $this->graphMessage->setFrom($recipient);
                    break;
                case EmailHeaders::ReplyTo:
                    $parsedValue = $this->getEmailAndName($value);
                    $email = $parsedValue[0] ?? [];
                    $emailAddress = new EmailAddress();
                    if (SugarEmailAddress::isValidEmail($email)) {
                        $emailAddress->setAddress($email);
                        if (!empty($parsedValue[1])) {
                            $emailAddress->setName($parsedValue[1]);
                        }
                    } else {
                        $emailAddress = null;
                    }
                    if ($emailAddress) {
                        $recipient = new Recipient();
                        $recipient->setEmailAddress($emailAddress);
                        $this->graphMessage->setReplyTo([$recipient]);
                    } else {
                        $this->graphMessage->setReplyTo([]);
                    }
                    break;
                case EmailHeaders::Sender:
                    $parsedValue = $this->getEmailAndName($value);
                    $name = $parsedValue[1] ?? '';
                    $sender = new Recipient();
                    $emailAddress = new EmailAddress();
                    $emailAddress->setAddress($parsedValue[0] ?? null);
                    $emailAddress->setName($name);
                    $sender->setEmailAddress($emailAddress);
                    $this->graphMessage->setSender($sender);
                    break;
                case EmailHeaders::MessageId:
                    // TBD
                    break;
                case EmailHeaders::Priority:
                    $priority = $this->convertPriority($value);
                    if ($priority) {
                        $this->graphMessage->setImportance(new Importance($priority));
                    }
                    break;
                case EmailHeaders::DispositionNotificationTo:
                    // TBD
                    break;
                case EmailHeaders::Subject:
                    // perform character set and HTML character translations on the subject
                    $value = $this->formatValue($value);
                    $this->graphMessage->setSubject($value);
                    break;
                default:
                    // it's not known, so it must be a custom header; add it to PHPMailer's custom headers array
                    //TODO:
                    break;
            }
        }
    }

    /**
     * Formats the value based on the format and locale.
     *
     * @param string|null $value The value to format.
     * @return string The formatted value.
     */
    protected function formatValue(?string $value): string
    {
        if (empty($value)) {
            return '';
        }

        return $this->formatter->translateCharacters(
            $value,
            $this->config->getLocale(),
            $this->config->getCharset()
        );
    }

    /**
     * parse email and name from the value
     *
     * @param mixed $value
     * @return array
     */
    protected function getEmailAndName(mixed $value): array
    {
        $name = '';
        $email = '';
        if (empty($value)) {
            return [$email, $name];
        } elseif (is_string($value)) {
            // if the value is a string, it should be an email address
            if (SugarEmailAddress::isValidEmail($value)) {
                $email = $value;
            }
            return [$email, $name];
        } elseif (!is_array($value)) {
            // if it's not a string or an array, set it to an empty array
            return [$email, $name];
        }

        $email = $value[0] ?? '';
        if (!empty($value[1])) {
            // perform character set and HTML character translations on the From name
            $name = $this->formatValue($value[1]);
        }

        return [$email, $name];
    }

    /**
     * convert provided priority value to Importance enum
     *
     * @param mixed $value
     * @return null|string
     */
    protected function convertPriority(mixed $value): ?string
    {
        if ($value === Importance::HIGH || $value === Importance::NORMAL || $value === Importance::LOW) {
            return $value;
        }

        if (is_int($value)) {
            switch ($value) {
                case 1:
                case 2:
                    return Importance::HIGH;
                case 3:
                case 4:
                    return Importance::NORMAL;
                case 5:
                case 6:
                    return Importance::LOW;
            }
        } elseif (is_string($value)) {
            $value = strtolower($value);
            if ($value === 'high') {
                return Importance::HIGH;
            } elseif ($value === 'normal') {
                return Importance::NORMAL;
            } elseif ($value === 'low') {
                return Importance::LOW;
            }
        }
        return null;
    }

    /**
     * Transfers the recipients.
     *
     * @return void
     */
    protected function transferRecipients()
    {
        // Transfer To recipients
        $recipients = [];
        foreach ($this->recipients->getTo() as $recipient) {
            $toRecipient = new Recipient();
            $emailAddress = new EmailAddress();
            if (empty($recipient->getEmail())) {
                continue; // skip if email is empty
            }
            $emailAddress->setAddress($recipient->getEmail());
            if (!empty($recipient->getName())) {
                $emailAddress->setName($recipient->getName());
            }
            $toRecipient->setEmailAddress($emailAddress);
            $recipients[] = $toRecipient;
        }
        if (!empty($recipients)) {
            // Set the To recipients in the graphMessage
            $this->graphMessage->setToRecipients($recipients);
        }

        $recipients = [];
        foreach ($this->recipients->getCc() as $recipient) {
            $toRecipient = new Recipient();
            $emailAddress = new EmailAddress();
            if (empty($recipient->getEmail())) {
                continue; // skip if email is empty
            }
            $emailAddress->setAddress($recipient->getEmail());
            if (!empty($recipient->getName())) {
                $emailAddress->setName($recipient->getName());
            }
            $toRecipient->setEmailAddress($emailAddress);
            $recipients[] = $toRecipient;
        }
        if (!empty($recipients)) {
            $this->graphMessage->setCcRecipients($recipients);
        }

        $recipients = [];
        foreach ($this->recipients->getBcc() as $recipient) {
            $toRecipient = new Recipient();
            $emailAddress = new EmailAddress();
            if (empty($recipient->getEmail())) {
                continue; // skip if email is empty
            }
            $emailAddress->setAddress($recipient->getEmail());
            if (!empty($recipient->getName())) {
                $emailAddress->setName($recipient->getName());
            }
            $toRecipient->setEmailAddress($emailAddress);
            $recipients[] = $toRecipient;
        }

        if (!empty($recipients)) {
            $this->graphMessage->setBccRecipients($recipients);
        }
    }

    /**
     * transfer body
     * @return void
     */
    protected function transferBody(): void
    {
        $this->graphMessage->setBody(null);
        $textBody = $this->handleSpecialChars($this->textBody);
        $htmlBody = $this->handleSpecialChars($this->htmlBody);

        $hasText = $this->hasMessagePart($textBody); // is there a plain-text part?
        $hasHtml = $this->hasMessagePart($htmlBody); // is there an HTML part?

        $itemBody = new ItemBody();
        // perform some preparations on the plain-text part, if one exists
        if ($hasText) {
            $itemBody->setContentType(new BodyType(BodyType::TEXT));
            // perform character set translations on the plain-text body
            $textBody = $this->prepareTextBody($this->textBody);

            $useBase64Encoding = false;
            $wordWrap = $this->getWordwrap();
            $textBodyLines = explode("\n", $textBody);
            $numberOfLines = safeCount($textBodyLines);

            for ($i = 0; !$useBase64Encoding && $i < $numberOfLines; $i++) {
                if (strlen($textBodyLines[$i]) > $wordWrap) {
                    $useBase64Encoding = true;
                }
            }

            if ($useBase64Encoding) {
                // if the text body is too long, we need to encode it in base64
                $textBody = base64_encode($textBody);
            }
            $itemBody->setContentType(new BodyType(BodyType::TEXT));
            $itemBody->setContent($textBody);
        }

        // add the HTML part to PHPMailer, if one exists
        if ($hasHtml) {
            $itemBody->setContentType(new BodyType(BodyType::HTML));
            // perform character set translations HTML body
            $htmlBody = $this->prepareHtmlBody($htmlBody);
            $itemBody->setContent($htmlBody);
        }

        $this->graphMessage->setBody($itemBody);
    }

    /**
     * wrapper of from_html method.
     *
     * @param string|null $part The message part to check.
     * @return bool True if the part is not empty, false otherwise.
     */
    protected function handleSpecialChars(?string $string): string
    {
        if (empty($string)) {
            return '';
        }
        return from_html($string);
    }

    protected function getWordWrap(): int
    {
        // Get the word wrap value from the configuration
        $wordWrap = $this->config->getWordwrap();
        if (empty($wordWrap) || !is_numeric($wordWrap)) {
            $wordWrap = 78; // Default word wrap value
        }
        return (int)$wordWrap;
    }

    /**
     * prepare the HTML body of the message
     *
     * @access protected
     * @param string $body required The HTML body that is to be translated.
     * @return string The compliant and translated body.
     */
    protected function prepareHtmlBody($body): string
    {
        $formatted = $this->formatter->formatHtmlBody($body);
        $body = $formatted['body'];
        $images = $formatted['images'];

        foreach ($images as $embeddedImage) {
            $this->addAttachment($embeddedImage);
        }
        // perform character set and HTML character translations on the HTML body
        $body = $this->formatValue($body);
        return $body ?? '';
    }

    /**
     *  build the plain-text body of the message
     *
     * @param string $part The message part to check.
     * @return bool True if the part is not empty, false otherwise.
     */
    protected function prepareTextBody($body): string
    {
        $body = $this->formatter->formatTextBody($body);

        // perform character set and HTML character translations on the plain-text body
        $body = $this->formatValue($body);
        return $body ?? '';
    }

    /**
     * transfer attachments
     * @return void
     * @throws MailerException
     */
    protected function transferAttachments(): void
    {
        foreach ($this->attachments as $attachment) {
            if ($attachment instanceof EmbeddedImage) {
                $this->addImageAttachment($attachment);
            } elseif ($attachment instanceof Attachment) {
                $this->addFileAttachment($attachment);
            } else {
                LoggerManager::getLogger()->fatal('Invalid attachment type');
                throw new MailerException('Invalid attachment type', MailerException::InvalidAttachment);
            }
        }
    }

    /**
     * Adds an image attachment to the graphMessage.
     * @param EmbeddedImage $image
     * @param string $imageName
     * @return void
     */
    protected function addImageAttachment(EmbeddedImage $image) : void
    {
        $imageName = $this->formatValue($image->getName());

        // Read the image file and encode it in Base64
        $imagePath = $image->getPath();
        $imageContent = base64_encode(file_get_contents($imagePath));

        // Create a new GraphAttachment instance
        $attachment = new GraphFileAttachment();
        $attachment->setOdataType('#microsoft.graph.fileAttachment');
        $attachment->setName($imageName);
        $mimeType = $image->getMimeType();
        if (empty($mimeType)) {
            $mimeType = $this->getFileMimeType($imagePath);
        }
        $attachment->setContentType($mimeType);
        $attachment->setContentBytes(\GuzzleHttp\Psr7\Utils::streamFor($imageContent));
        if (!empty($image->getCid())) {
            $attachment->setContentId($image->getCid());
            $attachment->setIsInline(true);
        }

        // Add the attachment to the graphMessage
        $this->addOneAttachment($attachment);
    }

    /**
     * Adds a file attachment to the graphMessage.
     *
     * @param Attachment $attachment
     * @return void
     * @throws MailerException
     */
    protected function addFileAttachment(Attachment $attachment) : void
    {
        // perform character set and HTML character translations on the file name
        $name = $this->formatValue($attachment->getName());

        // Read the file content and encode it in Base64
        $fileContent = base64_encode(file_get_contents($attachment->getPath()));

        // Create a new GraphAttachment instance
        $att = new GraphFileAttachment();
        $att->setOdataType('#microsoft.graph.fileAttachment');
        $att->setName($name);
        $att->setContentType($attachment->getMimeType());
        $att->setContentBytes(\GuzzleHttp\Psr7\Utils::streamFor($fileContent));

        $this->addOneAttachment($att);
    }

    /**
     * Get the MIME type of a file.
     *
     * @param string $path The path to the file.
     * @return string The MIME type of the file.
     */
    protected function getFileMimeType($path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $path);

        if ($mimeType == false) {
            $mimeType = 'application/octet-stream'; // Fallback to a default MIME type if it cannot be determined
        }

        finfo_close($finfo);
        return $mimeType;
    }

    /**
     * Adds a single attachment to the graphMessage.
     *
     * @param GraphAttachment $attachment
     * @return void
     */
    protected function addOneAttachment(GraphAttachment $attachment): void
    {
        $attachments = $this->graphMessage->getAttachments() ?? [];
        $attachments[] = $attachment;
        $this->graphMessage->setAttachments($attachments);
    }

    /**
     * Builds the email message in the format required by Microsoft Graph API.
     *
     * @return void
     */
    protected function buildMessage(): void
    {
        $this->transferHeaders();
        $this->transferRecipients();
        $this->transferBody();
        $this->transferAttachments();
    }

    public function connect()
    {
        // No need to connect explicitly for Microsoft Graph API
        // The access token is handled by the Graph client
    }

    public function setFixedResponseTimeDuration(bool $value): void
    {
    }
}
