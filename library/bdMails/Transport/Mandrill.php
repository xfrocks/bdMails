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

    public function bdMails_getWebhooksList()
    {
        $url = self::getWebhookUrl($this->_domain);

        $client = XenForo_Helper_Http::getClient(sprintf('%s/webhooks/list.json', self::$apiUrl));
        $client->setRawData(json_encode(array(
            'key' => $this->_apiKey,
        )));

        $response = $client->request('POST')->getBody();

        $hardBounce = false;
        $softBounce = false;

        $responseArray = @json_decode($response, true);
        if (!empty($responseArray)) {
            foreach ($responseArray as $webhook) {
                if (!empty($webhook['url'])
                    && $webhook['url'] === $url
                    && !empty($webhook['events'])
                ) {
                    $hardBounce = (in_array('hard_bounce', $webhook['events'], true));
                    $softBounce = (in_array('soft_bounce', $webhook['events'], true));
                }
            }
        }

        return array($hardBounce, $softBounce);
    }

    public function bdMails_postWebhooksAdd($hardBounce, $softBounce)
    {
        $events = array();
        if ($hardBounce === false) {
            $events[] = 'hard_bounce';
        }
        if ($softBounce === false) {
            $events[] = 'soft_bounce';
        }
        if (empty($events)) {
            return true;
        }

        $url = self::getWebhookUrl($this->_domain);

        $client = XenForo_Helper_Http::getClient(sprintf('%s/webhooks/add.json', self::$apiUrl));
        $client->setRawData(json_encode(array(
            'key' => $this->_apiKey,
            'url' => $url,
            'description' => XenForo_Application::getOptions()->get('boardTitle'),
            'events' => $events,
        )));

        $response = $client->request('POST')->getBody();

        $success = false;
        $responseArray = @json_decode($response, true);
        if (!empty($responseArray['url'])
            && $responseArray['url'] === $url
        ) {
            $success = true;
        }

        return $success;
    }

    public static function getWebhookUrl($domain)
    {
        if (XenForo_Application::debugMode()) {
            $configUrl = XenForo_Application::getConfig()->get('bdMails_webhookUrl');
            if (!empty($configUrl)) {
                return sprintf('%s?md5=%s', $configUrl, md5($domain));
            }
        }

        return sprintf(
            '%s/bdmails/mandrill.php?md5=%s',
            rtrim(XenForo_Application::getOptions()->get('boardUrl'), '/'),
            md5($domain)
        );
    }

    public static function doWebhook()
    {
        if (empty($_REQUEST['md5'])) {
            return false;
        }

        if (empty($_POST['mandrill_events'])) {
            // looks like a HEAD request for verification from Mandrill
            // we can't rely on REQUEST_METHOD due to different server implementation though
            $subscriptions = self::_getSubscriptions();
            $subscriptions['mandrill'][$_REQUEST['md5']] = true;
            self::_setSubscriptions($subscriptions);

            return false;
        }

        $events = json_decode($_POST['mandrill_events'], true);
        if (empty($events)) {
            return false;
        }

        foreach ($events as $event) {
            if (!bdMails_Option::get('bounce') ||
                empty($event['event']) ||
                !in_array($event['event'], array('hard_bounce', 'soft_bounce'), true) ||
                empty($event['msg'])
            ) {
                continue;
            }
            $msgRef =& $event['msg'];

            $userId = XenForo_Application::getDb()->fetchOne(
                'SELECT user_id FROM xf_user WHERE email = ?',
                $msgRef['email']
            );
            if (empty($userId)) {
                continue;
            }

            $bounceType = ($event['event'] === 'hard_bounce' ? 'hard' : 'soft');
            $bounceDate = $msgRef['ts'];

            /** @var bdMails_Model_EmailBounce $emailBounceModel */
            $emailBounceModel = XenForo_Model::create('bdMails_Model_EmailBounce');
            $emailBounceModel->takeBounceAction($userId, $bounceType, $bounceDate, array_merge($msgRef, array(
                'reason' => $msgRef['diag'],
            )));
        }

        return true;
    }
}
