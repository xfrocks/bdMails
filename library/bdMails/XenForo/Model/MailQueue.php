<?php

class bdMails_XenForo_Model_MailQueue extends XFCP_bdMails_XenForo_Model_MailQueue
{
	public function runMailQueue($targetRunTime)
	{
		XenForo_Mail::create('fake_email_title', array());
		bdMails_XenForo_Mail::bdMails_setupTransport();

		return parent::runMailQueue($targetRunTime);
	}

}
