<?php

class bdMails_CronEntry_Bounce
{
    public static function run()
    {
        if (!bdMails_Option::get('bounce')) {
            return;
        }

        $transport = bdMails_Helper_Transport::setupTransport();

        if (!empty($transport) AND $transport->bdMails_doesSupportFeature(bdMails_Transport_Abstract::FEATURE_BOUNCE)) {
            $bounces = $transport->bdMails_bounceList();

            $emails = array();
            foreach ($bounces as $bounce) {
                $emails[utf8_strtolower($bounce['email'])] = $bounce;
            }

            if (!empty($emails)) {
                /* @var $userModel XenForo_Model_User */
                $userModel = XenForo_Model::create('XenForo_Model_User');
                /** @var bdMails_Model_EmailBounce $emailBounceModel */
                $emailBounceModel = XenForo_Model::create('bdMails_Model_EmailBounce');

                $superAdmins = preg_split('#\s*,\s*#', XenForo_Application::getConfig()->get('superAdmins')
                    , -1, PREG_SPLIT_NO_EMPTY);

                $users = $userModel->getUsers(array(
                    'emails' => array_keys($emails),
                    'user_state' => array(
                        'valid',
                        'email_confirm'
                    ),
                ));

                foreach ($users as $user) {
                    $emailLower = utf8_strtolower($user['email']);
                    if (empty($emails[$emailLower])) {
                        // bounce of this user cannot be found
                        continue;
                    }

                    if (in_array($user['user_id'], $superAdmins)) {
                        // we should not alter super admin user record
                        continue;
                    }

                    $emailBounceModel->takeBounceAction($user['user_id'], 'hard', XenForo_Application::$time, $emails[$emailLower]);
                }
            }
        }
    }

}
