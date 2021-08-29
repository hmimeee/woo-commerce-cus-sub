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

$sub->update_status('cancelled');

wp_redirect('/queue');
exit;
