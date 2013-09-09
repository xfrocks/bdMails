<?php

class bdMails_Transport_SendGrid extends bdMails_Transport_Abstract
{
	public static $apiUrl = 'https://sendgrid.com/api';

	protected $_username;
	protected $_password;
	protected $_domain;

	public function __construct($username, $password, $domain, $options = array())
	{
		parent::__construct($options);

		$this->_username = $username;
		$this->_password = $password;
		$this->_domain = $domain;
	}

	public function bdMails_validateFromEmail($fromEmail)
	{
		return $this->_bdMails_validateFromEmailWithDomain($fromEmail, $this->_domain);
	}

	protected function _bdMails_sendMail()
	{
		$request = array('headers' => array(), );
		$client = XenForo_Helper_Http::getClient(sprintf('%s/mail.send.json', self::$apiUrl));

		$client->setParameterPost('api_user', $this->_username);
		$client->setParameterPost('api_key', $this->_password);

		$headers = $this->_bdMails_parseHeaderAsKeyValue($this->header);
		foreach ($headers as $headerKey => $headerValue)
		{
			$skip = false;

			switch ($headerKey)
			{
				case 'From':
					list($requestFromEmail, $requestFromName) = $this->_bdMails_parseFormattedAddress($headerValue);
					if (!empty($requestFromEmail))
					{
						$request['from'] = $requestFromEmail;
						if (!empty($requestFromName))
						{
							$request['fromname'] = $requestFromName;
						}
					}
					$skip = true;
					break;
				case 'Reply-To':
					$request['replyto'] = $headerValue;
					$skip = true;
					break;
				case 'Content-Type':
				case 'MIME-Version':
				// no need because SendGrid handle the MIME stuff
				case 'Return-Path':
				// no need because SendGrid does bounce management
				case 'Subject':
				case 'To':
					// handled below
					$skip = true;
					break;
			}

			if (!$skip)
			{
				$request['headers'][$headerKey] = $headerValue;
			}
		}

		if (empty($request['from']))
		{
			$request['from'] = $this->_mail->getFrom();
		}

		$recipients = explode(',', $this->recipients);
		$requestToCount = 0;
		foreach ($recipients as $recipient)
		{
			list($recipientEmail, $recipientName) = $this->_bdMails_parseFormattedAddress($recipient);

			if (!empty($recipientEmail))
			{
				$request[sprintf('to[%d]', $requestToCount)] = $recipientEmail;

				if (!empty($recipientName))
				{
					$request[sprintf('toname[%d]', $requestToCount)] = $recipientName;
				}
				else
				{
					$request[sprintf('toname[%d]', $requestToCount)] = $recipientEmail;
				}

				$requestToCount++;
			}
		}

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
		if (!empty($request['headers']['Sender']))
		{
			// I didn't test this, just copied from Mandrill
			$request['replyto'] = $request['from'];
			$request['from'] = $request['headers']['Sender'];
			unset($request['headers']['Sender']);
		}

		// `From` address validation
		$request['from'] = $this->bdMails_validateFromEmail($request['from']);

		foreach ($request as $key => $param)
		{
			if ($key === 'headers')
			{
				$client->setParameterPost($key, json_encode($param));
			}
			else
			{
				$client->setParameterPost($key, $param);
			}
		}

		$response = $client->request('POST')->getBody();

		$success = false;
		$responseArray = @json_decode($response, true);
		if (!empty($responseArray['message']) AND $responseArray['message'] == 'success')
		{
			$success = true;
		}

		return array(
			$request,
			$response,
			$success,
		);
	}

}
