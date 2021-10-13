<?php

// define('WP_DEBUG', true);
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

get_header();
/*
Template Name: Subscription Queue
*/

if (is_user_logged_in()) {
} else {
    wp_redirect(home_url('/my-account'));
    exit();
}

$instance = new Custom_Subscription();
$queues = $instance->get_queues();
$queue = count($queues) ? reset($queues) : null;
$type = $instance->get_subscription_metas($queue)['type'];

if ($queue) {
    $has_products = count($instance->get_queues($queue->position, 'position'));
    $variation = new WC_Product_Variation($queue->variation_id);
    $price = $has_products * $variation->price;
}

$start = new DateTime();
$data = [];
$prev_pos = 1;

foreach ($queues as $dt) {
    if ($prev_pos != $dt->position) {
        $prev_pos = $dt->position;
        $start->modify('+1 month');
    }

    $data[$start->format('Y')][$dt->position][] = $dt;
}
$queues = $data;

$sub = $instance->get_subscription();
$items = [];

$last_order = null;
$date = new DateTime();
if ($sub) {
    $items = array_values($sub->get_items());

    $formatted_items = [];
    foreach ($items as $itm) {
        $dateTime = DateTime::createFromFormat('F Y', $itm->get_meta('Deliverable Date'));
        if (!$itm->get_meta('Delivered'))
            $formatted_items[$dateTime->format('Y')][$dateTime->format('m')][] = $itm;
    }

    $related_orders = wc_get_orders([
        'parent' => $sub->get_id()
    ]);

    $last_order = reset($related_orders) ? reset($related_orders) : $sub->get_parent();
    $order_items = $last_order->get_items();
    $order_delivery = reset($order_items)->get_meta('Deliverable Date');
    if ($order_delivery == date('F Y'))
        $date->modify('+1 month');

    $last_product = reset($last_order->get_items())->get_product();
    if (has_term('luxury', 'product_cat', $last_product->get_id()))
        $type = 'Luxury';
}
?>

<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
<link rel="stylesheet" href="<?= get_template_directory_uri() ?>-child/style.css">
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div class="my-queue-section">
                <div class="row">
                    <div class="col-md-6">
                        <div class="sidebar-content">
                            <div class="welcome-content">
                                <img width="150" src="<?= $porto_settings['logo']['url'] ?>" alt="Logo">
                                <h4><?= _e('Welcome') ?></h4>
                                <h3><?= _e('Get your favorite scents in sequentially') ?></h3>
                                <?php if (!empty($queue)) : ?>
                                    <p class="mb-2"><?= _e('Next month charge:') ?> <b><?= $price ?>$</b></p>
                                    <p><?= _e('Subscription product:') ?> <b><?= $type ?></b></p>
                                <?php endif ?>
                                <?php if (!empty($queue) && $sub && $sub->get_status() == 'active') : ?>
                                    <a href="/unsubscribe-intend" class="btn btn-primary btn-sm"><?= _e('Cancel Subscription') ?></a>
                                <?php elseif (!empty($queue) && $sub && $sub->get_status() == 'on-hold') : ?>
                                    <a href="/subscribe-intend" class="btn btn-primary btn-sm"><?= _e('Re-subscription') ?></a>
                                <?php elseif (!empty($queue)) : ?>
                                    <a href="/subscribe-intend" class="btn btn-primary"><?= _e('Subscribe') ?></a>
                                <?php elseif (empty($queue) && !$sub) : ?>
                                    <a href="/subscribe" class="btn btn-primary btn-sm"><?= _e('Choose Package') ?></a>
                                <?php elseif ($sub) : ?>
                                    <a href="/product-category/subscription/<?= strtolower($type) ?>" class="btn btn-primary btn-sm"><?= _e('Choose Products') ?></a>
                                <?php endif ?>
                                <?php if (!empty($queue) && $sub) : ?>
                                    <a href="javascript:;" class="btn btn-primary btn-sm" id="upgrade"><?= _e('Upgrade/Downgrade') ?></a>
                                <?php endif ?>
                                <div class="form-group">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <?php
                        if ($last_order) :
                            $delivered_items = $last_order->get_items();
                            $ddate = $last_order->date_created;

                            if (date('Y') != $ddate->format('Y'))
                                print('<h4 class="text-center">' . $year . '</h4>');
                        ?>
                            <h5><?= $ddate->format('F') ?></h5>
                            <?php
                            foreach ($delivered_items as $ditem) :
                                $product = $ditem->get_product();
                                $images = wp_get_attachment_image_src(get_post_thumbnail_id($product->get_id()));
                            ?>
                                <div class="card mb-3 shadow-sm">
                                    <div class="row no-gutters">
                                        <div class="col-md-3">
                                            <img src="<?= reset($images) ?>" class="card-img w-75 mx-auto mt-2" alt="<?= $product->get_name() ?>">
                                        </div>
                                        <div class="col-md-8">
                                            <div class="card-body">
                                                <h5 class="card-title"><?= $product->get_name() ?></h5>
                                                <p class="card-text">
                                                    <span class="text-muted">Size: <?= $ditem->get_meta('Size') ?></span>
                                                    <br />
                                                    Delivery status: <span class="badge badge-info"><?= ucfirst($last_order->status) ?></span>
                                                    <br />
                                                    <a href="<?= get_permalink($product->get_id()) ?>" target="_blank">Get details</a>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                        <?php
                            endforeach;
                        endif;
                        ?>

                        <?php
                        $catchYear = $date->format('Y');
                        foreach ($queues as $year => $monthBunch) : ?>
                            <?php if ($catchYear != $year) {
                                $catchYear = $year;
                                print('<h4 class="text-center">' . $year . '</h4>');
                            } ?>
                            <?php foreach ($monthBunch as $position => $bunch) : ?>
                                <h5><?= $date->format('F') ?></h5>
                                <div class="queue-sort" style="min-height: 118px;" data-position="<?= $position ?>">
                                    <?php
                                    foreach ($bunch as $key => $data) :
                                        $product = wc_get_product($data->product_id);
                                        $images = wp_get_attachment_image_src(get_post_thumbnail_id($data->product_id));
                                    ?>
                                        <div class="card mb-3 shadow-sm" style="cursor: move" data-id="<?= $data->id ?>">
                                            <div class="row no-gutters">
                                                <div class="col-md-3">
                                                    <img src="<?= reset($images) ?>" class="card-img w-75 mx-auto mt-2" alt="<?= $product->get_name() ?>">
                                                </div>
                                                <div class="col-md-8">
                                                    <div class="card-body">
                                                        <h5 class="card-title"><?= $product->get_name() ?></h5>
                                                        <p class="card-text">
                                                            <span class="text-muted">Size: <?= wc_get_product($data->variation_id)->attributes['pa_size'] ?></span>
                                                            <br />
                                                            <a href="<?= get_permalink($data->product_id) ?>" target="_blank">Get details</a>
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="col-md-1">
                                                    <a href="javascript:;" class="delbtn float-right pt-2 pr-3" data-id="<?= $data->id; ?>">
                                                        <i class="fa fa-times d-none d-md-block" title="<?= _e('Remove') ?>"></i>
                                                    </a>

                                                    <button class="delbtn btn btn-danger btn-block d-block d-md-none" data-id="<?= $data->id; ?>">Remove</button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php
                                    endforeach
                                    ?>
                                </div>
                            <?php
                                $date->modify('+1 month');
                            endforeach;
                        endforeach;

                        if ($date->format('Y') != $year) :
                            ?>
                            <h4 class="text-center"><?= $date->format('Y') ?></h4>
                        <?php endif ?>
                        <h5><?= $date->format('F') ?></h5>
                        <div class="queue-sort queue-new" style="min-height: 118px;" data-position="<?= $position + 1 ?>">
                            <div class="card mb-3 shadow-sm ui-sortable-handle" style="cursor: copy" data-id="135">
                                <div class="row no-gutters">
                                    <div class="col-md-3">
                                        <img src="https://static.thenounproject.com/png/94729-200.png" class="card-img w-75 mx-auto mt-2" alt="Miss Dior Absolutely Blooming">
                                    </div>
                                    <div class="col-md-8">
                                        <div class="card-body">
                                            <h5 class="card-title">Add new perfume</h5>
                                            <p class="card-text">
                                                <span class="text-muted">Click on the browse link and add new products</span>
                                                <br>
                                                <a href="/shop" target="_blank">Browse shop</a>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="//code.jquery.com/jquery-1.12.4.js"></script>
<script src="//code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
<script src="/wp-content/themes/porto-child/script.js?v=1.2"></script>

<?php get_footer(); ?>