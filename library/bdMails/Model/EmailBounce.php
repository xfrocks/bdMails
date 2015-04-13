<?php

class bdMails_Model_EmailBounce extends XenForo_Model
{
    public function takeBounceAction($userId, $bounceType, $bounceDate, array $bounceInfo = array())
    {
        $action = '';
        $bounceInfo = array_merge(array(
            'email' => '',
            'reason' => '',
            'reason_code' => '',
        ), $bounceInfo);

        if (XenForo_Application::$versionId >= 1040000) {
            $action = $this->_getXenForoModel()->takeBounceAction($userId, $bounceType, $bounceDate);

            $this->_getDb()->insert('xf_email_bounce_log', array(
                'log_date' => XenForo_Application::$time,
                'email_date' => $bounceDate,
                'message_type' => $bounceType,
                'action_taken' => $action,
                'user_id' => $userId,
                'recipient' => $bounceInfo['email'],
                'raw_message' => json_encode($bounceInfo),
                'status_code' => $bounceInfo['reason_code'],
                'diagnostic_info' => serialize($bounceInfo),
            ));
        } elseif ($bounceType == 'hard') {
            $userDw = XenForo_DataWriter::create('XenForo_DataWriter_User');
            $userDw->setOption(XenForo_DataWriter_User::OPTION_ADMIN_EDIT, true);
            $userDw->setExistingData($userId);

            if (XenForo_Application::$versionId > 1030000) {
                // XenForo 1.3.0 starts dealing with bounced email
                $userDw->set('user_state', 'email_bounce');
            } else {
                $userDw->set('user_state', 'email_confirm_edit');
                $userDw->set('email', '');
            }

            $userDw->set('bdmails_bounced', serialize($bounceInfo));
            $userDw->save();

            XenForo_Helper_File::log('bdmails_bounce', call_user_func_array('sprintf', array(
                'user %d: user_state %s; email %s; %s',
                $userId,
                $userDw->getExisting('user_state'),
                var_export($bounceInfo, true),
            )));

            $action = 'hard';
        }

        return $action;
    }

    /**
     * @return XenForo_Model_EmailBounce
     */
    protected function _getXenForoModel()
    {
        return $this->getModelFromCache('XenForo_Model_EmailBounce');
    }
}