<?php
/**
 * Plugin Name: XUMM payments for WooCommerce
 * Plugin URI: https://github.com/KoenPaas/xumm-for-woocommerce
 * Description: Make XRP payments using XUMM
 * Author: XUMM
 * Author URI: https://xumm.app/
 * Version: 0.2
 */

//http://localhost?wc-api=XUMM

//
$lang = json_decode(file_get_contents(plugin_dir_path( __FILE__ ) . 'languages/xumm-payment.en-En.json'));

function init_xumm_gateway_class() {
    global $lang;

    class WC_Gateway_XUMM_Gateway extends WC_Payment_Gateway {
        public $endpoint = 'https://xumm.app/api/v1/platform/';

        public $availableCurrencies = [];

        public function __construct() {
            global $lang;

            $this->id = 'xumm';
            $this->icon = plugin_dir_url( __FILE__ ).'public/images/icon.jpg'; //If you want to show an image next to the gateway’s name on the frontend, enter a URL to an image.
            $this->has_fields = false; //– Bool. Can be set to true if you want payment fields to show on the checkout (if doing a direct integration).
            $this->method_title = $lang->construct->title;
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
            global $lang;

            $form = $lang->form;

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
                    'title'       => $form->enabled->title,
                    'label'       => $form->enabled->label,
                    'type'        => 'checkbox',
                    'description' => $form->enabled->description,
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => $form->title->title,
                    'type'        => 'text',
                    'description' => $form->title->description,
                    'default'     => 'XRP',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => $form->description->title,
                    'type'        => 'textarea',
                    'description' => $form->description->description,
                    'default'     => $form->description->default,
                ),
                'destination' => array(
                    'title'       => $form->destination->title,
                    'type'        => 'text',
                    'description' => $form->destination->description,
                    'desc_tip'    => true,
                ),
                'api' => array(
                    'title'       => $form->api->title,
                    'type'        => 'text',
                    'description' => $form->api->description,
                    'default'     => '',
                ),
                'api_secret' => array(
                    'title'       => $form->api_secret->title,
                    'type'        => 'text',
                    'description' => $form->api_secret->description,
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
                'title'       => $form->currencies->title,
                'description' => $form->currencies->description,
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
                'title'       => $form->issuers->title,
                'description' => $form->issuers->description,
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
            global $lang;
            $admin = $lang->admin;

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
                                echo('<div class="notice notice-success"><p>'.$admin->signin->success.'</p></div>');
                            break;
    
                        case 'TrustSet':
                            //Todo show message when trustset is success with: $admin->trustset->success
                            break;
                        
                        default:
                            break;
                    }
                }
            }

            ?>
            <h2><?php _e($admin->title,'woocommerce'); ?></h2>
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
                                        echo('<div class="notice notice-error"><p>'.$admin->api->redirect_error.' <a href="https://apps.xumm.dev/">'. $admin->api->href .'</a>. '. $admin->api->keys .'Error Code:'. $body['error']['code'] .'</p></div>');
                                   }
                            
                               } else {
                                    echo('<div class="notice notice-error"><p>'.$admin->api->no_response.' <a href="https://apps.xumm.dev/">'. $admin->api->href .'</a>.</p></div>');
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
                    if(empty($this->api) || empty($this->api_secret)) echo('<div class="notice notice-info"><p>'. $admin->api->no_keys .' <a href="https://apps.xumm.dev/">'. $admin->api->href .'</a></p></div>');
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
                                echo('<div class="notice notice-success"><p>'.$admin->api->ping_success.' <a href="https://apps.xumm.dev/">'.$admin->api->href.'</a></p></div>');
                                
                                $webhookApi = $body['auth']['application']['webhookurl'];
                                $webhook = get_home_url() . '/?wc-api=XUMM';
                                if($webhook != $webhookApi) echo('<div class="notice notice-error"><p>'.$admin->api->incorrect_webhook.' <a href="https://apps.xumm.dev/">'.$admin->api->href.'</a>, '.$admin->api->corrected_webhook.' '.$webhook.'</p></div>');
                            }
                            else echo('<div class="notice notice-error"><p>'.$admin->api->ping_error.' <a href="https://apps.xumm.dev/">'.$admin->api->href.'</a>. '.$admin->api->keys .'Error Code:'. $body['error']['code'].'</p></div>');
                        } else {
                            echo('<div class="notice notice-error"><p>'.$admin->api->no_response.' <a href="https://apps.xumm.dev/">'.$admin->api->href.'</a></p></div>');
                       }
                    }
                    if(!in_array($storeCurrency, $this->availableCurrencies)) echo('<div class="notice notice-error"><p>'.$admin->currency->store_unsupported.'</p></div>');
                    if ($storeCurrency != 'XRP' && $this->currencies != 'XRP' && $storeCurrency != $this->currencies) echo('<div class="notice notice-error"><p>'.$admin->currency->gateway_unsupported.'</p></div>');
                ?>
            </table>

            <input type="hidden" id="specialAction" name="specialAction" value="">
            <button type="button" class="customFormActionBtn" id="set_destination" style="border-style: none;">
                <?php echo(file_get_contents(plugin_dir_path( __FILE__ ).'public/images/signin.svg')); ?>
            </button>
            <button type="button" class="customFormActionBtn button-primary" id="set_trustline">
                <?php echo ($admin->trustset->button); ?>
            </button>

            <script>
                jQuery("form#mainform").submit(function (e) {
                    if (jQuery(this).find("input#specialAction").val() !== '') {
                        e.preventDefault()
                        jQuery.ajax({
                            url: document.location.href,
                            type: 'POST',
                            data: jQuery(this).serialize(),
                            success: function (response) {
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
            global $lang;
            $payment = $lang->payment;

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
                //wc_add_notice( 'Currency pair not supported, conversion from '. $storeCurrency . ' to -> '.$this->currencies, 'error' ); //Todo: Debug
                wc_add_notice($payment->error->currency_pair, 'error');
                return;
            } else {
                wc_add_notice($payment->error->currency, 'error' );
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
                     //Todo:: Check against detailed errors from the app.xumm api
                     //Like Duplicate ID etc...
                    wc_add_notice( $payment->error->xumm_api, 'error' );
                    return;
                }
         
            } else {
                wc_add_notice( $payment->error->wp_connection, 'error' );
                return;
            }
         
        }

        public function callback_handler() {
            global $lang;

            function getPayloadXummById($id, $headers) {
                global $lang;
                $error = $lang->callback->error->xumm_payload;

                $response = wp_remote_get('https://xumm.app/api/v1/platform/payload/ci/'. $id, array(
                    'method'    => 'GET',
                    'headers'   => $headers
                ));

                if( is_wp_error( $response ) ) {
                    wc_add_notice($error, 'error' );
                    exit();
                }

                $body = json_decode( $response['body'], true );
                return $body;
            }

            function getTransactionDetails($txid, $headers) {
                global $lang;
                $error = $lang->callback->error->xumm_payload;

                if (empty($txid)) return false;
                $tx = wp_remote_get('https://xumm.app/api/v1/platform/xrpl-tx/'. $txid, array(
                    'method'    => 'GET',
                    'headers'   => $headers
                ));
                if( is_wp_error( $tx ) ) {
                    $tx = wp_remote_get('https://data.ripple.com/v2/transactions/'. $txid, array(
                        'method'    => 'GET',
                        'headers'   => array(
                            'Content-Type' => 'application/json'
                        )
                    ));
                    if(is_wp_error( $tx )) {
                        wc_add_notice($error, 'error' );
                        exit();
                    }
                }
                return json_decode( $tx['body'], true );
            }

            function checkDeliveredAmount($delivered_amount, $order, $xr, $issuers, $txid) {
                global $lang;
                $error = $lang->callback->error;
                $note = $lang->callback->note;

                $total = $order->get_total();
                if($delivered_amount != null) {

                    switch (gettype($delivered_amount)) {
                        case 'integer':
                            $delivered_amount = $delivered_amount/1000000;
                            if($delivered_amount < ( $total * $xr)) {
                                if($delivered_amount == 0) wc_add_notice($error->zero, 'error');
                                else {
                                    wc_add_notice($error->insufficient, 'error' );
                                    $order->add_order_note($note->insufficient->message .'<br>'.$note->insufficient->paid .' XRP '. $delivered_amount .'<br>'. $note->insufficient->open .' XRP '. $total-$delivered_amount .'<br>'. '<a href="https://bithomp.com/explorer/'.$txid.'">'. $note->insufficient->information .'</a>',true);
                                }
                                return false;
                            } else return true;
                            break;

                        case 'array':

                            if($delivered_amount['issuer'] != $issuers) {
                                wc_add_notice( $error->issuer, 'error' );
                                $order->add_order_note($note->issuer->message .'<br>'.$note->issuer->paid .' '. $delivered_amount['currency'] .' '. $delivered_amount['value'] .'<br> <a href="https://bithomp.com/explorer/'.$txid.'">'. $note->issuer->information .'</a>',true);
                                return false;
                            }

                            if($delivered_amount['currency'] != $order->get_currency()) {
                                wc_add_notice( $error->currency, 'error' );
                                $order->add_order_note($note->currency->message .'<br>'.$note->currency->paid .' '. $delivered_amount['currency'] .' '. $delivered_amount['value'] .'<br> <a href="https://bithomp.com/explorer/'.$txid.'">'. $note->currency->information .'</a>',true);
                                return false;
                            }

                            if($delivered_amount['value'] <= ($total * 0.99)) {
                                if($delivered_amount['value'] == 0) wc_add_notice($error->zero, 'error');
                                else {
                                    wc_add_notice($error->insufficient, 'error');
                                    $order->add_order_note($note->insufficient->message .'<br>'.$note->insufficient->paid .' '. $delivered_amount['currency'] .' '. $delivered_amount['value'] .'<br>'. $note->insufficient->open .' '. $delivered_amount['currency'] .' '. $total-$delivered_amount['value'] .'<br>'. '<a href="https://bithomp.com/explorer/'.$txid.'">'. $note->insufficient->information .'</a>',true);
                                }
                                return false;
                            }

                            else return true;
                            break;

                        default:
                            wc_add_notice($error->amount, 'error');
                    }
                } else {
                    wc_add_notice($error->amount, 'error');
                    return false;
                }
            }

            function getReturnUrl($custom_identifier, $order, $self) {
                global $lang;
                $error = $lang->callback->error;
                $success = $lang->callback->note->success;

                $headers = array(
                    'Content-Type' => 'application/json',
                    'X-API-Key' => $self->api,
                    'X-API-Secret' => $self->api_secret
                );
                $payload = getPayloadXummById($custom_identifier, $headers);
                $txid = $payload['response']['txid'];
                $xr = $payload['custom_meta']['blob']['xr'];

                $txbody = getTransactionDetails($txid, $headers);
                if(empty($txbody)) {
                    wc_add_notice($error->payment, 'error' );
                    return $order->get_checkout_payment_url(false);
                }
                $delivered_amount = $txbody['transaction']['meta']['delivered_amount'];
                if(!checkDeliveredAmount($delivered_amount, $order, $xr, $self->issuers, $txid)) {
                    $redirect_url = $order->get_checkout_payment_url(false);
                    return $redirect_url;
                } else {
                    $order->payment_complete();
                    wc_reduce_stock_levels( $order->get_id() );
                    $order->add_order_note( $success->thanks . '<br>'. $success->check .'<a href="https://bithomp.com/explorer/'.$txid.'"> '.$success->href.'</a>', true );
                    WC()->cart->empty_cart();
                    return WC_Payment_Gateway::get_return_url( $order );
                }
            }

            if(!empty($_GET["order_id"])) {
                $status = $lang->callback->status;

                $custom_identifier = $_GET["order_id"];
                $order_id = explode("_", $custom_identifier)[0];
                $order = wc_get_order( $order_id );

                $order_status  = $order->get_status();
                switch ($order_status) {
                    case 'processing':
                        wc_add_notice($status->processing);
                        $redirect_url = $this->get_return_url( $order );
                        break;
                    case 'pending':
                        $redirect_url = getReturnUrl($custom_identifier, $order, $this);
                        break;
                    case 'on-hold':
                        wc_add_notice($status->on_hold);
                        $redirect_url = $order->get_checkout_payment_url(false);
                        break;
                    case 'completed':
                        wc_add_notice($status->completed, 'success');
                        $redirect_url = $this->get_return_url( $order );
                        break;
                    case 'cancelled':
                        wc_add_notice($status->cancelled, 'error' );
                        $redirect_url = $order->get_checkout_payment_url(false);
                        break;
                    case 'failed':
                        wc_add_notice($status->failed, 'error' );
                        $redirect_url = $order->get_checkout_payment_url(false);
                        break;
                    case 'refunded':
                        wc_add_notice($status->refunded, 'notice' );
                        $redirect_url = $this->get_return_url( $order );
                        break;
                    default:
                        wc_add_notice($status->default, 'error' );
                        wp_safe_redirect($order->get_checkout_payment_url(false));
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
                    $payload = getPayloadXummById($custom_identifier, $headers);
                    $txid = $payload['response']['txid'];
                    $xr = $payload['custom_meta']['blob']['xr'];
                    // we received the payment
                    $txbody = getTransactionDetails($txid, $headers);

                    $order_id = explode("_", $custom_identifier)[0];
                    $order = wc_get_order( $order_id );
                    $delivered_amount = $txbody['transaction']['meta']['delivered_amount'];
                    if(!checkDeliveredAmount($delivered_amount, $order, $xr, $this->issuers, $txid)) {
                        exit();
                    }

                    $order->payment_complete();
                    wc_reduce_stock_levels( $order_id );
                    
                    $success = $lang->callback->note->success;
                    //some notes to customer (replace true with false to make it private)
                    $order->add_order_note( $success->thanks . '<br>'. $success->check .'<a href="https://bithomp.com/explorer/'.$txid.'"> '.$success->href.'</a>', true );
            
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