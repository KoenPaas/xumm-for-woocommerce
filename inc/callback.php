<?php

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    $uuid = $data['payloadResponse']['payload_uuidv4'];

    if($uuid != null) {
        $custom_identifier = $data['custom_meta']['identifier'];
        if ($custom_identifier != null) {
            $payload = getPayloadXummById($custom_identifier, $headers);
            $txid = $payload['response']['txid'];
            $xr = $payload['custom_meta']['blob']['xr'];

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
            //A notes to the customer (replace true with false to make it private)
            $order->add_order_note( $success->thanks . '<br>'. $success->check .'<a href="https://bithomp.com/explorer/'.$txid.'"> '.$success->href.'</a>', true );
    
            WC()->cart->empty_cart();

        }
    }
?>