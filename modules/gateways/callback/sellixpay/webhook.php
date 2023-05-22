<?php
/**
 * WHMCS Sellix Pay Payment Gateway Module Webhook Page
 *
 * @copyright Copyright (c) WHMCS Limited 2023
 * @license http://www.whmcs.com/license/ WHMCS Eula
 */

require_once __DIR__ . '/../../../../init.php';
require_once __DIR__ . '/../../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../../includes/invoicefunctions.php';
require_once __DIR__ . '/../../../../modules/gateways/sellixpay.php';

$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);

$gatewayModuleName = 'sellixpay';
$gatewayParams = getGatewayVariables($gatewayModuleName);

try {
    
    sellixLog($gatewayParams['name'], $data, 'Webhook received data');

    if ((null === $data['data']) || (null === $data['data']['uniqid']) || empty($data['data']['uniqid'])) {
        $message = 'Sellixpay: suspected fraud. Code-001';
        throw new \Exception($message);
    }

    $sellix_order = sellixValidSellixOrder($gatewayParams, $data['data']['uniqid']);

    if (isset($_REQUEST["invoiceid"]) && !empty($_REQUEST["invoiceid"])) {
        $invoiceid = $_REQUEST["invoiceid"];
        $invoiceid = trim($invoiceid);
        $invoiceid = (int)$invoiceid;
        
        $transactionId = $sellix_order['uniqid'];

        $gateway_fees = 0;
        if (isset($sellix_order["discount_breakdown"]["gateway_fee"]["total_display"])) {
            $gateway_fees = $sellix_order["discount_breakdown"]["gateway_fee"]["total_display"];
        }

        $paymentAmount = $sellix_order['total_display'];
        $paymentAmount = $paymentAmount - $gateway_fees;
         
        $orderAmount = $gatewayParams['amount'];
        if ($paymentAmount > $orderAmount) {
            $paymentAmount = $orderAmount;
        }
        
        $message1 = 'Invoice #' . $invoiceid;
        $message2 = ' (' . $sellix_order['uniqid'] . '). Status: ' . $sellix_order['status'];
        
        updateSellixpayOrder($invoiceid, 'status', $sellix_order['status']);
        updateSellixpayOrder($invoiceid, 'transaction_id', $transactionId);
        updateSellixpayOrder($invoiceid, 'response', json_encode($sellix_order));
        
        sellixLog($gatewayParams['name'], $message1.$message2, 'Webhook Concern Invoice');
        
        $invoiceId = checkCbInvoiceID($invoiceid, $gatewayParams['name']);
        checkCbTransID($transactionId);

        if ($sellix_order['status'] == 'PROCESSING') {
            addInvoicePayment($invoiceid,$transactionId,$paymentAmount,0,$gatewayModuleName);
        } elseif ($sellix_order['status'] == 'COMPLETED') {
            addInvoicePayment($invoiceid,$transactionId,$paymentAmount,0,$gatewayModuleName);
        } elseif ($sellix_order['status'] == 'WAITING_FOR_CONFIRMATIONS') {

        } elseif ($sellix_order['status'] == 'PARTIAL') {

        } elseif ($sellix_order['status'] == 'PENDING') {

        }
    } else {
        throw new \Exception('Empty response received from gateway.');
    }
} catch (\Exception $e) {
    $error_message = $e->getMessage();
    $message = 'Payment error. '.$error_message;
    sellixLog($gatewayParams['name'], $message, 'Webhook from Gateway Catch');
    echo $message;
    exit;

}
echo 'Web hook finished';
exit;
