<?php

class bdMails_Transport_Mandrill extends bdMails_Transport_Abstract
{
    public static $apiUrl = 'https://mandrillapp.com/api/1.0';

    protected $_apiKey;
    protected $_domain;

    public function __construct($apiKey, $domain, $options = array())
    {
        parent::__construct($options);

        $this->_apiKey = $apiKey;
        $this->_domain = $domain;
    }

    public function bdMails_getSupportedFeatures()
    {
        return array_merge(parent::bdMails_getSupportedFeatures(), array(self::FEATURE_BOUNCE => true));
    }

    public function bdMails_bounceList()
    {
        $client = XenForo_Helper_Http::getClient(sprintf('%s/rejects/list.json', self::$apiUrl));
        $client->setRawData(json_encode(array('key' => $this->_apiKey)));
        $response = $client->request('POST')->getBody();

        $responseArray = @json_decode($response, true);
        $list = array();

        if (!empty($responseArray)) {
            foreach ($responseArray as $reject) {
                if (is_array($reject)) {
                    if ($reject['reason'] !== 'hard-bounce') {
                        // TODO: should we only get hard bounces?
                        // continue;
                    }

                    $list[$reject['email']] = array(
                        'email' => $reject['email'],
                        'reason' => $reject['detail'] ? $reject['detail'] : $reject['reason'],
                    );
                }
            }
        }

        return $list;
    }

    public function bdMails_bounceAdd($email)
    {
        return false;
    }

    public function bdMails_bounceDelete($email)
    {
        return false;
    }

    public function bdMails_validateFromEmail($fromEmail)
    {
        return $this->_bdMails_validateFromEmailWithDomain($fromEmail, $this->_domain);
    }

    protected function _bdMails_sendMail()
    {
        $message = array(
            'to' => array(),
            'headers' => array()
        );
        $client = XenForo_Helper_Http::getClient(sprintf('%s/messages/send.json', self::$apiUrl));

        $headers = $this->_bdMails_parseHeaderAsKeyValue($this->header);
        foreach ($headers as $headerKey => $headerValue) {
            $skip = false;

            switch ($headerKey) {
                case 'From':
                    list($messageFromEmail, $messageFromName) = $this->_bdMails_parseFormattedAddress($headerValue);
                    if (!empty($messageFromEmail)) {
                        $message['from_email'] = $messageFromEmail;
                        if (!empty($messageFromName)) {
                            $message['from_name'] = $messageFromName;
                        }
                    }
                    $skip = true;
                    break;
                case 'Content-Type':
                    // no need because Mandrill handle the MIME stuff
                case 'Date':
                    // TODO: skip for now because Mandrill sent_at feature costs extra
                case 'MIME-Version':
                    // no need because Mandrill handle the MIME stuff
                case 'Return-Path':
                    // no need because Mandrill does bounce management
                case 'Subject':
                case 'To':
                    // handled below
                    $skip = true;
                    break;
            }

            if (!$skip) {
                $message['headers'][$headerKey] = $headerValue;
            }
        }

        if (empty($message['from_email'])) {
            $message['from_email'] = $this->_mail->getFrom();
        }

        $recipients = explode(',', $this->recipients);
        foreach ($recipients as $recipient) {
            list($recipientEmail, $recipientName) = $this->_bdMails_parseFormattedAddress($recipient);

            if (!empty($recipientEmail)) {
                $messageTo = array();
                $messageTo['email'] = $recipientEmail;
                if (!empty($recipientName)) {
                    $messageTo['name'] = $recipientName;
                }

                $message['to'][] = $messageTo;
            }
        }

        $message['subject'] = $this->_bdMails_getSubject();

        $bodyTextMime = $this->_mail->getBodyText();
        if (!empty($bodyTextMime)) {
            $bodyTextMime->encoding = '';
            $message['text'] = $bodyTextMime->getContent();
        }

        $bodyHtmlMime = $this->_mail->getBodyHtml();
        if (!empty($bodyHtmlMime)) {
            $bodyHtmlMime->encoding = '';
            $message['html'] = $bodyHtmlMime->getContent();
        }

        // `Sender` header validation
        if (!empty($message['headers']['Sender'])) {
            // Mandrill drops `Sender` so we have to move the
            // `From` address to `Reply-To` header, and use the address
            // in `Sender` for that.
            $message['headers']['Reply-To'] = $message['from_email'];
            $message['from_email'] = $message['headers']['Sender'];
            unset($message['headers']['Sender']);
        }

        // From address validation
        $message['from_email'] = $this->bdMails_validateFromEmail($message['from_email']);

        $client->setRawData(json_encode(array(
            'key' => $this->_apiKey,
            'message' => $message,
            'async' => true,
        )));

        $response = $client->request('POST')->getBody();

        $success = false;
        $responseArray = @json_decode($response, true);
        if (!empty($responseArray)) {
            $first = reset($responseArray);
            if (!empty($first['status']) AND in_array($first['status'], array(
                    'sent',
                    'queued'
                ))
            ) {
                $success = true;
            }
        }

        return array(
            $message,
            $response,
            $success,
        );
    }

}
