<?php
/*
Template Name: Subscribe Intend
*/

require_once plugin_dir_path(__FILE__) . 'class-custom-subscription.php';

$instance = new Custom_Subscription;
$sub = $instance->get_subscription();

if ($sub && isset($_GET['upgrade'])) {
    //Remove subscription items
    $sub->remove_order_items('line_item');
    $instance->empty_queue();

    //Redirect to the payment page
    wp_redirect("/subscribe");
    exit;
} elseif ($sub) {

    // $stripe = new WC_Stripe_Order_Handler;
    // $stripe->scheduled_subscription_payment($sub->get_total(), $sub);
    $instance->update_subscription('active');

    //Redirect to the payment page
    wp_redirect("/queue");
    exit;
}

$details = $instance->order_details();

if (!$details->billing_address['billing_first_name'] || !$details->billing_address['billing_country'] || !$details->billing_address['billing_state']) {
    wp_redirect('/my-account/edit-address/billing/');
    exit;
}

if (!$details->shipping_address['shipping_first_name'] || !$details->shipping_address['shipping_country'] || !$details->shipping_address['shipping_state']) {
    wp_redirect('/my-account/edit-address/shipping/');
    exit;
}

//Queue first data
$queue = $instance->get_queues(true);
$queues = $instance->get_queues($queue->position, 'position');

//Check if queue is empty
if (!$queue) {
    wp_redirect('/queue');
    exit;
}

//Create an order
$data = array_merge($details->data, $details->billing_address, $details->shipping_address);
WC()->cart->empty_cart();
$checkout = new WC_Checkout();
$order_id = $checkout->create_order($data);

//Check if order created
if (!$order_id)
    wp_redirect('queue');

foreach ($queues as $key => $queue) {
    //Get the order object
    $order = wc_get_order($order_id);

    //Get the first product object
    $product = wc_get_product($queue->product_id);

    // //get the amount
    $variation = new WC_Product_Variation($queue->variation_id);
    $amount = $variation->price;

    //Add product to the order
    $item = $order->add_product($product, 1, ['total' => $amount]);
    wc_add_order_item_meta($item, 'Size', $variation->attributes['pa_size']);
    wc_add_order_item_meta($item, 'Deliverable Date', date('F Y'), true);
}

//Calculate the amounts
$order->calculate_totals();
$order->save();

// Store Order ID in session so it can be re-used after payment failure
WC()->session->subscription_order = $order->id;

//Redirect to the payment page
wp_redirect("/checkout/order-pay/$order_id/?pay_for_order=true&key=$order->order_key");
exit;
