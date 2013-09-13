<?php

class bdMails_CronEntry_Bounce
{
	public static function run()
	{
		$transport = bdMails_Helper_Transport::setupTransport();

		if (!empty($transport) AND $transport->bdMails_doesSupportFeature(bdMails_Transport_Abstract::FEATURE_BOUNCE))
		{
			$bounces = $transport->bdMails_bounceList();

			$emails = array();
			foreach ($bounces as $bounce)
			{
				$emails[utf8_strtolower($bounce['email'])] = $bounce;
			}

			if (!empty($emails))
			{
				/* @var $userModel XenForo_Model_User */
				$userModel = XenForo_Model::create('XenForo_Model_User');

				$users = $userModel->getUsers(array(
					'emails' => array_keys($emails),
					'user_state' => array(
						'valid',
						'email_confirm'
					),
				));

				foreach ($users as $user)
				{
					$emailLower = utf8_strtolower($user['email']);
					if (empty($emails[$emailLower]))
					{
						// bounce of this user cannot be found
						continue;
					}

					$bounce = $emails[$emailLower];

					$userDw = XenForo_DataWriter::create('XenForo_DataWriter_User');
					$userDw->setOption(XenForo_DataWriter_User::OPTION_ADMIN_EDIT, true);
					$userDw->setExistingData($user, true);
					$userDw->set('user_state', 'email_confirm_edit');
					$userDw->set('email', '');
					$userDw->set('bdmails_bounced', serialize($bounce));
					$userDw->save();

					XenForo_Helper_File::log('bdmails_bounce', call_user_func_array('sprintf', array(
						'user %d: user_state %s; email %s; reason %s',
						$user['user_id'],
						$user['user_state'],
						$bounce['email'],
						$bounce['reason'],
					)));
				}
			}
		}
	}

}
