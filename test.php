<?php
/*
Template Name: Test
*/
define('WP_DEBUG', true);

error_reporting(E_ALL);
ini_set('display_errors', 1);

// echo '<pre>';
$data = array(
    'terms' => 0,
    'createaccount' => 0,
    'payment_method' => 'stripe',
    'shipping_method' => array(
        'free_shipping:1'
    ),
    'ship_to_different_address' => '',
    'woocommerce_checkout_update_totals' => '',
    'order_comments' => '',
    'billing_first_name' => 'Hossain Mohammad',
    'billing_last_name' => 'Imran',
    'billing_company' => 'HMI',
    'billing_country' => 'BD',
    'billing_address_1' => 'test address',
    'billing_address_2' => '',
    'billing_city' => 'Dhaka',
    'billing_state' => 'BD-13',
    'billing_postcode' => '',
    'billing_phone' => '1313',
    'billing_email' => 'admin@scent.viserx.net',
    'shipping_first_name' => 'Test',
    'shipping_last_name' => 'test',
    'shipping_company' => 'test',
    'shipping_country' => 'BD',
    'shipping_address_1' => 'test',
    'shipping_address_2' => '',
    'shipping_city' => 'test',
    'shipping_state' => 'BD-13',
    'shipping_postcode' => ''
);

echo "<pre>";
print_r($data);
die;

global $wpdb;
$table = 'woocommerce_queue_data';
$user = wp_get_current_user();
$query = "SELECT * FROM $table
WHERE  `customer_id` = $user->ID
AND `status` = 'Active'
ORDER BY year ASC, month_id ASC";
$queues = $wpdb->get_results($query);

$checkout = new WC_Checkout();
$order = wc_get_order($checkout->create_order($data));
// $order = wc_get_order(10997);
$order->set_total(9.99);
$order->save();

print_r($order);
die;

$sub = wcs_create_subscription(array(
    'order_id' => $order->get_id(),
    'status' => 'pending', // Status should be initially set to pending to match how normal checkout process goes
    'billing_period' => 'month',
    'billing_interval' => 1
));
// $sub = wcs_get_subscription(10998);

$date = new DateTime();
$date->modify('first day of this month');

foreach ($queues as $queue) {
    $date->modify('+1 month');
    $product = wc_get_product($queue->product_id);
    $item = $sub->add_product($product);
    wc_add_order_item_meta($item, 'Deliverable Month', $date->format('F Y'), true);
}

print_r($sub);
die;

// $process = WC_Subscriptions_Manager::process_subscription_payments_on_order($order);

// print_r(WC_Subscriptions_Order::get_next_payment_date($order));
$stripe = new WC_Gateway_Stripe();
$stripe->payment_scripts();
$stripe->elements_form();
// print_r($stripe->process_payment($order->get_id()));
die;

$stripe = new WC_Gateway_Stripe();
print_r($stripe->payment_scripts());
$stripe->elements_form();

$order = new Order();
print_r($stripe->process_payment(10977));
die;

$res = $stripe->request('https://api.stripe.com/v1/charges', [
    'method' => 'POST',
    'body' => array(
        'name' => 'Test',
    ),
]);

print_r(wp_remote_retrieve_body($res));
die;
