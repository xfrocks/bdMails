<?php

class bdMails_Helper_Transport
{
    public static function setupTransport()
    {
        $transport = null;
        $providerName = bdMails_Option::get('provider', 'name');

        if (!empty($providerName)) {
            $providerConfig = bdMails_Option::get('provider', $providerName);

            try {
                $transport = bdMails_Helper_Transport::getTransportForProvider($providerName, $providerConfig);
            } catch (XenForo_Exception $e) {
                XenForo_Error::logException($e, false, '[bd] Mails: ');
            }
        }

        if (!empty($transport)) {
            XenForo_Mail::setupTransport($transport);
        }

        return $transport;
    }

    public static function getTransportForProvider($name, $config, $options = array())
    {
        $transport = null;

        switch ($name) {
            case 'amazonses':
                if (empty($config['region'])) {
                    throw new XenForo_Exception(new XenForo_Phrase('bdmails_amazonses_requires_region'), true);
                }

                if (empty($config['access_key'])) {
                    throw new XenForo_Exception(new XenForo_Phrase('bdmails_amazonses_requires_access_key'), true);
                }

                if (empty($config['private_key'])) {
                    throw new XenForo_Exception(new XenForo_Phrase('bdmails_amazonses_requires_private_key'), true);
                }

                if (empty($config['domain'])) {
                    throw new XenForo_Exception(new XenForo_Phrase('bdmails_amazonses_requires_domain'), true);
                }

                if (!empty($config['sendmail'])) {
                    return new Zend_Mail_Transport_Sendmail();
                }

                $transport = new bdMails_Transport_AmazonSes(
                    $config['region'],
                    $config['access_key'],
                    $config['private_key'],
                    $config['domain'],
                    $options
                );
                break;
            case 'mailgun':
                if (empty($config['api_key'])) {
                    throw new XenForo_Exception(new XenForo_Phrase('bdmails_mailgun_requires_api_key'), true);
                }

                if (empty($config['domain'])) {
                    throw new XenForo_Exception(new XenForo_Phrase('bdmails_mailgun_requires_domain'), true);
                }

                $transport = new bdMails_Transport_Mailgun($config['api_key'], $config['domain'], $options);
                break;
            case 'mandrill':
                if (empty($config['api_key'])) {
                    throw new XenForo_Exception(new XenForo_Phrase('bdmails_mandrill_requires_api_key'), true);
                }

                if (empty($config['domain'])) {
                    throw new XenForo_Exception(new XenForo_Phrase('bdmails_mandrill_requires_domain'), true);
                }

                $transport = new bdMails_Transport_Mandrill($config['api_key'], $config['domain'], $options);
                break;
            case 'sendgrid':
                if (empty($config['username']) OR empty($config['password'])) {
                    throw new XenForo_Exception(new XenForo_Phrase('bdmails_sendgrid_requires_username_and_password'), true);
                }

                if (empty($config['domain'])) {
                    $domain = 'sendgrid.me';
                } else {
                    $domain = $config['domain'];
                }

                $transport = new bdMails_Transport_SendGrid($config['username'], $config['password'], $domain, $options);
                break;
        }

        return $transport;
    }

}
