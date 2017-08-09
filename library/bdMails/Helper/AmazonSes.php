<?php

class bdMails_Helper_AmazonSes
{
    public static $apiUrl = 'https://email.%s.amazonaws.com';

    public static function sendRawEmail($region, $accessKey, $privateKey, $base64MessageData)
    {
        return self::_makeRequest($region, $accessKey, $privateKey, 'SendRawEmail', array(
            'RawMessage.Data' => $base64MessageData,
        ));
    }

    public static function getSendQuota($region, $accessKey, $privateKey)
    {
        $quota = array();
        $response = self::_makeRequest($region, $accessKey, $privateKey, 'GetSendQuota');

        if (empty($response['status'])
            || $response['status'] != 200
            || empty($response['body'])
        ) {
            return $quota;
        }

        /** @var SimpleXMLElement $xml */
        $xml = Zend_Xml_Security::scan($response['body']);
        /** @noinspection PhpUndefinedFieldInspection */
        if (!$xml
            || $xml->getName() !== 'GetSendQuotaResponse'
            || !$xml->GetSendQuotaResult
        ) {
            return $quota;
        }

        /** @noinspection PhpUndefinedFieldInspection */
        $result = $xml->GetSendQuotaResult;
        foreach (array(
                     'Max24HourSend',
                     'SentLast24Hours',
                     'MaxSendRate',
                 ) as $key) {
            if (!empty($result->$key)) {
                $quota[$key] = doubleval($result->$key);
            }
        }

        return $quota;
    }

    public static function getSendStatistics($region, $accessKey, $privateKey)
    {
        $statistics = array();
        $response = self::_makeRequest($region, $accessKey, $privateKey, 'GetSendStatistics');

        if (empty($response['status'])
            || $response['status'] != 200
            || empty($response['body'])
        ) {
            return $statistics;
        }

        /** @var SimpleXMLElement $xml */
        $xml = Zend_Xml_Security::scan($response['body']);
        /** @noinspection PhpUndefinedFieldInspection */
        if (!$xml
            || $xml->getName() !== 'GetSendStatisticsResponse'
            || !$xml->GetSendStatisticsResult
            || !$xml->GetSendStatisticsResult->SendDataPoints
        ) {
            return $statistics;
        }

        /** @noinspection PhpUndefinedFieldInspection */
        $dataPointContainers = $xml->GetSendStatisticsResult->SendDataPoints;
        $dataPoints = XenForo_Helper_DevelopmentXml::fixPhpBug50670($dataPointContainers->member);

        $complaints = array();
        $rejects = array();
        $bounces = array();
        $deliveryAttempts = array();
        foreach ($dataPoints as $dataPoint) {
            $timestamp = strtotime($dataPoint->Timestamp);
            $complaints[$timestamp] = $dataPoint->Complaints;
            $rejects[$timestamp] = $dataPoint->Rejects;
            $bounces[$timestamp] = $dataPoint->Bounces;
            $deliveryAttempts[$timestamp] = $dataPoint->DeliveryAttempts;
        }

        $cutoffs = array(
            'last_hour' => XenForo_Application::$time - 3600,
            'last_day' => XenForo_Application::$time - 86400,
            'last_week' => XenForo_Application::$time - 7 * 86400,
        );
        foreach ($cutoffs as $cutoffName => $cutoff) {
            $cutoffStatistics = array();
            foreach (array(
                         'complaints',
                         'rejects',
                         'bounces',
                         'deliveryAttempts',
                     ) as $key) {
                $cutoffStatistics[$key] = 0;
                foreach ($$key as $timestamp => $value) {
                    if ($timestamp > $cutoff) {
                        $cutoffStatistics[$key] += $value;
                    }
                }
            }

            $statistics[$cutoffName] = $cutoffStatistics;
        }

        return $statistics;
    }

    protected static function _makeRequest($region, $accessKey, $privateKey, $action, array $params = array())
    {
        $uri = sprintf(self::$apiUrl, $region);
        $client = XenForo_Helper_Http::getClient($uri);

        $date = gmdate('D, d M Y H:i:s O');
        $client->setHeaders('Date', $date);
        $client->setHeaders('X-Amzn-Authorization', self::_getAuthorizationHeader($accessKey, $privateKey, $date));
        $client->setEncType(Zend_Http_Client::ENC_URLENCODED);

        $parameterPost = $params;
        $parameterPost['Action'] = $action;
        $client->setParameterPost($parameterPost);

        $response = $client->request('POST');
        $responseStatus = $response->getStatus();
        $responseBody = $response->getBody();

        if (XenForo_Application::debugMode()) {
            XenForo_Helper_File::log(__METHOD__, sprintf(
                "POST %s (action=%s; %s)\n\t->%d %s",
                $uri,
                $action,
                implode(', ', array_keys($params)),
                $responseStatus,
                $responseBody
            ));
        }

        return array(
            'status' => $responseStatus,
            'body' => $responseBody,
        );
    }

    protected static function _getAuthorizationHeader($accessKey, $privateKey, $date)
    {
        return sprintf(
            'AWS3-HTTPS AWSAccessKeyId=%s,Algorithm=HmacSHA256,Signature=%s',
            $accessKey,
            base64_encode(hash_hmac('sha256', $date, $privateKey, true))
        );
    }
}
