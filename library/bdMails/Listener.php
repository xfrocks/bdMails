<?php

class bdMails_Listener
{
	public static function load_class($class, array &$extend)
	{
		static $classes = array(
			'XenForo_DataWriter_User',
			'XenForo_Mail',
		);

		if (in_array($class, $classes))
		{
			$extend[] = 'bdMails_' . $class;
		}
	}

	public static function init_dependencies(XenForo_Dependencies_Abstract $dependencies, array $data)
	{
		bdMails_Helper_Transport::setupTransport();

		XenForo_Template_Helper_Core::$helperCallbacks['bdmails_getoption'] = array(
			'bdMails_Option',
			'get'
		);
	}

	public static function visitor_setup(XenForo_Visitor &$visitor)
	{
		$bounced = $visitor->get('bdmails_bounced');
		if (empty($bounced))
		{
			$bouncedArray = array();
		}
		else
		{
			$bouncedArray = @unserialize($bounced);
			if (empty($bouncedArray))
			{
				$bouncedArray = array();
			}
		}

		$visitor['_bdMails_bounced'] = $bouncedArray;
	}

	public static function front_controller_pre_view(XenForo_FrontController $fc, XenForo_ControllerResponse_Abstract &$controllerResponse, XenForo_ViewRenderer_Abstract &$viewRenderer, array &$containerParams)
	{
		$visitor = XenForo_Visitor::getInstance();

		if ($visitor->get('user_state') == 'email_confirm_edit' AND !!$visitor->get('bdmails_bounced') AND !$visitor->get('email'))
		{
			// XenForo 1.3.0 starts supporting a new notice of type 'isEmailBouncing'
			// however, our old logic to display our own notice here is still kept
			// for legacy support (e.g. users who got email bounced in XenForo 1.2.0
			// and the site was updated to XenForo 1.3.0 etc.) Generally, this should not run
			// within XenForo 1.3.0 because our cron uses the new user state (email_bounce)
			// instead of email_confirm_edit

			$dependencies = $viewRenderer->getDependencyHandler();
			$notices = &$dependencies->notices;

			$notices['isAwaitingEmailConfirmation'] = 'bdmails_notice_update_email';
		}
	}

	public static function file_health_check(XenForo_ControllerAdmin_Abstract $controller, array &$hashes)
	{
		$hashes += bdMails_FileSums::getHashes();
	}

}
