<?php

class bdMails_Model_SpamPrevention extends XenForo_Model_SpamPrevention
{
    public function checkSfsResult(array $user, Zend_Controller_Request_Http $request)
    {
        $this->_resultDetails = array();

        $decision = $this->_checkSfsResult($user, $request);
        $this->_lastResult = $decision;

        return $decision;
    }

    public function isEmailBlacklistedInLastCheck()
    {
        foreach ($this->_resultDetails as $record) {
            if (!isset($record['data'])) {
                continue;
            }
            $data = $record['data'];

            if (!isset($data['matches'])) {
                continue;
            }
            $matches = $data['matches'];

            $parts = explode(',', $matches);
            foreach ($parts as $part) {
                $part = trim($part);
                if ($part === 'email: blacklisted') {
                    return true;
                }
            }
        }

        return false;
    }
}
