<?php

class bdMails_Transport_Mailgun extends bdMails_Transport_Abstract
{
	public static $apiUrl = 'https://api.mailgun.net/v2';

	protected $_apiKey;
	protected $_domain;

	public function __construct($apiKey, $domain, $options = array())
	{
		parent::__construct($options);

		$this->_apiKey = $apiKey;
		$this->_domain = $domain;
	}

	public function bdMails_validateFromEmail($fromEmail)
	{
		return $this->_bdMails_validateFromEmailWithDomain($fromEmail, $this->_domain);
	}

	protected function _bdMails_sendMail()
	{
		$request = array();
		$client = XenForo_Helper_Http::getClient(sprintf('%s/%s/messages', self::$apiUrl, $this->_domain));

		$client->setAuth('api', $this->_apiKey);

		$headers = $this->_bdMails_parseHeaderAsKeyValue($this->header);
		foreach ($headers as $headerKey => $headerValue)
		{
			$skip = false;

			switch ($headerKey)
			{
				case 'From':
					$request['from'] = $headerValue;
					$skip = true;
					break;
				case 'Content-Type':
				case 'MIME-Version':
				// no need because Mailgun handle the MIME stuff
				case 'Return-Path':
				// no need because Mailgun does bounce management
				case 'Subject':
				case 'To':
					// handled below
					$skip = true;
					break;
			}

			if (!$skip)
			{
				$request[sprintf('h:%s', $headerKey)] = $headerValue;
			}
		}

		if (empty($request['from']))
		{
			$request['from'] = $this->_mail->getFrom();
		}

		$request['to'] = $this->recipients;
		$request['subject'] = $this->_mail->getSubject();

		$bodyTextMime = $this->_mail->getBodyText();
		if (!empty($bodyTextMime))
		{
			$bodyTextMime->encoding = '';
			$request['text'] = $bodyTextMime->getContent();
		}

		$bodyHtmlMime = $this->_mail->getBodyHtml();
		if (!empty($bodyHtmlMime))
		{
			$bodyHtmlMime->encoding = '';
			$request['html'] = $bodyHtmlMime->getContent();
		}

		// `Sender` header validation
		if (!empty($request['h:Sender']))
		{
			// Mailgun does forward `Sender` header but doing so is risky
			// because according to my test, receiver server may refuse
			// if the `From` domain blacklists foreign servers.
			//
			// To make sure the email gets delivered, we will move the
			// `From` address to `Reply-To` header, and use the address
			// in `Sender` for that. Elimating `Sender` header altogether.
			$request['h:Reply-To'] = $request['from'];
			$request['from'] = $request['h:Sender'];
			unset($request['h:Sender']);
		}

		// `From` address validation
		$request['from'] = $this->bdMails_validateFromEmail($request['from']);

		foreach ($request as $key => $param)
		{
			$client->setParameterPost($key, $param);
		}

		$response = $client->request('POST');

		return array(
			$request,
			$response
		);
	}

}
