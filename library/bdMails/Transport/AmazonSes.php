<?php

class bdMails_Transport_AmazonSes extends bdMails_Transport_Abstract
{
    public static $apiUrl = 'https://email.%s.amazonaws.com';

    protected $_apiUrl;
    protected $_accessKey;
    protected $_privateKey;
    protected $_domain;

    public function __construct($region, $accessKey, $privateKey, $domain, $options = array())
    {
        parent::__construct($options);

        $this->_apiUrl = sprintf(self::$apiUrl, $region);
        $this->_accessKey = $accessKey;
        $this->_privateKey = $privateKey;
        $this->_domain = $domain;
    }

    protected function _buildBody()
    {
        parent::_buildBody();

        $from = $this->bdMails_validateFromEmail($this->_mail->getFrom());
        $this->_headers['From'] = array($from, 'append' => true);
    }

    public function bdMails_validateFromEmail($fromEmail)
    {
        return $this->_bdMails_validateFromEmailWithDomain($fromEmail, $this->_domain);
    }

    protected function _bdMails_sendMail()
    {
        $client = XenForo_Helper_Http::getClient($this->_apiUrl);

        $date = gmdate('D, d M Y H:i:s O');
        $client->setHeaders('Date', $date);
        $client->setHeaders('X-Amzn-Authorization', $this->_bdMails_getAuthorizationHeader($date));

        $request['Action'] = 'SendRawEmail';
        $request['RawMessage.Data'] = base64_encode(sprintf("%s\n%s\n", $this->header, $this->body));

        $client->setEncType(Zend_Http_Client::ENC_URLENCODED);
        $client->setParameterPost($request);

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

    protected function _bdMails_getAuthorizationHeader($date)
    {
        return sprintf(
            'AWS3-HTTPS AWSAccessKeyId=%s,Algorithm=HmacSHA256,Signature=%s',
            $this->_accessKey,
            base64_encode(hash_hmac('sha256', $date, $this->_privateKey, TRUE))
        );
    }

}
