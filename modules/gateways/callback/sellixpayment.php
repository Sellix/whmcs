<?php


require_once __DIR__ . '/../../../init.php';

App::load_function('gateway');
App::load_function('invoice');

use WHMCS\Database\Capsule;


$payload = file_get_contents('php://input');

$gatewayModuleName = basename(__FILE__, '.php');

$gatewayParams = getGatewayVariables($gatewayModuleName);

if (!$gatewayParams['type'])
{
    die("Module Not Activated");
}

$payloadArr = json_decode($payload);

if($payloadArr->event != "order:paid")
{
    logTransaction($gatewayParams['name'], $payload, 'WebHook: Event mismatched');
    exit;
}

$secret = $gatewayParams['webhooksecret'];
$header_signature = $_SERVER["HTTP_X_SELLIX_SIGNATURE"];

$signature = hash_hmac('sha512', $payload, $secret);

if(!hash_equals($signature, $header_signature))
{
    logTransaction($gatewayParams['name'], $payload, 'WebHook: Hash mismatched.');
    exit;
}

$uniqid = $payloadArr->data->uniqid;

$sellixOrderDetails = Capsule::table("sellixpayment_orders")->where('uniquid',$uniqid)->first();

if($sellixOrderDetails->uniquid !== $uniqid)
{
    logTransaction($gatewayParams['name'], $payload, 'WebHook:Invalid uniquid.');
    exit;
}

$invoiceId = $sellixOrderDetails->invoiceid;

checkCbInvoiceID($invoiceId, $gatewayParams['name']);

$transactionId = $uniqid;

checkCbTransID($transactionId);

if($payloadArr->data->status == 'COMPLETED')
{
    $paymentAmount = number_format($payloadArr->data->total, 2, '.', '');
    $paymentFee = (float)0.00;

    #checking for currency mismatch
    $paidCurrency = $payloadArr->data->currency;

    $userCurrency = getCurrency($sellixOrderDetails->userid);

    if($paidCurrency != $userCurrency['code'])
    {
        $paidCurrencyId = Capsule::table("tblcurrencies")->where("code",$paidCurrency)->value('id');

        $paymentAmount = convertCurrency($paymentAmount, $paidCurrencyId, $userCurrency['id']);

        $paymentFee = convertCurrency($paymentFee, $paidCurrencyId, $userCurrency['id']);

        $invoiceDetails = Capsule::table("tblinvoices")->where("id",$invoiceId)->first();

        $total = $invoiceDetails->total;

        if($total < $paymentAmount + 1 && $paymentAmount - 1 < $total)
        {
            $paymentAmount = $total;
        }
    }

    addInvoicePayment($invoiceId,$transactionId,$paymentAmount,$paymentFee,$gatewayModuleName);

    Capsule::table("sellixpayment_orders")->where('invoiceid',$invoiceId)->delete();

    logTransaction($gatewayParams['name'], $payload, 'WebHook: Payment Completed');
}
else
{
    logTransaction($gatewayParams['name'], $payload, 'WebHook: Payment '.$payloadArr->data->status);
}

exit;