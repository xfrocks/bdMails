<?php

class bdMails_XenForo_Mail extends XFCP_bdMails_XenForo_Mail
{
	protected static $_bdMails_transportSetup = false;
	
	public function sendMail(Zend_Mail $mailObj)
	{
		if (!self::$_bdMails_transportSetup)
		{
			self::bdMails_setupTransport();
		}

		return parent::sendMail($mailObj);
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
				$transport = bdMails_Helper_Transport::getTransportForProvider($providerName, $providerConfig);
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

}
