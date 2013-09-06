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
	
	public static function get($optionName, $subOption = null)
	{
		$options = XenForo_Application::getOptions();
		
		return $options->get('bdMails_' . $optionName, $subOption);
	}

}
