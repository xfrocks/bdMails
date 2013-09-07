<?php

abstract class bdMails_Transport_Abstract extends Zend_Mail_Transport_Abstract
{
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

	protected function _bdMails_log($request, $response)
	{
		if ($response instanceof Zend_Http_Response)
		{
			$response = $response->getBody();
		}

		file_put_contents(sprintf('%s/bdmails_%d_%s.log', XenForo_Helper_File::getInternalDataPath(), XenForo_Application::$time, md5(serialize($request))), var_export(array(
			$request,
			$response
		), true));
	}

	protected function _bdMails_parseHeaderAsKeyValue($header)
	{
		$lines = explode($this->EOL, $header);
		$parsed = array();

		while (count($lines) > 0)
		{
			$line = array_shift($lines);
			$parts = explode(': ', $line);
			if (count($parts) == 2)
			{
				$key = $parts[0];
				$value = $parts[1];

				$lastCharOfValue = substr($value, -1);

				while ($lastCharOfValue === ',')
				{
					if (count($lines) == 0)
					{
						$value = substr($value, 0, -1);
					}
					else
					{
						$nextLine = array_shift($lines);
						$value .= $this->EOL . ' ' . $nextLine;
					}
				}

				$parsed[$key] = $value;
			}
		}

		return $parsed;
	}

	protected function _bdMails_parseFormattedAddress($address)
	{
		$address = trim($address);
		$name = '';

		if (preg_match('/^"(.+)" <(.+)>$/', $address, $matches) OR preg_match('/^(.+) <(.+)>$/', $address, $matches))
		{
			$name = $matches[1];
			$email = $matches[2];
		}
		else
		{
			$email = $address;
		}

		return array(
			$email,
			$name
		);
	}

	protected function _bdMails_validateFromEmailWithDomain($fromEmail, $domain)
	{
		if (empty($fromEmail) OR !preg_match(sprintf('/^[^@]+@%s$/i', preg_quote($domain, '/')), $fromEmail))
		{
			return $this->bdMails_getDefaultFromEmail($domain);
		}

		return $fromEmail;
	}

}
