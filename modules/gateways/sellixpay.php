<?php
/**
 * WHMCS Sellix Pay Payment Gateway Module
 *
 * Accept Cryptocurrencies, Credit Cards, PayPal and regional banking methods with Sellix Pay.
 *
 * @copyright Copyright (c) WHMCS Limited 2023
 * @license http://www.whmcs.com/license/ WHMCS Eula
 */

use WHMCS\Database\Capsule;
 
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Define module related meta data.
 *
 * @return array
 */
function sellixpay_MetaData()
{
    return array(
        'DisplayName' => 'Sellix Pay',
        'APIVersion' => '1.1',
        'DisableLocalCredtCardInput' => false,
        'TokenisedStorage' => false,
    );
}

/**
 * Define gateway configuration options.
 *
 * The fields you define here determine the configuration options that are
 * presented to administrator users when activating and configuring your
 * payment gateway module for use.
 *
 * @return array
 */
function sellixpay_config()
{
    $inputs = array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'Sellix Pay',
        ),
        'api_key' => array(
            'FriendlyName' => 'API Key',
            'Type' => 'text',
            'Description' => 'Please enter your Sellix API Key',
        ),
        'url_branded' => array(
            'FriendlyName' => 'Branded URL',
            'Type' => 'yesno',
            'Description' => 'If this is enabled, customer will be redirected to your branded sellix pay checkout url',
        ),
        'order_prefix' => array(
            'FriendlyName' => 'Order/Invoice ID Prefix',
            'Type' => 'text',
            'Size' => 10,
            'Description' => 'The prefix before the order number. For example, a prefix of "Order #" and a ID of "10" will result in "Order #10"',
        ),
    );

    $inputs['debug'] = array(
        'FriendlyName' => 'Debug mode',
        'Type' => 'yesno',
    );
    
    return $inputs;
}

function sellixpay_link($params) {
    global $_LANG;
 
    createSellixpayDbTable();
    sellixLog($params['name'], $_REQUEST, 'Request Data on link function');
    
    $htmlOutput = '';
    try {
        if (isset($params['invoiceid']) && $params['invoiceid'] > 0) {
            
            $clientArea = new WHMCS\ClientArea();
            $pageName = $clientArea->getCurrentPageName();
            $payment_url = '';

            if ($pageName == 'viewinvoice') {
                $invoiceid = (int)$params['invoiceid'];

                $lastInvoiceId = (int)getUserLastInvoiceId($params['clientdetails']['userid']);
                if ($lastInvoiceId != $invoiceid) {
                    $payment_url = getSellixpayOrderByColumn($params['invoiceid'], 'payment_url');
                    if (empty($payment_url)) {
                        $payment_url = generateSellixPayment($params);
                        updateSellixpayOrder($params['invoiceid'], 'payment_url', $payment_url);
                    }

                    if (!empty($payment_url)) {
                        $htmlOutput .= '<form action="' . $payment_url . '">';
                        $htmlOutput .= '<input type="hidden" name="action" value="paynow" />';
                        $htmlOutput .= '<input type="hidden" name="sellix_url_generate" value="regenerate" />';
                        $htmlOutput .= '<input class="btn btn-primary" type="submit" value="' . $params['langpaynow'] . '" />';
                        $htmlOutput .= '</form>';
                    } else {
                        throw new Exception('Sellix checkout URL is failed to generate.');
                    }
                } else {//last invoice id
                    $payment_url = getSellixpayOrderByColumn($params['invoiceid'], 'payment_url');
                    if (empty($payment_url)) {
                        $payment_url = generateSellixPayment($params);
                        updateSellixpayOrder($params['invoiceid'], 'payment_url', $payment_url);
                    }
                    
                    if (!empty($payment_url)) {
                        $htmlOutput .= '<form action="' . $payment_url . '">';
                        $htmlOutput .= '<input type="hidden" name="action" value="paynow" />';
                        $htmlOutput .= '<input type="hidden" name="sellix_url_generate" value="regenerate" />';
                        $htmlOutput .= '<input class="btn btn-primary" type="submit" value="' . $params['langpaynow'] . '" />';
                        $htmlOutput .= '</form>';
                    } else {
                        throw new Exception('Sellix checkout URL is failed to generate.');
                    }
                }
            } else {//not viewinvoice page
                $payment_url = getSellixpayOrderByColumn($params['invoiceid'], 'payment_url');
                if (empty($payment_url)) {
                    $payment_url = generateSellixPayment($params);
                    updateSellixpayOrder($params['invoiceid'], 'payment_url', $payment_url);
                }

                if (!empty($payment_url)) {
                    sellixLog($params['name'], 'Returned url: '.$payment_url, 'Payment process concerning invoice '.$params['invoiceid']);
                    $htmlOutput .= '<form action="' . $payment_url . '">';
                    $htmlOutput .= '<input type="hidden" name="action" value="paynow" />';
                    $htmlOutput .= '<input type="submit" value="' . $params['langpaynow'] . '" />';
                    $htmlOutput .= '</form>';
                } else {
                    throw new Exception('Sellix checkout URL is failed to generate.');
                }
            }
        } else {
            sellixRedirect($params['systemurl']);
        }
    } catch (\Exception $e) {
        $error_message = $e->getMessage();
        sellixLog($params['name'], 'Payment Gateway Request Catch', 'Exception: '.$e->getMessage());
        $htmlOutput .= '<h6 style="color:red">An error occurred while initiating payment transaction: '.$error_message.'</h6>';
    }

    return $htmlOutput;
}

function sellixRedirect($url)
{
    header('Location:'.$url);
}

/**
 * Generate Sellix Payment
 *
 * @param string $configParams
 *
 * @return string sellix checkout payment url
 */
function generateSellixPayment($configParams)
{
    $params = [
        'title' => $configParams['order_prefix'] . $configParams['invoiceid'],
        'currency' => $configParams['currency'],
        'return_url' => getSellixReturnUrl($configParams),
        'webhook' => getSellixWebhookUrl($configParams),
        'email' => $configParams['clientdetails']['email'],
        'value' => $configParams['amount'],
    ];

    $route = "/v1/payments";
    $response = sellixPostAuthenticatedJsonRequest($configParams, $route, $params);

    if (isset($response['body']) && !empty($response['body'])) {
        $responseDecode = json_decode($response['body'], true);
        if (isset($responseDecode['error']) && !empty($responseDecode['error'])) {
            $error_message = 'Payment error: '.$responseDecode['status'].'-'.$responseDecode['error'];
            throw new \Exception($error_message);
        }

        $url = $responseDecode['data']['url'];
        if ($configParams['url_branded'] == 'on') {
            if (isset($responseDecode['data']['url_branded'])) {
                $url = $responseDecode['data']['url_branded'];
            }
        }

        return $url;
    } else {
        throw new \Exception('Payment error: '.$response['error']);
    }
}

/**
* Generate Valid Sellix Order
*
* @param \Sellix\Pay\Model\Pay $model
* @param string $order_uniqid
*
* @return array sellix order
*/
function sellixValidSellixOrder($params, $order_uniqid)
{
   $route = "/v1/orders/" . $order_uniqid;
   $response = sellixPostAuthenticatedJsonRequest($params, $route, '', '', 'GET');

   sellixLog($params['name'], $response['body'], 'Order validation returned');

   if (isset($response['body']) && !empty($response['body'])) {
       $responseDecode = json_decode($response['body'], true);
       if (isset($responseDecode['error']) && !empty($responseDecode['error'])) {
           $message = 'Payment error: '.$responseDecode['status'].'-'.$responseDecode['error'];
           throw new \Exception($message);
       }

       return $responseDecode['data']['order'];
   } else {
       throw new \Exception('Unable to verify order via Sellix Pay API');
   }
}

function getSellixReturnUrl($params)
{
    $url = $params['systemurl'].'/modules/gateways/callback/sellixpay/return.php?invoiceid='.$params['invoiceid'];
    return $url;
}

function getSellixWebhookUrl($params)
{
    $url = $params['systemurl'].'/modules/gateways/callback/sellixpay/webhook.php?invoiceid='.$params['invoiceid'];
    return $url;
}

function getSellixPaymentCreateAjxUrl($params)
{
    $url = $params['systemurl'].'/modules/gateways/callback/sellixpay/payajax.php?invoiceid='.$params['invoiceid'];
    return $url;
}

/**
 * Log Transaction.
 *
 * Add an entry to the Gateway Log for debugging purposes.
 *
 * The debug data can be a string or an array.
 *
 * @param string $gatewayName Display label
 * @param string|array $debugData Data to log
 * @param string $transactionStatus Status
 */
function sellixLog($gatewayName, $debugData, $transactionStatus)
{
    logTransaction($gatewayName, $debugData, $transactionStatus);
}

/**
* Sellix Post Authenticated Json Request
*
* @param string $route
* @param mixed $body
* @param mixed $extra_headers
* @param string $method
*
* @return array $response
*/
function sellixPostAuthenticatedJsonRequest($params, $route, $body = false, $extra_headers = false, $method = "POST")
{
    $server = getApiUrl();

    $url = $server . $route;

    $uaString = 'Sellix WHMCS - '.$params['whmcsVersion'].' (PHP ' . PHP_VERSION . ')';
    $apiKey = trim($params['api_key']);
    $headers = [
        'Content-Type: application/json',
        'User-Agent: '.$uaString,
        'Authorization: Bearer ' . $apiKey
    ];

    if ($extra_headers && is_array($extra_headers)) {
        $headers = array_merge($headers, $extra_headers);
    }

    sellixLog($params['name'], $url, 'API URL');
    sellixLog($params['name'], $headers, 'Headers');
    sellixLog($params['name'], $body, 'Body');

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    if (!empty($body)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response['body'] = curl_exec($ch);
    $response['code'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    sellixLog($params['name'], $response['body'], 'Response Body');
    $response['error'] = curl_error($ch);

    return $response;
}

/**
* Get Api Url
*
* @return string
*/
function getApiUrl()
{
   return 'https://dev.sellix.io';
}

/**
 * Create database table
 *
 * This function checks database table and creates if not exists
 */
function createSellixpayDbTable()
{
    if (!Capsule::schema()->hasTable('sellixpay_orders')) {
        try {
            Capsule::schema()->create(
                'sellixpay_orders',
                function ($table) {
                    $table->increments('id');
                    $table->integer('invoiceid');
                    $table->string('payment_gateway');
                    $table->string('payment_url');
                    $table->string('transaction_id');
                    $table->string('status');
                    $table->text('response');
                }
            );
        }
        catch (\Exception $e) { }
    }
}

function updateSellixpayOrder($invoiceid, $column, $value)
{
    if (!empty($value)) {
        try {
            $query = Capsule::table("sellixpay_orders")->where("invoiceid", $invoiceid);
            if (!empty($query->value('id'))) {
                $query->update(array($column => $value));
            } else {
                Capsule::table("sellixpay_orders")->insert(
                    array(
                        'invoiceid'=>$invoiceid,
                        $column => $value
                    )
                );
            }
        }
        catch (\Exception $e) { }
    }
}

function getSellixpayOrderPaymentGateway($invoiceid, $payment_gateway)
{
    if (empty($payment_gateway)) {
        return Capsule::table("sellixpay_orders")->where("invoiceid", $invoiceid)->value('payment_gateway');
    } else {
        return $payment_gateway;
    }
}

function getSellixpayOrderByColumn($invoiceid, $column)
{
    try {
        return Capsule::table("sellixpay_orders")->where("invoiceid", $invoiceid)->value($column);
    }
    catch (\Exception $e) { 
        return false;
    }
}

function getUserLastInvoiceId($userid)
{
    try {
        return Capsule::table("tblinvoices")->where("userid", $userid)->orderBy('id', 'desc')->limit(1)->value('id');
    }
    catch (\Exception $e) { 
        return false;
    }
}
