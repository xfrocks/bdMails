<?php

class bdMails_Option
{
    const UPDATER_URL = 'https://xfrocks.com/api/index.php?updater';

    public static function renderProviders(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
    {
        $optionValue = $preparedOption['option_value'];
        $amazonSesSubscriptionsRequired = false;
        $amazonSesBounceSubscribed = false;
        $amazonSesComplaintSubscribed = false;

        if (!empty($optionValue['name']) && $optionValue['name'] == 'amazonses') {
            if (!empty($optionValue['amazonses']['domain'])) {
                $amazonSesSubscriptionsRequired = true;

                /** @var XenForo_Model_DataRegistry $dataRegistryModel */
                $dataRegistryModel = XenForo_Model::create('XenForo_Model_DataRegistry');
                $subscriptions = $dataRegistryModel->get(bdMails_Transport_AmazonSes::DATA_REGISTRY_SUBSCRIPTIONS);

                if (!empty($subscriptions)) {
                    foreach ($subscriptions as $subscriptionMessage => $received) {
                        if (strpos($subscriptionMessage, $optionValue['amazonses']['domain']) === false) {
                            continue;
                        }

                        if (strpos($subscriptionMessage, 'Bounce') !== false) {
                            $amazonSesBounceSubscribed = true;
                        }

                        if (strpos($subscriptionMessage, 'Complaint') !== false) {
                            $amazonSesComplaintSubscribed = true;
                        }
                    }
                }
            }
        }

        if (!bdMails_Option::get('bounce')) {
            $amazonSesSubscriptionsRequired = false;
        }

        $editLink = $view->createTemplateObject('option_list_option_editlink', array(
            'preparedOption' => $preparedOption,
            'canEditOptionDefinition' => $canEdit
        ));

        return $view->createTemplateObject('bdmails_option_providers', array(
            'fieldPrefix' => $fieldPrefix,
            'listedFieldName' => $fieldPrefix . '_listed[]',
            'preparedOption' => $preparedOption,
            'value' => $optionValue,
            'formatParams' => $preparedOption['formatParams'],
            'editLink' => $editLink,

            'amazonSesSubscriptionsRequired' => $amazonSesSubscriptionsRequired,
            'amazonSesBounceSubscribed' => $amazonSesBounceSubscribed,
            'amazonSesComplaintSubscribed' => $amazonSesComplaintSubscribed,
        ));
    }

    public static function verifyProvider(array &$options, XenForo_DataWriter $dw, $fieldName)
    {
        if (empty($options['name'])) {
            $options = array();
        } else {
            $name = $options['name'];
            $config = array();
            if (!empty($options[$name])) {
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
