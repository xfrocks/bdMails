<?php

class bdMails_Transport_AmazonSes extends bdMails_Transport_Abstract
{
    protected $_region;
    protected $_accessKey;
    protected $_privateKey;
    protected $_domain;

    public function __construct($region, $accessKey, $privateKey, $domain, $options = array())
    {
        parent::__construct($options);

        $this->_region = $region;
        $this->_accessKey = $accessKey;
        $this->_privateKey = $privateKey;
        $this->_domain = $domain;
    }

    protected function _buildBody()
    {
        parent::_buildBody();

        $from = '';
        if (!empty($this->_headers['From'])) {
            $from = $this->_headers['From'][0];
        }
        if (empty($from)) {
            $from = $this->_mail->getFrom();
        }
        $validatedFrom = $this->bdMails_validateFromEmail($from);
        $this->_headers['From'] = array($validatedFrom, 'append' => true);

        if (isset($this->_headers['Return-Path'])) {
            if (count($this->_headers['Return-Path']) == 1
                && isset($this->_headers['Return-Path'][0])
            ) {
                $this->_headers['Return-Path'][0] = $this->bdMails_validateFromEmail($this->_headers['Return-Path'][0]);
            } else {
                unset($this->_headers['Return-Path']);
            }
        }
    }

    public function bdMails_validateFromEmail($fromEmail)
    {
        return $this->_bdMails_validateFromEmailWithDomain($fromEmail, $this->_domain);
    }

    protected function _bdMails_sendMail()
    {
        $messageData = sprintf("%s\n%s\n", $this->header, $this->body);

        $sent = bdMails_Helper_AmazonSes::sendRawEmail(
            $this->_region,
            $this->_accessKey,
            $this->_privateKey,
            base64_encode($messageData)
        );

        return array(
            $messageData,
            $sent['body'],
            $sent['status'] == 200,
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
                $subscriptions = self::_getSubscriptions();
                $subscriptions['amazonses'][$notification['message']] = true;
                self::_setSubscriptions($subscriptions);
                break;
            case 'Bounce':
                if (!bdMails_Option::get('bounce')) {
                    return false;
                }

                $bounceType = '';
                switch ($notification['bounce']['bounceType']) {
                    case 'Permanent':
                        $bounceType = 'hard';
                        break;
                    case 'Transient':
                        $bounceType = 'soft';
                        break;
                }
                if (empty($bounceType)) {
                    return false;
                }

                foreach ($notification['bounce']['bouncedRecipients'] as $recipient) {
                    if (empty($recipient['status']) && empty($recipient['diagnosticCode'])) {
                        // ignore bounce notification without both status AND diagnostic code
                        // in practice, we found those to be incorrect
                        XenForo_Error::logException(new Exception(json_encode($recipient), false, 'Amazon SES: '));
                        continue;
                    }

                    $userId = XenForo_Application::getDb()->fetchOne(
                        'SELECT user_id FROM xf_user WHERE email = ?',
                        $recipient['emailAddress']
                    );
                    if (empty($userId)) {
                        continue;
                    }

                    $bounceDate = strtotime($notification['bounce']['timestamp']);

                    /** @var bdMails_Model_EmailBounce $emailBounceModel */
                    $emailBounceModel = XenForo_Model::create('bdMails_Model_EmailBounce');
                    $emailBounceModel->takeBounceAction(
                        $userId,
                        $bounceType,
                        $bounceDate,
                        array_merge($notification['bounce'], array(
                            'email' => $recipient['emailAddress'],
                            'reason' => isset($recipient['diagnosticCode']) ? $recipient['diagnosticCode'] : 'N/A',
                            'reason_code' => isset($recipient['status']) ? $recipient['status'] : 'N/A',
                        ))
                    );
                }
                break;
            case 'Complaint':
                if (!bdMails_Option::get('bounce')) {
                    return false;
                }

                foreach ($notification['complaint']['complainedRecipients'] as $recipient) {
                    $userId = XenForo_Application::getDb()->fetchOne(
                        'SELECT user_id FROM xf_user WHERE email = ?',
                        $recipient['emailAddress']
                    );
                    if (empty($userId)) {
                        continue;
                    }

                    $bounceType = 'soft';
                    $bounceDate = strtotime($notification['complaint']['timestamp']);

                    /** @var bdMails_Model_EmailBounce $emailBounceModel */
                    $emailBounceModel = XenForo_Model::create('bdMails_Model_EmailBounce');
                    $emailBounceModel->takeBounceAction(
                        $userId,
                        $bounceType,
                        $bounceDate,
                        array_merge($notification['complaint'], array(
                            'email' => $recipient['emailAddress'],
                            'reason' => 'complaint',
                        ))
                    );
                }
                break;
        }

        return true;
    }
}
