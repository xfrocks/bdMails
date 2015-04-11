<?php

class bdMails_Transport_AmazonSes extends bdMails_Transport_Abstract
{
    const DATA_REGISTRY_SUBSCRIPTIONS = 'bdMails_asSubs';

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

    public static function doSns()
    {
        $contents = file_get_contents('php://input');
        $json = json_decode($contents, true);

        if (empty($json['Type'])) {
            return false;
        }

        switch ($json['Type']) {
            case 'SubscriptionConfirmation':
                if (!empty($json['SubscribeURL'])) {
                    // confirm subscription
                    file_get_contents($json['SubscribeURL']);
                }

                return false;
            case 'Notification':
                if (empty($json['Message'])) {
                    return false;
                } else {
                    // good, continue
                }
                break;
            default:
                return false;
        }

        $notification = json_decode($json['Message'], true);
        if (empty($notification['notificationType'])) {
            return false;
        }

        switch ($notification['notificationType']) {
            case 'AmazonSnsSubscriptionSucceeded':
                /** @var XenForo_Model_DataRegistry $dataRegistryModel */
                $dataRegistryModel = XenForo_Model::create('XenForo_Model_DataRegistry');
                $subscriptions = $dataRegistryModel->get(self::DATA_REGISTRY_SUBSCRIPTIONS);
                if (empty($subscriptions)) {
                    $subscriptions = array();
                }
                $subscriptions[$notification['message']] = true;
                $dataRegistryModel->set(self::DATA_REGISTRY_SUBSCRIPTIONS, $subscriptions);
                break;
            case 'Bounce':
                if (!bdMails_Option::get('bounce')) {
                    return false;
                }

                foreach ($notification['bounce']['bouncedRecipients'] as $recipient) {
                    $userId = XenForo_Application::getDb()->fetchOne('SELECT user_id FROM xf_user WHERE email = ?', $recipient['emailAddress']);
                    if (empty($userId)) {
                        continue;
                    }

                    $bounceType = ($notification['bounce']['bounceType'] == 'Permanent' ? 'hard' : 'soft');
                    $bounceDate = strtotime($notification['bounce']['timestamp']);

                    /** @var bdMails_Model_EmailBounce $emailBounceModel */
                    $emailBounceModel = XenForo_Model::create('bdMails_Model_EmailBounce');
                    $emailBounceModel->takeBounceAction($userId, $bounceType, $bounceDate, array_merge($notification['bounce'], array(
                        'email' => $recipient['emailAddress'],
                        'reason' => $recipient['diagnosticCode'],
                        'reason_code' => $recipient['status'],
                    )));
                }
                break;
            case 'Complaint':
                if (!bdMails_Option::get('bounce')) {
                    return false;
                }

                foreach ($notification['complaint']['complainedRecipients'] as $recipient) {
                    $userId = XenForo_Application::getDb()->fetchOne('SELECT user_id FROM xf_user WHERE email = ?', $recipient['emailAddress']);
                    if (empty($userId)) {
                        continue;
                    }

                    $bounceType = 'soft';
                    $bounceDate = strtotime($notification['complaint']['timestamp']);

                    /** @var bdMails_Model_EmailBounce $emailBounceModel */
                    $emailBounceModel = XenForo_Model::create('bdMails_Model_EmailBounce');
                    $emailBounceModel->takeBounceAction($userId, $bounceType, $bounceDate, array_merge($notification['complaint'], array(
                        'email' => $recipient['emailAddress'],
                        'reason' => $notification['complaint']['complaintFeedbackType'],
                    )));
                }
                break;
        }

        return true;
    }
}
