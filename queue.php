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
    wp_redirect(home_url('/my-account/'));
    exit();
}

$instance = new Custom_Subscription();
$queue = $instance->get_queues(true);
$date = DateTime::createFromFormat('Y-m', $queue->year . '-' . $queue->month_id);

$queues = $instance->get_queues();

$sub = $instance->get_subscription();
$items = [];
if ($sub)
    $items = array_values($sub->get_items());
?>

<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
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
                                    <p class="mb-2"><?= _e('Subscription plan:') ?> <b><?= $instance->get_subscription_metas($queue)['price'] ?>$/Month</b></p>
                                    <p><?= _e('Subscription product:') ?> <b><?= $instance->get_subscription_metas($queue)['type'] ?></b></p>
                                <?php endif ?>
                                <?php if (!empty($queue) && $sub && $sub->get_status() == 'active') : ?>
                                    <a href="/unsubscribe-intend"><?= _e('Cancel Subscription') ?></a>
                                    <a href="/subscribe-intend?upgrade=yes"><?= _e('Upgrade/Downgrade') ?></a>
                                <?php elseif (!empty($queue) && $sub && $sub->get_status() == 'cancelled') : ?>
                                    <a href="/subscribe-intend"><?= _e('Re-subscription') ?></a>
                                    <a href="/subscribe-intend?upgrade=yes"><?= _e('Upgrade/Downgrade') ?></a>
                                <?php elseif (!empty($queue)) : ?>
                                    <a href="/subscribe-intend"><?= _e('Subscribe') ?></a>
                                    <a href="/subscribe-intend?upgrade=yes"><?= _e('Upgrade/Downgrade') ?></a>
                                <?php elseif (empty($queue)) : ?>
                                    <a href="/subscribe"><?= _e('Choose Package') ?></a>
                                <?php endif ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <?php if (!empty($items)) : ?>
                            <div id="delivered">
                                <?php
                                foreach ($queues as $key => $data) :
                                    $product = wc_get_product($data->product_id);
                                    if (isset($items[$key]) && !$items[$key]->get_meta('Delivered'))
                                        continue;
                                ?>
                                    <div class="single-sidebarproduct" data-id="<?= $data->id; ?>">
                                        <h3><?= $date->format('F - Y'); ?></h3>
                                        <div class="flexdiv">
                                            <a href="<?= get_permalink($data->product_id) ?>" target="_blank">
                                                <img src="<?= reset(wp_get_attachment_image_src(get_post_thumbnail_id($data->product_id), 'single-post-thumbnail')) ?>" data-id="<?= $data->product_id ?>">
                                                <div class="single-dt">
                                                    <h4><?= $product->get_name() ?></h4>
                                                    <span>Get details</span>
                                                    <p>Size: <?= wc_get_product($data->variation_id)->attributes['pa_size'] ?></p>
                                                </div>
                                            </a>

                                            <div class="controls-option">
                                                <i class="fa fa-check text-success" title="<?= _e('Delivered') ?>"></i>
                                            </div>
                                        </div>
                                    </div>

                                <?php
                                    $date->modify('+1 month');
                                endforeach
                                ?>
                            </div>
                        <?php endif ?>
                        <div id="sortable">
                            <?php
                            foreach ($queues as $key => $data) :
                                $product = wc_get_product($data->product_id);
                                if (isset($items[$key]) && $items[$key]->get_meta('Delivered'))
                                    continue;
                            ?>
                                <div class="single-sidebarproduct" data-id="<?= $data->id; ?>">
                                    <h3><?= $date->format('F - Y'); ?></h3>
                                    <div class="flexdiv">
                                        <a href="<?= get_permalink($data->product_id) ?>" target="_blank">
                                            <img src="<?= reset(wp_get_attachment_image_src(get_post_thumbnail_id($data->product_id), 'single-post-thumbnail')) ?>" data-id="<?= $data->product_id ?>">
                                            <div class="single-dt">
                                                <h4>
                                                    <?= $product->get_name() ?>
                                                </h4>
                                                <span>Get details</span>
                                                <h5>Size:
                                                    <?= wc_get_product($data->variation_id)->attributes['pa_size'] ?>
                                                </h5>
                                            </div>
                                        </a>

                                        <div class="controls-option">
                                            <div class="sideclose">
                                                <button class="delbtn" data-customer="<?= $data->customer_id; ?>" data-id="<?= $data->id; ?>">
                                                    <i class="fa fa-times" title="<?= _e('Remove') ?>"></i>
                                                </button>
                                            </div>
                                            <div class="bars">
                                                <a href="javascript:;">
                                                    <i class="fa fa-bars" title="<?= _e('Move') ?>"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            <?php
                                $date->modify('+1 month');
                            endforeach
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="//code.jquery.com/jquery-1.12.4.js"></script>
<script src="//code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
<script>
    $("#sortable").sortable({
        start: function(e, ui) {
            $(this).attr('data-previndex', ui.item.index());
        },
        update: function(e, ui) {
            var dataid = ui.item.data('id');
            var newIndex = ui.item.index();
            var oldIndex = $(this).attr('data-previndex');
            var element_id = ui.item.attr('id');
            var ajaxurl = "/wp-admin/admin-ajax.php";

            $.ajax({
                type: 'POST',
                url: ajaxurl,
                data: {
                    "action": "post_data_drag",
                    "postdataid": dataid,
                    "old_pos": oldIndex,
                    "new_pos": newIndex
                },
                success: function(response) {
                    location.reload();
                }
            });
        }
    });
    $("#sortable").disableSelection();

    jQuery('.delbtn').click(function() {
        var d = jQuery(this).data('id');
        var c = jQuery(this).data('customer');
        if (!confirm("Are you sure you’d like to remove this item"))
            return false;

        event.preventDefault();
        var ajaxurl = "/wp-admin/admin-ajax.php";
        jQuery.ajax({
            type: 'POST',
            url: ajaxurl,
            data: {
                "action": "post_data_del",
                "productid": d,
                "customerid": c
            },
            success: function(response) {
                location.reload();
            }
        });
    });
</script>

<?php get_footer(); ?>