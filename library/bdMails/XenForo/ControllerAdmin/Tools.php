<?php

class bdMails_XenForo_ControllerAdmin_Tools extends XFCP_bdMails_XenForo_ControllerAdmin_Tools
{
    public function actionMandrillWebhooksAdd()
    {
        $mandrill = bdMails_Helper_Transport::setupTransport();
        if ($mandrill instanceof bdMails_Transport_Mandrill) {
            list($existingHardBounce, $existingSoftBounce) = $mandrill->bdMails_getWebhooksList();
            $mandrill->bdMails_postWebhooksAdd(!$existingHardBounce, !$existingSoftBounce);
        }

        return $this->responseRedirect(
            XenForo_ControllerResponse_Redirect::RESOURCE_UPDATED,
            XenForo_Link::buildAdminLink('options/list', array('group_id' => 'bdMails'))
        );
    }
}