<?php

require('bootstrap.php');

$mailgun = bdMails_Helper_Transport::setupTransport();
if ($mailgun instanceof bdMails_Transport_Mailgun) {
    $mailgun->bdMails_doWebhook();
}
