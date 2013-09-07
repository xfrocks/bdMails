<?php

class bdMails_Option
{
	public static function renderProviders(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
	{
		$editLink = $view->createTemplateObject('option_list_option_editlink', array(
			'preparedOption' => $preparedOption,
			'canEditOptionDefinition' => $canEdit
		));

		return $view->createTemplateObject('bdmails_option_providers', array(
			'fieldPrefix' => $fieldPrefix,
			'listedFieldName' => $fieldPrefix . '_listed[]',
			'preparedOption' => $preparedOption,
			'value' => $preparedOption['option_value'],
			'formatParams' => $preparedOption['formatParams'],
			'editLink' => $editLink,
		));
	}

	public static function verifyProvider(array &$options, XenForo_DataWriter $dw, $fieldName)
	{
		if (empty($options['name']))
		{
			$options = array();
		}
		else
		{
			$name = $options['name'];
			$config = array();
			if (!empty($options[$name]))
			{
				$config = $options[$name];
			}

			$transport = bdMails_Helper_Transport::getTransportForProvider($name, $config);
		}

		return true;
	}

	public static function debugMode()
	{
		$configValue = XenForo_Application::getConfig()->get('bdMails_debug');

		return !empty($configValue);
	}

	public static function get($optionName, $subOption = null)
	{
		$options = XenForo_Application::getOptions();

		return $options->get('bdMails_' . $optionName, $subOption);
	}

}
