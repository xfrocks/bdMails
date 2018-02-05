<?php

class bdMails_XenForo_Model_EmailBounce extends XFCP_bdMails_XenForo_Model_EmailBounce
{
    public function pruneEmailBounceLogs($cutOff = null)
    {
        $bounceLogTtl = bdMails_Option::get('bounceLogTtl');
        if ($bounceLogTtl < 1) {
            return 0;
        }

        $cutOff = XenForo_Application::$time - 86400 * $bounceLogTtl;

        return parent::pruneEmailBounceLogs($cutOff);
    }
}
