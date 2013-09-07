<?php

class bdMails_Transport_Mandrill extends bdMails_Transport_Abstract
{
	public static $apiUrl = 'https://mandrillapp.com/api/1.0';

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

	protected function _sendMail()
	{
		$message = array(
			'to' => array(),
			'headers' => array()
		);
		$client = XenForo_Helper_Http::getClient(sprintf('%s/messages/send.json', self::$apiUrl));

		$headers = $this->_bdMails_parseHeaderAsKeyValue($this->header);
		foreach ($headers as $headerKey => $headerValue)
		{
			$skip = false;

			switch ($headerKey)
			{
				case 'From':
					list($messageFromEmail, $messageFromName) = $this->_bdMails_parseFormattedAddress($headerValue);
					if (!empty($messageFromEmail))
					{
						$message['from_email'] = $messageFromEmail;
						if (!empty($messageFromName))
						{
							$message['from_name'] = $messageFromName;
						}
					}
					$skip = true;
					break;
				case 'Content-Type':
				// no need because Mandrill handle the MIME stuff
				case 'Date':
				// TODO: skip for now because Mandrill sent_at feature costs extra
				case 'MIME-Version':
				// no need because Mandrill handle the MIME stuff
				case 'Return-Path':
				// no need because Mandrill does bounce management
				case 'Subject':
				case 'To':
					// handled below
					$skip = true;
					break;
			}

			if (!$skip)
			{
				$message['headers'][$headerKey] = $headerValue;
			}
		}

		if (empty($message['from_email']))
		{
			$message['from_email'] = $this->_mail->getFrom();
		}

		$recipients = explode(',', $this->recipients);
		foreach ($recipients as $recipient)
		{
			list($recipientEmail, $recipientName) = $this->_bdMails_parseFormattedAddress($recipient);

			if (!empty($recipientEmail))
			{
				$messageTo = array();
				$messageTo['email'] = $recipientEmail;
				if (!empty($recipientName))
				{
					$messageTo['name'] = $recipientName;
				}

				$message['to'][] = $messageTo;
			}
		}

		$message['subject'] = $this->_mail->getSubject();

		$bodyTextMime = $this->_mail->getBodyText();
		$bodyTextMime->encoding = '';
		$message['text'] = $bodyTextMime->getContent();

		$bodyHtmlMime = $this->_mail->getBodyHtml();
		$bodyHtmlMime->encoding = '';
		$message['html'] = $bodyHtmlMime->getContent();

		$client->setRawData(json_encode(array(
			'key' => $this->_apiKey,
			'message' => $message,
			'async' => true,
		)));

		$response = $client->request('POST');

		$this->_bdMails_log($message, $response);
	}

}
