<?php

use WHMCS\Database\Capsule;

if(!defined("WHMCS"))
{
    die("This file cannot be accessed directly");
}

function sellixpayment_MetaData()
{
    return array(
        'DisplayName' => 'Sellix Payment Gateway',
        'APIVersion' => '1.0',
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    );
}

/**
 * Create database table
 *
 * This function checks database table and creates if not exists
 */
function sellixpayment_schema()
{
    if (!Capsule::schema()->hasTable('sellixpayment_orders'))
    {
        try
        {
            Capsule::schema()->create('sellixpayment_orders', function ($table)
            {
                $table->increments('id');
                $table->integer('invoiceid');
                $table->integer('userid');
                $table->string('uniquid',100);
            });
        }
        catch (Exception $e)
        {
            logTransaction('Sellix Payment',json_encode($e->getMessage()),"Create Table: Failed.");
            die("Unable to create sellixpayment_orders table: {$e->getMessage()}");
        }
    }
}


function sellixpayment_updateOrder($invoiceid,$userid,$refid)
{
    try
    {
        $query = Capsule::table("sellixpayment_orders")->where("invoiceid",$invoiceid)->where("userid",$userid);

        if(!empty($query->value('id')))
        {
            $query->update(array('uniquid'=>$refid));
        }
        else
        {
            Capsule::table("sellixpayment_orders")
                    ->insert(array(
                                    'invoiceid'=>$invoiceid,
                                    'userid'=>$userid,
                                    'uniquid'=>$refid
                                  ));
        }
    }
    catch (Exception $e)
    {
        $data = array('invoiceid'=>$invoiceid,'userid'=>$userid,'uniquid'=>$refid);

        logTransaction('Sellix Payment',json_encode(array($data,$e->getMessage())),"Unable to update record: Failed.");
    }
}

function sellixpayment_getuniquid($invoiceid,$userid)
{
    return Capsule::table("sellixpayment_orders")->where('invoiceid',$invoiceid)->where('userid',$userid)->value('uniquid');
}

function sellixpayment_config()
{
    global $CONFIG;

    return [
        'FriendlyName' => ['Type' => 'System','Value' => 'Sellix Payment',],
        'apiKey' => ['FriendlyName' => 'API Key','Type' => 'text','Description' => '<a target="_blank" href="https://dashboard.sellix.io/settings/security">Get API key here</a>',],
        'webhooksecret' => ['FriendlyName' => 'Webhook Secret','Type' => 'password','Description' => '<a target="_blank" href="https://dashboard.sellix.io/settings/shop/general">Get Webhook Secret here</a>',],
        'sellixPaymentMethod' => ['FriendlyName' => 'Payment Method','Type' => 'dropdown',
                                                                                        'Options' => [
                                                                                            'bitcoin' => 'Bitcoin',
                                                                                            'litecoin' => 'Litecoin',
                                                                                            'bitcoincash' => 'Bitcoin Cash',
                                                                                            'ethereum' => 'Ethereum',
                                                                                            'paypal' => 'Paypal',
                                                                                            'skrill' => 'Skrill',
                                                                                            'perfectmoney' => 'Perfect Money',
                                                                                            'all' => 'All Payment Methods',
                                                                                        ],
                                                                                'Default' => 'paypal',
                                                                                'Description' => ' Select your payment method that will be available on sellix Payment dashboard.
                                                                                <script>
                                                                                    const paymentArray = ["bitcoin","litecoin","bitcoincash","ethereum"];
                                                                                    jQuery("select[name=\"field[sellixPaymentMethod]\"]").on("change", function ()
                                                                                    {
                                                                                        var selectedMethod = jQuery(this).val();
                                                                                        var thisObject = jQuery("#Payment-Gateway-Config-sellixpayment").find("select[name=\"field[confirmation]\"]");
                                                                                        if(jQuery.inArray(selectedMethod, paymentArray) != -1)
                                                                                        {
                                                                                            thisObject.parents("tr").show();
                                                                                        }
                                                                                        else
                                                                                        {
                                                                                            thisObject.parents("tr").hide();
                                                                                        }
                                                                                    });
                                                                                </script>',
                                                                                ],
        'confirmation' => ['FriendlyName' => 'Confirmation','Type' => 'dropdown',
                                                                        'Options' => ['1' => '1','2' => '2','3' => '3','4' => '4','5' => '5','6' => '6',],
                                                                        'Default' => '2',
                                                                        'Description' => ' Select number of confirmation before invoice mark paid.
                                                                            <script>
                                                                            $(document).ready(function()
                                                                            {
                                                                                var thisObject =jQuery("#Payment-Gateway-Config-sellixpayment").find("select[name=\"field[confirmation]\"]");
                                                                                thisObject.parents("tr").hide();
                                                                                const paymentArray = ["bitcoin","litecoin","bitcoincash","ethereum"];
                                                                                var selectedMethod = jQuery("select[name=\"field[sellixPaymentMethod]\"]").val();

                                                                                if(jQuery.inArray(selectedMethod, paymentArray) != -1)
                                                                                {
                                                                                    thisObject.parents("tr").show();
                                                                                }


                                                                                $("table#Payment-Gateway-Config-sellixpayment").find("tr:eq(6)").before("<tr><td class=\'fieldlabel\'></td><td class=\'fieldarea\'><div class=\'alert alert-info clearfix top-margin-5 bottom-margin-5\'>You must add Web hook Notifications inside your Sellix Payment account. To do this, login to <a href=\'https://dashboard.sellix.io/\' target=\'_blank\'>Sellix Payment Dashboard</a>  and navigate to <em>Developers > Webhooks</em> and click on <b>Add Webhook Endpoint</b> button, enter the following URL: <b>'.$CONFIG['SystemURL'].'/modules/gateways/callback/sellixpayment.php</b> select event as <b>order:paid</b> </div></td></tr>");
                                                                            });



                                                                            </script>',
                        ],
    ];
}


function sellixpayment_link($params)
{
    global $CONFIG;
    global $remote_ip;

    sellixpayment_schema();

    $apiKey = $params['apiKey'];
    $webHookSecret = $params['webhooksecret'];
    $sellixPaymentMethod = $params['sellixPaymentMethod'];
    $confirmation = $params['confirmation'];

    $invoiceId = $params['invoiceid'];
    $description = $params["description"];
    $amount = $params['amount'];
    $currencyCode = $params['currency'];

    $userid = $params['clientdetails']['userid'];
    $firstname = $params['clientdetails']['firstname'];
    $lastname = $params['clientdetails']['lastname'];
    $email = $params['clientdetails']['email'];
    $address1 = $params['clientdetails']['address1'];
    $address2 = $params['clientdetails']['address2'];
    $city = $params['clientdetails']['city'];
    $state = $params['clientdetails']['state'];
    $postcode = $params['clientdetails']['postcode'];
    $country = $params['clientdetails']['country'];
    $phone = $params['clientdetails']['phonenumber'];

    // System Parameters
    $companyName = $params['companyname'];
    $systemUrl = $params['systemurl'];
    $returnUrl = $params['returnurl'];#/viewinvoice.php?id=3
    $langPayNow = $params['langpaynow'];
    $moduleDisplayName = $params['name'];
    $moduleName = $params['paymentmethod'];
    $whmcsVersion = $params['whmcsVersion'];


    $uniquid = sellixpayment_getuniquid($invoiceId,$userid);

    if(!empty($uniquid))
    {
        #valid for 2hours
        $url = "https://checkout.sellix.io/payment/{$uniquid}";

        #checking if uniquid expired ?
        if(curl_init($url) !== false)
        {
            $status = 'Payment created already: Success';

            $htmlOutput = '<form target="_blank" method="POST" action="'.$url.'">';
            //$htmlOutput .= '<input type="hidden" name="uniqid" value="' .$uniquid. '" />';

            $htmlOutput .= '<input type="hidden" name="action" value="paynow" />';
            $htmlOutput .= '<input type="submit" class="btn btn-primary" name="pay" value="' . $langPayNow . '" formtarget="_blank"/>';
            $htmlOutput .= '</form>';

            logTransaction($moduleName,$url,$status);

            return $htmlOutput;
        }
    }

    $data = [
        'apiURL' => "https://dev.sellix.io/v1/payments",
        'method' => "post",
        'data' => [
            'title' => $description,
            'quantity' => '1',
            'currency' => $currencyCode,
            'gateway' => ($sellixPaymentMethod == 'all' ? '' : $sellixPaymentMethod),
            'value' => $amount,
            'confirmations' => $confirmation,
            'email' => $email,
            'webhook'=>$CONFIG['SystemURL'].'/modules/gateways/callback/sellixpayment.php',
            'white_label'=>true,
            'return_url' => $returnUrl
        ]
    ];

    $headers = ['Authorization: Bearer '.$apiKey,'Content-Type: application/json'];


    $rawResponse = sellixpayment_doCurl($data, $headers);

    $response = json_decode($rawResponse['result']);

    $htmlOutput = '';

    if(!empty($response->status == 200))
    {
        sellixpayment_updateOrder($invoiceId,$userid,$response->data->invoice->uniqid);

        $status = 'Payment created: Success';

        $htmlOutput = '<form target="_blank" method="POST" action="https://checkout.sellix.io/payment/'.$response->data->invoice->uniqid.'">';
        //$htmlOutput .= '<input type="hidden" name="uniqid" value="' . $response->data->invoice->uniqid .'" />';

        $htmlOutput .= '<input type="hidden" name="action" value="paynow" />';
        $htmlOutput .= '<input type="submit" class="btn btn-primary" name="pay" value="' . $langPayNow . '" formtarget="_blank" />';
        $htmlOutput .= '</form>';
    }
    else
    {
        $status = 'Payment creatation: Failed';

        $htmlOutput = '<div class="alert alert-danger" role="alert">Unable to pay with selected payment method.</div>';
    }

    logTransaction($moduleName, array('Request'=>$data,'Response'=>json_decode($rawResponse['result'])), $status);

    return $htmlOutput;
}

function sellixpayment_doCurl($data, $headers)
{
    //Initiate cURL.
    $ch = curl_init($data['apiURL']);

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    switch($data['method'])
    {

        case 'post':
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data['data']));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            break;
        default:
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if(curl_errno($ch))
    {
        //If an error occured, throw an Exception.
        $response =  curl_error($ch);
    }
    curl_close($ch); // close the curl

    return ['httpcode' => $httpCode, 'result' => $response];
}