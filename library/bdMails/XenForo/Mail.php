<?php

class bdMails_XenForo_Mail extends XFCP_bdMails_XenForo_Mail
{
	protected static $_bdMails_transportSetup = false;

	public function getPreparedMailHandler($toEmail, $toName = '', array $headers = array(), $fromEmail = '', $fromName = '', $returnPath = '')
	{
		if ($fromEmail)
		{
			$fromEmail = $this->_bdMails_validateFromEmail($fromEmail);
		}
		else
		{
			$fromEmail = $this->_bdMails_validateFromEmail(XenForo_Application::getOptions()->defaultEmailAddress);
		}

		return parent::getPreparedMailHandler($toEmail, $toName, $headers, $fromEmail, $fromName, $returnPath);
	}

	public function sendMail(Zend_Mail $mailObj)
	{
		if (!self::$_bdMails_transportSetup)
		{
			self::bdMails_setupTransport();
		}

		return parent::sendMail($mailObj);
	}

	protected function _bdMails_validateFromEmail($fromEmail)
	{
		$transport = self::bdMails_setupTransport();

		if (!empty($transport) AND is_callable(array(
			$transport,
			'bdMails_validateFromEmail'
		)))
		{
			$fromEmail = $transport->bdMails_validateFromEmail($fromEmail);
		}

		return $fromEmail;
	}

	public static function bdMails_setupTransport()
	{
		$transport = null;
		$providerName = bdMails_Option::get('provider', 'name');

		if (!empty($providerName))
		{
			$providerConfig = bdMails_Option::get('provider', $providerName);

			try
			{
				$transport = self::getTransportForProvider($providerName, $providerConfig);
			}
			catch (XenForo_Exception $e)
			{
				XenForo_Error::logException($e, false, '[bd] Mails');
			}
		}

		XenForo_Mail::setupTransport($transport);

		self::$_bdMails_transportSetup = true;

		return $transport;
	}

	public static function getTransportForProvider($name, $config)
	{
		$transport = null;

		switch ($name)
		{
			case 'mailgun':
				if (empty($config['api_key']))
				{
					throw new XenForo_Exception(new XenForo_Phrase('bdmails_mailgun_requires_api_key'), true);
				}

				if (empty($config['domain']))
				{
					throw new XenForo_Exception(new XenForo_Phrase('bdmails_mailgun_requires_domain'), true);
				}

				$transport = new bdMails_Transport_Mailgun($config['api_key'], $config['domain']);
				break;
		}

		return $transport;
	}

}
