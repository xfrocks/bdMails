<?php

class bdMails_Helper_Transport
{
	public static function getTransportForProvider($name, $config, $options = array())
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

				$transport = new bdMails_Transport_Mailgun($config['api_key'], $config['domain'], $options);
				break;
			case 'mandrill':
				if (empty($config['api_key']))
				{
					throw new XenForo_Exception(new XenForo_Phrase('bdmails_mandrill_requires_api_key'), true);
				}

				if (empty($config['domain']))
				{
					throw new XenForo_Exception(new XenForo_Phrase('bdmails_mandrill_requires_domain'), true);
				}

				$transport = new bdMails_Transport_Mandrill($config['api_key'], $config['domain'], $options);
				break;
		}

		return $transport;
	}

}
