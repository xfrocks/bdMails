<?php

abstract class bdMails_Transport_Abstract extends Zend_Mail_Transport_Abstract
{
    const DATA_REGISTRY_SUBSCRIPTIONS = 'bdMails_subscriptions';

    const FEATURE_BOUNCE = 'bounce';

    abstract protected function _bdMails_sendMail();

    protected $_options = array();

    public static $defaultOptions = array('from_email_template' => 'xenforo@%s');

    public function __construct($options = array())
    {
        $this->_options = array_merge(self::$defaultOptions, $options);
    }

    public function bdMails_getDefaultFromEmail($domain)
    {
        $fromEmail = sprintf($this->_options['from_email_template'], $domain);

        return $fromEmail;
    }

    final protected function _sendMail()
    {
        $startTime = microtime(true);

        list($request, $response, $success) = $this->_bdMails_sendMail();

        $endTime = microtime(true);

        $this->_bdMails_log($request, $response, $success, array('microtime' => $endTime - $startTime));
    }

    protected function _bdMails_log($request, $response, $success, $extraData = array())
    {
        if ($success AND !bdMails_Option::debugMode()) {
            return;
        }

        $logPath = sprintf(
            '%s/bdmails_%d_%s.log',
            XenForo_Helper_File::getInternalDataPath(),
            XenForo_Application::$time,
            md5(serialize($request))
        );

        if (!$success) {
            $e = new XenForo_Exception(sprintf('Sending mail failed, log is available at %s', $logPath));
            XenForo_Error::logException($e, false, '[bd] Mails: ');
        }

        file_put_contents($logPath, var_export(array(
            $request,
            $response,
            $success,
            $extraData,
        ), true));
    }

    protected function _bdMails_decodeEncodedHeader($headerValue)
    {
        $pattern = '#^[^=]*=\?[^\?]+\?(?<mo' . 'de>B|Q)\?(?<en' . 'coded>[^\?]+)\?=#';
        $parts = array();
        while (true) {
            if (preg_match($pattern, $headerValue, $matches)) {
                if ($matches['mode'] == 'Q') {
                    // quoted printable
                    $str = $matches['encoded'];
                    $str = str_replace(array(
                        '=3F',
                        '=22',
                        '=28',
                        '=29',
                        '=2C',
                        '=2E',
                        '=3A',
                        '=3B',
                        '=3C',
                        '=3E',
                        '=40',
                        '=5B',
                        '=5C',
                        '=5D',
                        '=20',
                        '=5F'
                    ), array(
                        '?',
                        '"',
                        '(',
                        ')',
                        ',',
                        '.',
                        ':',
                        ';',
                        '<',
                        '>',
                        '@',
                        '[',
                        '\\',
                        ']',
                        ' ',
                        '_'
                    ), $str);
                    $str = str_replace(Zend_Mime::$qpReplaceValues, Zend_Mime::$qpKeys, $str);
                    $str = str_replace('=3D', '=', $str);
                } else {
                    // base64
                    // TODO: support other encoding scheme?
                    $str = $matches['encoded'];
                    $str = base64_decode(str_replace(Zend_Mime::LINEEND, '', $str));
                }

                $parts[] = $str;

                $headerValue = substr($headerValue, strlen($matches[0]));
            } else {
                $parts[] = $headerValue;
                break;
            }
        }

        $headerValue = implode('', $parts);

        return $headerValue;
    }

    protected function _bdMails_getSubject()
    {
        $subject = $this->_mail->getSubject();

        $subject = $this->_bdMails_decodeEncodedHeader($subject);

        return $subject;
    }

    protected function _bdMails_parseHeaderAsKeyValue($header)
    {
        $lines = explode($this->EOL, $header);
        $parsed = array();

        while (count($lines) > 0) {
            $line = array_shift($lines);
            $parts = explode(': ', $line);
            if (count($parts) == 2) {
                $key = $parts[0];
                $value = $parts[1];

                $lastCharOfValue = substr($value, -1);

                while ($lastCharOfValue === ',') {
                    if (count($lines) == 0) {
                        $value = substr($value, 0, -1);
                    } else {
                        $nextLine = array_shift($lines);
                        $value .= $this->EOL . ' ' . $nextLine;
                    }
                }

                $value = $this->_bdMails_decodeEncodedHeader($value);

                $parsed[$key] = $value;
            }
        }

        return $parsed;
    }

    protected function _bdMails_parseFormattedAddress($address)
    {
        $address = trim($address);
        $name = '';

        if (preg_match('/^"(.+)" <(.+)>$/', $address, $matches) OR preg_match('/^(.+) <(.+)>$/', $address, $matches)) {
            $name = $matches[1];
            $email = $matches[2];
        } else {
            $email = $address;
        }

        return array(
            $email,
            $name
        );
    }

    protected function _bdMails_validateFromEmailWithDomain($fromEmail, $domain)
    {
        list($email, $name) = $this->_bdMails_parseFormattedAddress($fromEmail);

        if (empty($email) OR !preg_match(sprintf('/^[^@]+@%s$/i', preg_quote($domain, '/')), $email)) {
            $email = $this->bdMails_getDefaultFromEmail($domain);
        }

        if (empty($name)) {
            return $email;
        } else {
            return sprintf('"%1$s" <%2$s>', $name, $email);
        }
    }

    protected static function _getSubscriptions()
    {
        $subscriptions = self::_getDataRegistryModel()->get(self::DATA_REGISTRY_SUBSCRIPTIONS);
        if (empty($subscriptions)) {
            $subscriptions = array();
        }

        return $subscriptions;
    }

    protected static function _setSubscriptions(array $subscriptions)
    {
        self::_getDataRegistryModel()->set(self::DATA_REGISTRY_SUBSCRIPTIONS, $subscriptions);

        return true;
    }

    /**
     * @return XenForo_Model_DataRegistry
     */
    protected static function _getDataRegistryModel()
    {
        /** @var XenForo_Model_DataRegistry $model */
        static $model = null;

        if ($model === null) {
            $model = XenForo_Model::create('XenForo_Model_DataRegistry');
        }

        return $model;
    }
}
