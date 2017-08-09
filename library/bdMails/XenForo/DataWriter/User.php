<?php

class bdMails_XenForo_DataWriter_User extends XFCP_bdMails_XenForo_DataWriter_User
{
    protected function _getFields()
    {
        $fields = parent::_getFields();

        $fields['xf_user_option']['bdmails_bounced'] = array(
            'type' => XenForo_DataWriter::TYPE_STRING,
            'default' => ''
        );

        return $fields;
    }

    protected function _preSave()
    {
        if ($this->isChanged('email') AND !!$this->get('bdmails_bounced')) {
            $email = $this->get('email');

            if (!empty($email)) {
                $bounced = $this->get('bdmails_bounced');
                $bouncedArray = unserialize($bounced);

                if (utf8_strtolower($email) === utf8_strtolower($bouncedArray['email'])) {
                    if ($this->getOption(XenForo_DataWriter_User::OPTION_ADMIN_EDIT)) {
                        // a staff member is changing the email, accept it
                    } else {
                        throw new XenForo_Exception(
                            new XenForo_Phrase('bdmails_must_use_different_email_address'),
                            true
                        );
                    }
                }

                $this->set('bdmails_bounced', '');
            }
        }

        parent::_preSave();
    }
}
