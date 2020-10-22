<?php
/**
 * Plugin Name: XUMM payments for WooCommerce
 * Plugin URI: https://github.com/KoenPaas/xumm-for-woocommerce
 * Description: Make XRP payments using XUMM
 * Author: XUMM
 * Author URI: https://xumm.app/
 * Version: 0.1.1
 */

//http://localhost?wc-api=XUMM

function init_xumm_gateway_class() {
    class WC_Gateway_XUMM_Gateway extends WC_Payment_Gateway {

        public $endpoint = 'https://xumm.app/api/v1/platform/';

        public $availableCurrencies = [];

        public function __construct() {
            $this->id = 'xumm';
            $this->icon = plugin_dir_url( __FILE__ ).'public/images/icon.jpg'; //If you want to show an image next to the gateway’s name on the frontend, enter a URL to an image.
            $this->has_fields = false; //– Bool. Can be set to true if you want payment fields to show on the checkout (if doing a direct integration).
            $this->method_title = 'Accept XUMM payments';
            $this->method_description = 'Receive any supported currency into your XRP account using XUMM';

            $this->supports = array(
                'products'
            );

            $this->enabled = $this->get_option('enabled');
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->destination = $this->get_option('destination');
            $this->currency = $this->get_option('currency');
            $this->issuer = $this->get_option('issuer');
            $this->api = $this->get_option('api');
            $this->api_secret = $this->get_option('api_secret');
            $this->currencies = $this->get_option('currencies');
            $this->issuers = $this->get_option('issuers');

            $this->init_form_fields();
            $this->init_settings();

            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

            add_action( 'woocommerce_api_xumm', array( $this, 'callback_handler' ));
            
        }

        public function init_form_fields() {
            $headers = array(
                'Content-Type' => 'application/json',
                'X-API-Key' => $this->api,
                'X-API-Secret' => $this->api_secret
            );

            $response = wp_remote_get('https://xumm.app/api/v1/platform/curated-assets', array(
                'method'    => 'GET',
                'headers'   => $headers,
            ));
            $body = json_decode( $response['body'], true );

            $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Enable/Disable',
                    'label'       => 'Enable XUMM Gateway',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => 'Title',
                    'type'        => 'text',
                    'description' => 'This is the title which the user sees during checkout.',
                    'default'     => 'XRP',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'description' => 'This is the text users will see in the checkout for this payment method',
                    'default'     => 'Pay with XRP using the #1 XRPL wallet: XUMM.',
                ),
                'destination' => array(
                    'title'       => 'XRP Destination address',
                    'type'        => 'text',
                    'description' => 'This is your XRP r Address',
                    'desc_tip'    => true,
                ),
                'api' => array(
                    'title'       => 'API Key',
                    'type'        => 'text',
                    'description' => 'Get the API Key from https://apps.xumm.dev/',
                    'default'     => '',
                ),
                'api_secret' => array(
                    'title'       => 'API Secret Key',
                    'type'        => 'text',
                    'description' => 'Get the API Secret Key from https://apps.xumm.dev/',
                    'default'     => '',
                )
            );

            $this->availableCurrencies['XRP'] = 'XRP';
            foreach ($body['currencies'] as $v) {
                if(get_woocommerce_currency() == $v){
                    $this->availableCurrencies[$v] = $v;
                }
            }

            $this->form_fields['currencies'] = array(
                'title'       => 'Select your currency',
                'description' => 'Here you can select how you want to be paid',
                'type'        => 'select',
                'options'     => $this->availableCurrencies,
            );

            $availableIssuers = [];
            foreach ($body['details'] as $exchange) {
                $exchangeName = $exchange['name'];
                foreach ($exchange['currencies'] as $currency) {
                    $value = $currency['issuer'];
                    $availableIssuers[$value] = $exchangeName;
                }
            }

            $this->form_fields['issuers'] = array(
                'title'       => 'Select your issuer',
                'description' => 'Here you can select how you want to be paid',
                'type'        => 'select',
                'options'     => $availableIssuers,
                'default'     => ''
            );

             $body['account'] = $this->destination;
             $body['store_currency'] = get_woocommerce_currency();

            wp_enqueue_script( 'custom-js', plugins_url( 'js/admin.js' , __FILE__ ), array('jquery') );
            wp_localize_script( 'custom-js', 'xumm_object', $body);

        }

        public function admin_options() {
            function getXummData($id, $self){
                $response = wp_remote_get('https://xumm.app/api/v1/platform/payload/ci/'. $id, array(
                    'method'    => 'GET',
                    'headers'   => array(
                        'Content-Type' => 'application/json',
                        'X-API-Key' => $self->api,
                        'X-API-Secret' => $self->api_secret
                    )
                ));
                $body = json_decode( $response['body'], true );
                return $body;
            }

            if(!empty($_GET['xumm-id'])) {
                $data = getXummData($_GET['xumm-id'], $this);
                //Todo:: first check if success
                if (!empty($data['payload'])) {
                    switch ($data['payload']['tx_type']) {
                        case 'SignIn':
                            $account = $data['response']['account'];
                            if(!empty($account))
                                $this->update_option('destination', $account );
                                echo('<div class="notice notice-success"><p>Sign In successfull please check address & test payment</p></div>');
                            break;
    
                        case 'TrustSet':
    
                            break;
                        
                        default:
                            break;
                    }
                }
            }

            ?>
            <h2><?php _e('XUMM Payment Gateway for WooCommerce','woocommerce'); ?></h2>
            <?php
                if(!empty($_POST["specialAction"])) {
                    ?>
                        <div id="customFormActionResult" style="display: none;">
                            <?php
                                $query_arr = array(
                                    'page'      => 'wc-settings',
                                    'tab'       => 'checkout',
                                    'section'   => 'xumm'
                                );

                                $return_url = get_home_url() .'/wp-admin/admin.php/?' . http_build_query($query_arr);

                                $headers = [
                                    'Content-Type' => 'application/json',
                                    'X-API-Key' => $_POST['woocommerce_xumm_api'],
                                    'X-API-Secret' => $_POST['woocommerce_xumm_api_secret']
                                ];

                                switch($_POST["specialAction"]) {
                                    case 'set_destination':
                                        $identifier = 'sign-in_' . strtoupper(substr(md5(microtime()), 0, 10));
                                        $return_url = add_query_arg( 'xumm-id', $identifier, $return_url);
                                        $body = [
                                            "txjson" => [
                                                "TransactionType" => "SignIn"
                                            ],
                                            "options" => [
                                                "submit" => true,
                                                "return_url" => [
                                                    "web" => $return_url
                                                ]
                                            ],
                                            'custom_meta' => array(
                                                'identifier' => $identifier
                                            )
                                        ];
                                        break;
                                    case 'set_trustline':
                                        $identifier = 'trustline_' . strtoupper(substr(md5(microtime()), 0, 10));
                                        $return_url = add_query_arg( 'xumm-id', $identifier, $return_url);
                                        $body = [
                                            "txjson" => [
                                                "TransactionType" => "TrustSet",
                                                "Account" => $this->destination,
                                                "Fee" => "12",
                                                "LimitAmount" => [
                                                  "currency" => $_POST['woocommerce_xumm_currencies'],
                                                  "issuer" => $_POST['woocommerce_xumm_issuers'],
                                                  "value" => "999999999"
                                                ]
                                            ],
                                            "options" => [
                                                "submit" => true,
                                                "return_url" => [
                                                    "web" => $return_url
                                                ]
                                            ],
                                            'custom_meta' => array(
                                                'identifier' => $identifier
                                            )
                                        ];
                                        break;
                                }

                                $body = wp_json_encode($body);
                            
                                $response = wp_remote_post('https://xumm.app/api/v1/platform/payload', array(
                                    'method'    => 'POST',
                                    'headers'   => $headers,
                                    'body'      => $body
                                    )
                                );

                                if( !is_wp_error( $response ) ) {
                                    $body = json_decode( $response['body'], true );
                                    $redirect = $body['next']['always'];
                                    if ( $redirect != null ) {
                                       // Redirect to the XUMM processor page
                                        echo($redirect);
                                    } else {
                                        // Todo error handling
                                   }
                            
                               } else {
                                   wc_add_notice(  'Connection error.', 'error' );
                               }
                            ?>
                        </div>
                    <?php
                }
            ?>
            <table class="form-table">
                <?php
                $this->generate_settings_html();
                $storeCurrency = get_woocommerce_currency();
                    if(empty($this->api) && empty($this->api_secret)) echo('<div class="notice notice-info"><p>Please add XUMM API keys from <a href="https://apps.xumm.dev/">XUMM API</a></p></div>');
                    else {
                        $response = wp_remote_get('https://xumm.app/api/v1/platform/ping', array(
                            'method'    => 'GET',
                            'headers'   => array(
                                'Content-Type' => 'application/json',
                                'X-API-Key' => $this->api,
                                'X-API-Secret' => $this->api_secret
                                )
                            ));
                        if( !is_wp_error( $response ) ) {
                            $body = json_decode( $response['body'], true );
                            if(!empty($body['pong'] && $body['pong'] == true)) {
                                echo('<div class="notice notice-success"><p>Connection to <a href="https://apps.xumm.dev/">XUMM API</a> is CONNECTED</p></div>');
                                
                                $webhookApi = $body['auth']['application']['webhookurl'];
                                $webhook = get_home_url() . '/?wc-api=XUMM';
                                if($webhook != $webhookApi) echo('<div class="notice notice-error"><p>WebHook incorrect on <a href="https://apps.xumm.dev/">XUMM API</a>, should be '. $webhook .'</p></div>');
                            }
                            else echo('<div class="notice notice-error"><p>Connection API Error to the <a href="https://apps.xumm.dev/">XUMM API</a>. Check your API keys. Got a code: '. $body['error']['code'] .'</p></div>');
                        } else {
                            echo('<div class="notice notice-error"><p>Connection Error to the <a href="https://apps.xumm.dev/">XUMM API. WP GET ERROR</a></p></div>');
                       }
                    }
                    if(!in_array($storeCurrency, $this->availableCurrencies)) echo('<div class="notice notice-error"><p>Please change store currency</p></div>');
                    if ($storeCurrency != 'XRP' && $this->currencies != 'XRP' && $storeCurrency != $this->currencies) echo('<div class="notice notice-error"><p>Please change currency here</p></div>');
                ?>
            </table>

            <input type="hidden" id="specialAction" name="specialAction" value="">

            <pre>

            </pre>

                <!-- Todo::html -->
            <!-- <input type="submit" value="Set Trustline"> -->
            <button type="button" class="customFormActionBtn" id="set_destination" style="border-style: none;">
                <?php echo(file_get_contents(plugin_dir_path( __FILE__ ).'public/images/signin.svg')); ?>
            </button>
            <button type="button" class="customFormActionBtn" id="set_trustline">
                Add Trustline
            </button>

            <script>
                jQuery("form#mainform").submit(function (e) {
                    if (jQuery(this).find("input#specialAction").val() !== '') {

                        console.log('We gaan een custom action uitvoeren met AJAX request')
                        e.preventDefault()
                        jQuery.ajax({
                            url: document.location.href,
                            type: 'POST',
                            data: jQuery(this).serialize(),
                            success: function (response) {
                                console.log('We hebben antwoord')
                                let tlResponse = jQuery(response).find("#customFormActionResult").html().trim()

                                window.location.href = tlResponse

                            }
                        });
                        return false
                    }
                })
                jQuery("button.customFormActionBtn").click(function () {
                    jQuery("input#specialAction").val(jQuery(this).attr('id'))
                    jQuery("form#mainform").submit()
                })
            </script>

            <?php

        }

        public function payment_fields() {
            echo wpautop( wp_kses_post( $this->description ) );
            echo file_get_contents(plugin_dir_path( __FILE__ ).'public/images/pay.svg');
        }

        public function process_payment( $order_id ) {
            $order = wc_get_order( $order_id );
            $storeCurrency = get_woocommerce_currency();
            $exchange_rates_url = 'https://data.ripple.com/v2/exchange_rates/';

            //Check against that XRP cannot have an issuer and if trustlines are available in what is used in the 
            if($storeCurrency == 'XRP' && $this->currencies != 'XRP') {
                $apiCall = $exchange_rates_url . $storeCurrency .'/'. $this->currencies .'+'. $this->issuers;             
            } else if ($storeCurrency != 'XRP' && $this->currencies == 'XRP') {
                //$apiCall = $exchange_rates_url . $storeCurrency .'+'. $this->issuers .'/'. $this->currencies;
                $apiCall = 'https://www.bitstamp.net/api/v2/ticker_hour/' . $this->currencies . $storeCurrency;  
                $apiCall = strtolower($apiCall);
            } else if ($storeCurrency == 'XRP' && $this->currencies == 'XRP') {
                $apiCall = null;
                $xr = 1;
            } else if ($storeCurrency == $this->currencies) {
                $apiCall = null;
                $xr = 1;
            } else if ($storeCurrency != 'XRP' && $this->currencies != 'XRP' && $storeCurrency != $this->currencies) {
                wc_add_notice( 'Currency pair not supported, conversion from '. $storeCurrency . ' to -> '.$this->currencies, 'error' );
                //TODO: set correct issuer for base currency.!!!!!!!!!!!!!!!!!!!!!!!
                //$apiCall = $exchange_rates_url . $storeCurrency .'+'. $this->issuers .'/'. $this->currencies .'+'. $this->issuers;
                return;
            } else {
                wc_add_notice(  'Currency issue', 'error' );
                return;
            }

            if(!is_null($apiCall)) {
                $response = wp_remote_get($apiCall);
                $body = json_decode( $response['body'], true );

                preg_match('@^(?:https://)?([^/]+)@i', $apiCall, $matches);
                $host = $matches[1];
                switch ($host) {
                    case 'www.bitstamp.net':
                        $xr = 1 / $body['ask'];
                        $totalSum = $order->get_total() * $xr;
                        break;
                    case 'data.ripple.com':
                        $xr = $body['rate'];
                        $totalSum = $order->get_total() * $xr;
                        break;
                    default:
                        $xr = null;
                        $totalSum = $order->get_total();
                        break;
                }
            } else {
                $totalSum = $order->get_total();
            }

            $identifier = $order_id . '_' . strtoupper(substr(md5(microtime()), 0, 10));

            $totalSum = round($totalSum, 6);
            $query = array(
                'wc-api' => 'XUMM',
                'order_id' => $identifier
            );

            //$return_url = get_home_url() . '/?' . http_build_query($query);
            $return_url = add_query_arg($query, get_home_url());

            $headers = array(
                'Content-Type' => 'application/json',
                'X-API-Key' => $this->api,
                'X-API-Secret' => $this->api_secret
            );

            $body = [
                'txjson'  => array(
                    'TransactionType' => 'Payment',
                    'Destination' => $this->destination,
                    'Amount' => array(
                        'currency' => $this->currencies,
                        'value' => $totalSum,
                        'issuer' => $this->issuers
                        ),
                    'Flags' => 2147483648
                    ),
                'options' => array(
                    'submit' => 'true',
                    'expire' => 15,
                    'return_url' => array(
                        'web' => $return_url
                    )   
                ),
                'custom_meta' => array(
                    'identifier' => $identifier,
                    'blob' => array(
                        'xr' => $xr,
                        'base' => $storeCurrency
                    )
                )
            ];
            $body = wp_json_encode($body);

            wc_add_notice($headers, 'error');

            $response = wp_remote_post('https://xumm.app/api/v1/platform/payload', array(
                'method'    => 'POST',
                'headers'   => $headers,
                'body'      => $body
                )
            );
         
             if( !is_wp_error( $response ) ) {
                 $body = json_decode( $response['body'], true );
                 
                 if ( $body['next']['always'] != null ) {
                    // Redirect to the XUMM processor page
                    return array(
                        'result' => 'success',
                        'redirect' => $body['next']['always']
                    );
         
                 } else {
                     //Todo:: Check against errors from the app.xumm api
                     //Like Duplicate ID
                    wc_add_notice(  'Please try again.', 'error' );
                    return;
                }
         
            } else {
                wc_add_notice(  'Connection error.', 'error' );
                return;
            }
         
        }

        public function callback_handler() {
            $headers = array(
                'Content-Type' => 'application/json',
                'X-API-Key' => $this->api,
                'X-API-Secret' => $this->api_secret
            );

            function getPayloadXummById($id, $headers) {
                $response = wp_remote_get('https://xumm.app/api/v1/platform/payload/ci/'. $id, array(
                    'method'    => 'GET',
                    'headers'   => $headers
                ));
                $body = json_decode( $response['body'], true );
                return $body;
            }

            function getTransactionDetails($txid, $headers) {
                if (empty($txid)) return false;
                // $tx = wp_remote_get('https://data.ripple.com/v2/transactions/'. $txid, array(
                //     'method'    => 'GET',
                //     'headers'   => array(
                //         'Content-Type' => 'application/json'
                //     )
                // ));
                $tx = wp_remote_get('https://xumm.app/api/v1/platform/xrpl-tx/'. $txid, array(
                    'method'    => 'GET',
                    'headers'   => $headers
                ));
                return json_decode( $tx['body'], true );
            }

            function checkDeliveredAmount($delivered_amount, $total, $xr) {
                if($delivered_amount != null) {
                    switch (gettype($delivered_amount)) {
                        default:
                            $log = 'todo::delete this and switch/case integer stops working';
                        case 'integer':
                            $delivered_amount = $delivered_amount/1000000;
                            if($delivered_amount < ( $total * $xr)) {
                                $msg = 'not enough XRP money';
                                error_log($msg);
                                wc_add_notice('total: '. $total . ' ; paid: '. $delivered_amount);
                                wc_add_notice($total == $delivered_amount);
                                wc_add_notice( $msg, 'error' );
                                return false;
                            } else return true;
                            break;
                        case 'array':
                            if($delivered_amount['value'] < $total) {
                                $msg = 'Step 1: not enough money';
                                error_log($msg);
                                wc_add_notice( $msg, 'error' );
                                $order->add_order_note( 'Your order with custom id: '.$custom_identifier.' is not paid only '. $delivered_amount['value']. ' Remaining: '. $total-$delivered_amount['value'], true );
                                return false;
                            }
                            if($delivered_amount['currency'] != $order->get_currency()) {
                                $msg = 'Step 2: Wrong currency';
                                error_log($msg);
                                wc_add_notice( $msg, 'error' );
                                return false;
                            }
                            if($delivered_amount['issuer'] != $this->issuers) {
                                $msg = 'Step 3: Wrong Issuer';
                                error_log($msg);
                                wc_add_notice( $msg, 'error' );
                                return false;
                            }
                            else return true;
                            break;
                    }
                } else {
                    error_log('Error on amount');
                    return false;
                }
            }

            function getReturnUrl($custom_identifier, $order, $headers) {
                $payload = getPayloadXummById($custom_identifier, $headers);
                $txid = $payload['response']['txid'];
                $xr = $payload['custom_meta']['blob']['xr'];

                $txbody = getTransactionDetails($txid, $headers);
                if(empty($txbody)) {
                    wc_add_notice( 'Failed payment please try again', 'error' );
                    return $order->get_checkout_payment_url(false);
                }
                $delivered_amount = $txbody['transaction']['meta']['delivered_amount'];
                $total = $order->get_total();
                if(!checkDeliveredAmount($delivered_amount, $total, $xr)) {
                    $log = 'redirect to payment page';
                    error_log( $log );
                    $redirect_url = $order->get_checkout_payment_url(false);
                    return $redirect_url;
                } else {
                    $order->payment_complete();
                    wc_reduce_stock_levels( $order->get_id() );
                    $order->add_order_note( 'Hey, your order with custom id: '.$custom_identifier.' is paid! Thank you!', true );
                    WC()->cart->empty_cart();
                    return WC_Payment_Gateway::get_return_url( $order );
                }
            }

            if(!empty($_GET["order_id"])) {
                $custom_identifier = $_GET["order_id"];
                $order_id = explode("_", $custom_identifier)[0];
                $order = wc_get_order( $order_id );

                $order_status  = $order->get_status();
                switch ($order_status) {
                    case 'processing':
                        wc_add_notice('Order Status: Processing');
                        $redirect_url = $this->get_return_url( $order );
                        break;
                    case 'pending':
                        $redirect_url = getReturnUrl($custom_identifier, $order, $headers);
                        break;
                    case 'on-hold':
                        $redirect_url = $order->get_checkout_payment_url(false);
                        break;
                    case 'completed':
                        wc_add_notice('Order Status: Completed');
                        $redirect_url = $this->get_return_url( $order );
                        break;
                    case 'cancelled':
                        wc_add_notice(  'Your order has been cancelled, please try again.', 'error' );
                        $redirect_url = $order->get_checkout_payment_url(false);
                        break;
                    case 'failed':
                        wc_add_notice(  'Your payment has failed, please try again.', 'error' );
                        $redirect_url = $order->get_checkout_payment_url(false);
                        break;
                    case 'refunded':
                        $redirect_url = $this->get_return_url( $order );
                        break;
                    default:
                        wc_add_notice(  'There is something wrong with the order, please contact us.', 'error' );
                        wp_safe_redirect($order->get_checkout_payment_url(false));
                        error_log($order_status);
                        break;
                }
                wp_safe_redirect($redirect_url);
            }

            // Callback section

            $json = file_get_contents('php://input');
            $data = json_decode($json, true);

            $uuid = $data['payloadResponse']['payload_uuidv4'];

            if($uuid != null) {
                $custom_identifier = $data['custom_meta']['identifier'];
                if ($custom_identifier != null) {
                    $txid = getPayloadStatusXummWithId($custom_identifier, $headers);
                    // we received the payment
                    $txbody = getTransactionDetails($txid, $headers);

                    $order_id = explode("_", $custom_identifier)[0];
                    $order = wc_get_order( $order_id );
                    $total = $order->get_total();
                    $delivered_amount = $txbody['transaction']['meta']['delivered_amount'];

                    if(!checkDeliveredAmount($amount, $total)) {
                        $log = 'redirect to payment page';
                        error_log( $log );
                        //$return_url = $this->get_return_url( $order );
                        $redirect_url = $order->get_checkout_payment_url(false);
                        error_log($redirect_url);
                        exit;
                    }

                    $order->payment_complete();
                    wc_reduce_stock_levels( $order_id );
            
                    //some notes to customer (replace true with false to make it private)
                    $order->add_order_note( 'Hey, your order with custom id: '.$custom_identifier.' is paid! Thank you!', true );
            
                    //Empty cart
                    WC()->cart->empty_cart();

                }
            }
        }

    }
}

if(class_exists('WooCommerce')) {
    add_action( 'plugins_loaded', 'init_xumm_gateway_class' );

    function add_xumm_gateway_class( $methods ) {
        $methods[] = 'WC_Gateway_XUMM_Gateway'; 
        return $methods;
    }

    add_filter( 'woocommerce_payment_gateways', 'add_xumm_gateway_class' );

    add_filter( 'woocommerce_currencies', 'add_xrp_currency' );
    function add_xrp_currency( $xrp_currency ) {
        $xrp_currency['XRP'] = __( 'XRP', 'woocommerce' );
        $xrp_currency['ETH'] = __( 'Ethereum', 'woocommerce' );
        return $xrp_currency;
    }

    add_filter('woocommerce_currency_symbol', 'add_xrp_currency_symbol', 10, 2);
    function add_xrp_currency_symbol( $custom_currency_symbol, $custom_currency ) {
        switch( $custom_currency ) {
            case 'XRP': $custom_currency_symbol = 'XRP '; break;
            case 'ETH': $custom_currency_symbol = 'Ξ'; break;
        }
        return $custom_currency_symbol;
    }
}

?>