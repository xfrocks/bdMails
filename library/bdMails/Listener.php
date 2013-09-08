<?php

class bdMails_Listener
{
	public static function load_class($class, array &$extend)
	{
		static $classes = array(
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

		XenForo_Template_Helper_Core::$helperCallbacks['bdmails_getoption'] = array('bdMails_Option', 'get');
	}
	
	public static function file_health_check(XenForo_ControllerAdmin_Abstract $controller, array &$hashes)
	{
		$hashes += bdMails_FileSums::getHashes();
	}
}
