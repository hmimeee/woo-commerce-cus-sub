<?php
/*
Template Name: Subscribe Intend
*/

require_once plugin_dir_path(__FILE__) . 'class-custom-subscription.php';

$custom = new Custom_Subscription;
$details = $custom->order_details();

//Queue first data
$queue = $custom->get_queues(true);

//Check if queue is empty
if (!$queue)
    wp_redirect('queue');

//Create an order
$data = array_merge($details->data, $details->billing_address, $details->shipping_address);
$checkout = new WC_Checkout();
$order_id = $checkout->create_order($data);
update_post_meta($order_id, '_customer_user', $custom->user_id);

//Check if order created
if (!$order_id)
    wp_redirect('queue');

//Get the order object
$order = wc_get_order($order_id);

//Get the first product object
$product = wc_get_product($queue->product_id);

//get the amount
$amount = $custom->get_amount($queue->product_id, $queue->variation_id);

//Add product to the order
$item = $order->add_product($product, 1, ['total' => $amount]);
wc_add_order_item_meta($item, 'Deliverable Date', $details->date->format('F Y'), true);

//Calculate the amounts
$order->calculate_totals();
$order->save();

// Store Order ID in session so it can be re-used after payment failure
WC()->session->subscription_order = $order->id;

//Redirect to the payment page
wp_redirect("/checkout/order-pay/$order_id/?pay_for_order=true&key=$order->order_key");
exit;
