<?php
/**
 * WHMCS Sellix Pay Payment Gateway Module Return Page
 *
 * @copyright Copyright (c) WHMCS Limited 2023
 * @license http://www.whmcs.com/license/ WHMCS Eula
 */

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../../includes/invoicefunctions.php';
require_once __DIR__ . '/../../../../modules/gateways/sellixpay.php';

$gatewayModuleName = 'sellixpay';

$gatewayParams = getGatewayVariables($gatewayModuleName);

if (!$gatewayParams['type']) {
    die("Module Not Activated");
}

try {
    if (isset($_REQUEST["invoiceid"]) && !empty($_REQUEST["invoiceid"])) {
        $invoiceid = $_REQUEST["invoiceid"];
        $invoiceid = trim($invoiceid);
        $invoiceid = (int)$invoiceid;
        $systemUrl = $gatewayParams['systemurl'];
        $redirectUrl = $systemUrl.'/viewinvoice.php?id='.$invoiceid;
        sellixRedirect($redirectUrl);
    } else {
        throw new \Exception('Empty response received from gateway.');
    }
} catch (\Exception $e) {
    $error_message = $e->getMessage();
    sellixLog($gatewayParams['name'], $error_message, 'Return from gateway');
    $htmlOutput = '<h6 style="color:red">An error occurred while returning from payment gateway: '.$error_message.'</h6>';
    echo $htmlOutput;
    exit;
}
