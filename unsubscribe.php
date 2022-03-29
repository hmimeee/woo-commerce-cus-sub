<?php
/*
Template Name: Unsubscribe Intend
*/

require_once plugin_dir_path(__FILE__) . 'class-custom-subscription.php';


$instance = new Custom_Subscription;
$sub = $instance->get_subscription();

if (!$sub) {
    wp_redirect('/queue');
    exit;
}

if (isset($_GET['suspend'])) {
    $sub->update_status('cancelled');
    $instance->empty_queue();
    wp_redirect('/queue');
    exit;
}

$sub->update_status('on-hold');
$sub->add_order_note('Order paused by the user');

wp_redirect('/queue');
exit;
