<?php

class bdMails_Transport_Mailgun extends bdMails_Transport_Abstract
{
    public static $apiUrl = 'https://api.mailgun.net/v3';

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
        $request = array();
        $client = XenForo_Helper_Http::getClient(sprintf('%s/%s/messages', self::$apiUrl, $this->_domain));

        $client->setAuth('api', $this->_apiKey);

        $headers = $this->_bdMails_parseHeaderAsKeyValue($this->header);
        foreach ($headers as $headerKey => $headerValue) {
            $skip = false;

            switch ($headerKey) {
                case 'From':
                    $request['from'] = $headerValue;
                    $skip = true;
                    break;
                case 'Content-Type':
                case 'MIME-Version':
                    // no need because Mailgun handle the MIME stuff
                case 'Return-Path':
                    // no need because Mailgun does bounce management
                case 'Subject':
                case 'To':
                    // handled below
                    $skip = true;
                    break;
            }

            if (!$skip) {
                $request[sprintf('h:%s', $headerKey)] = $headerValue;
            }
        }

        if (empty($request['from'])) {
            $request['from'] = $this->_mail->getFrom();
        }

        $request['to'] = $this->recipients;
        $request['subject'] = $this->_bdMails_getSubject();

        $bodyTextMime = $this->_mail->getBodyText();
        if (!empty($bodyTextMime)) {
            $bodyTextMime->encoding = '';
            $request['text'] = $bodyTextMime->getContent();
        }

        $bodyHtmlMime = $this->_mail->getBodyHtml();
        if (!empty($bodyHtmlMime)) {
            $bodyHtmlMime->encoding = '';
            $request['html'] = $bodyHtmlMime->getContent();
        }

        // `Sender` header validation
        if (!empty($request['h:Sender'])) {
            // Mailgun does forward `Sender` header but doing so is risky
            // because according to my test, receiver server may refuse
            // if the `From` domain blacklists foreign servers.
            //
            // To make sure the email gets delivered, we will move the
            // `From` address to `Reply-To` header, and use the address
            // in `Sender` for that. Elimating `Sender` header altogether.
            $request['h:Reply-To'] = $request['from'];
            $request['from'] = $request['h:Sender'];
            unset($request['h:Sender']);
        }

        // `From` address validation
        $request['from'] = $this->bdMails_validateFromEmail($request['from']);

        foreach ($request as $key => $param) {
            $client->setParameterPost($key, $param);
        }

        $response = $client->request('POST');
        $responseBody = $response->getBody();

        $success = false;
        if ($response->getStatus() == 200) {
            $success = true;
        }

        return array(
            $request,
            $responseBody,
            $success,
        );
    }

    public function bdMails_getWebhooks()
    {
        $url = self::getWebhookUrl();

        $client = XenForo_Helper_Http::getClient(sprintf('%s/domains/%s/webhooks', self::$apiUrl, $this->_domain));
        $client->setAuth('api', $this->_apiKey);

        $response = $client->request('GET')->getBody();

        // $$event == null: existing webhook not found
        // $$event == false: webhook exists but incorrect url
        // $$event == true: webhook has been setup correctly
        $bounce = null;

        $responseArray = @json_decode($response, true);
        if (!empty($responseArray['webhooks'])) {
            foreach ($responseArray['webhooks'] as $event => $webhook) {
                $$event = false;

                if (!empty($webhook['url'])
                    && $webhook['url'] === $url
                ) {
                    $$event = true;
                }
            }
        }

        return array($bounce);
    }

    public function bdMails_postWebhooks($bounce)
    {
        $events = array();
        if (!$bounce) {
            $events[] = 'bounce';
        }

        $url = self::getWebhookUrl();
        $postedCount = 0;

        foreach ($events as $event) {
            if ($$event === null) {
                $client = XenForo_Helper_Http::getClient(sprintf('%s/domains/%s/webhooks',
                    self::$apiUrl, $this->_domain));
                $client->setAuth('api', $this->_apiKey);
                $client->setParameterPost(array(
                    'id' => $event,
                    'url' => $url,
                ));
                $response = $client->request('POST')->getBody();
            } else {
                $client = XenForo_Helper_Http::getClient(sprintf('%s/domains/%s/webhooks/%s',
                    self::$apiUrl, $this->_domain, $event));
                $client->setAuth('api', $this->_apiKey);
                $client->setRawData(sprintf('url=%s', rawurlencode($url)), 'application/x-www-form-urlencoded');
                $response = $client->request('PUT')->getBody();
            }

            $responseArray = @json_decode($response, true);
            if (!empty($responseArray['webhook']['url'])
                && $responseArray['webhook']['url'] === $url
            ) {
                $postedCount++;
            }
        }

        $result = count($events) === $postedCount;

        if ($result) {
            // because Mailgun does not send any request to test our url
            // we will have to mark it as done manually
            /** @var XenForo_Model_DataRegistry $dataRegistryModel */
            $dataRegistryModel = XenForo_Model::create('XenForo_Model_DataRegistry');
            $subscriptions = $dataRegistryModel->get(self::DATA_REGISTRY_SUBSCRIPTIONS);
            if (empty($subscriptions)) {
                $subscriptions = array();
            }
            $subscriptions['mailgun'][$this->_domain] = true;
            $dataRegistryModel->set(self::DATA_REGISTRY_SUBSCRIPTIONS, $subscriptions);
        }

        return $result;
    }


    public function bdMails_doWebhook()
    {
        if (empty($_POST['timestamp'])
            || empty($_POST['token'])
            || empty($_POST['signature'])
            || empty($_POST['domain'])
            || empty($_POST['event'])
            || empty($_POST['recipient'])
        ) {
            return false;
        }

        if (!$this->bdMails_validateHook($_POST['timestamp'], $_POST['token'], $_POST['signature'])) {
            XenForo_Error::logError('Invalid Mailgun webhook request detected.');
            return false;
        }

        if ($_POST['domain'] !== $this->_domain
            || $_POST['event'] !== 'bounced'
        ) {
            return false;
        }

        $userId = XenForo_Application::getDb()->fetchOne('SELECT user_id FROM xf_user WHERE email = ?', $_POST['recipient']);
        if (empty($userId)) {
            return false;
        }

        $bounceType = 'hard';
        $bounceDate = $_POST['timestamp'];

        /** @var bdMails_Model_EmailBounce $emailBounceModel */
        $emailBounceModel = XenForo_Model::create('bdMails_Model_EmailBounce');
        $emailBounceModel->takeBounceAction($userId, $bounceType, $bounceDate, array_merge($_POST, array(
            'email' => $_POST['recipient'],
            'reason' => $_POST['error'],
        )));

        return true;
    }

    public function bdMails_validateHook($timestamp, $token, $signature)
    {
        return hash_hmac('sha256', $timestamp . $token, $this->_apiKey) === $signature;
    }

    public static function getWebhookUrl()
    {
        if (XenForo_Application::debugMode()) {
            $configUrl = XenForo_Application::getConfig()->get('bdMails_webhookUrl');
            if (!empty($configUrl)) {
                return $configUrl;
            }
        }

        return sprintf('%s/bdmails/mailgun.php', rtrim(XenForo_Application::getOptions()->get('boardUrl'), '/'));
    }
}
