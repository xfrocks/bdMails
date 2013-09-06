<?php

class bdMails_Transport_Mailgun extends Zend_Mail_Transport_Abstract
{
	public static $apiUrl = 'https://api.mailgun.net/v2';
	public static $defaultOptions = array('from_email_template' => 'xenforo@%s');

	protected $_apiKey;
	protected $_domain;
	protected $_options;

	public function __construct($apiKey, $domain, $options = array())
	{
		$this->_apiKey = $apiKey;
		$this->_domain = $domain;

		$this->_options = array_merge(self::$defaultOptions, $options);
	}

	public function bdMails_validateFromEmail($fromEmail)
	{
		if (empty($fromEmail) OR !preg_match(sprintf('/^[^@]+@%s$/i', preg_quote($this->_domain, '/')), $fromEmail))
		{
			return sprintf($this->_options['from_email_template'], $this->_domain);
		}

		return $fromEmail;
	}

	protected function _sendMail()
	{
		$request = array();
		$client = XenForo_Helper_Http::getClient(sprintf('%s/%s/messages', self::$apiUrl, $this->_domain));

		$client->setAuth('api', $this->_apiKey);
		$mailHeaders = $this->_mail->getHeaders();

		if (!empty($mailHeaders['From']))
		{
			$this->_prepareHeaders(array('From' => $mailHeaders['From']));
			$parts = explode(': ', $this->header);
			if (count($parts) == 2)
			{
				$request['from'] = trim($parts[1]);
			}
		}
		if (empty($request['from']))
		{
			$request['from'] = $this->_mail->getFrom();
		}

		$request['to'] = $this->recipients;
		$request['subject'] = $this->_mail->getSubject();

		$bodyTextMime = $this->_mail->getBodyText();
		$bodyTextMime->encoding = '';
		$request['text'] = $bodyTextMime->getContent();

		$bodyHtmlMime = $this->_mail->getBodyHtml();
		$bodyHtmlMime->encoding = '';
		$request['html'] = $bodyHtmlMime->getContent();

		foreach ($request as $key => $param)
		{
			$client->setParameterPost($key, $param);
		}

		$response = $client->request('POST');
	}

	protected function _sendMailMime()
	{
		$request = array();
		$client = XenForo_Helper_Http::getClient(sprintf('%s/%s/messages.mime', self::$apiUrl, $this->_domain));

		$client->setAuth('api', $this->_apiKey);

		$request['to'] = $this->recipients;
		$client->setParameterPost('to', $request['to']);

		$request['message'] = $this->body;
		$client->setFileUpload('message.mime', 'message', $request['message']);

		$headers = explode($this->EOL, $this->header);
		foreach ($headers as $header)
		{
			$parts = explode(':', $header);
			if (count($parts) == 2)
			{
				$paramName = sprintf('h:%s', trim($parts[0]));
				$paramValue = trim($parts[1]);
				$skip = false;

				switch (strtolower($paramName))
				{
					case 'h:to':
					case 'h:return-path':
						// avoid "5.7.1 not RFC 2822 compliant" error
						$skip = true;
						break;
				}

				if (!$skip)
				{
					$request[$paramName] = $paramValue;
					$client->setParameterPost($paramName, $paramValue);
				}
			}
		}

		$response = $client->request('POST');

		file_put_contents(XenForo_Helper_File::getInternalDataPath() . '/mailgun/' . XenForo_Application::$time, var_export(array(
			$request,
			$response
		), true));
	}

}
