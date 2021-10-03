<?php

// define('WP_DEBUG', true);
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

get_header();
/*
Template Name: Subscription Queue Backup
*/

if (is_user_logged_in()) {
} else {
    wp_redirect(home_url('/my-account/'));
    exit();
}

$instance = new Custom_Subscription();
$queues = $instance->get_queues();
$queue = count($queues) ? reset($instance->get_queues()) : null;
$date = $queue ? DateTime::createFromFormat('Y-m', $queue->year . '-' . $queue->month_id) : null;

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
                                <?php elseif (!empty($queue) && $sub && ($sub->get_status() == 'cancelled' || $sub->get_status() == 'on-hold')) : ?>
                                    <a href="/subscribe-intend"><?= _e('Re-subscription') ?></a>
                                <?php elseif (!empty($queue)) : ?>
                                    <a href="/subscribe-intend"><?= _e('Subscribe') ?></a>
                                <?php elseif (empty($queue)) : ?>
                                    <a href="/subscribe"><?= _e('Choose Package') ?></a>
                                <?php endif ?>
                                <?php if (!empty($queue) && $sub) : ?>
                                    <a href="javascript:;" id="upgrade"><?= _e('Upgrade/Downgrade') ?></a>
                                <?php endif ?>
                                <div class="form-group">
                                </div>
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
                        <div class="sortable">
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
                        <h1>Gaap</h1>
                        <div class="sortable">
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
    $('#upgrade').click(function() {
        $btn = $(this);
        $btn.append(' <i class="fa fa-circle-notch fa-spin"></i>');
        $.ajax({
            url: '/wp-admin/admin-ajax.php',
            type: 'post',
            data: {
                'action': 'upgrade_subscription',
                '_wpnonce': '<?= wp_create_nonce() ?>'
            },
            success: function(res) {
                if (res.success) {
                    $btn.hide();
                    $select = '<select id="variation" style="padding:10px 90px;">';
                    for (let i = 0; i < res.data.length; i++) {
                        const variant = res.data[i];
                        $select += '<option value="' + variant.size + '" ' + variant.selected + '>' + variant.size + ' (' + variant.price + '$)</option>';
                    }
                    $select += '</select>';

                    if (res.data.length) {
                        $btn.next('div.form-group').html($select);
                        $btn.next('div.form-group').append('<button id="confirm-upgrade" class="m-2">Confirm</button>');
                        $btn.next('div.form-group').append('<button id="cancel-upgrade" class="btn-secondary active">Cancel</button>');
                        $btn.next('div.form-group').append('<button id="hard-upgrade">Suspend Subscription</button>');
                        $btn.next('div.form-group').append('<p class="text-left"><b>Note:</b> Suspending subscription will clear the queue and you have to prepare the queue again for new subscription.');
                    }
                }
            }
        })
    });

    $('body').on('click', '#cancel-upgrade', function() {
        $can = $(this);
        $upgrade = $can.parents().find('#upgrade');
        $upgrade.html('Upgrade/Downgrade');
        $upgrade.show();
        $can.parent().html('');
    });

    $('body').on('click', '#hard-upgrade', function(e) {
        e.preventDefault();
        location.href = '/unsubscribe-intend?suspend=yes'
    })

    $('body').on('click', '#confirm-upgrade', function() {
        $btn = $(this);
        $btn.addClass('w-75');
        $('#cancel-upgrade').remove();
        $btn.html('Updating <i class="fa fa-cog fa-spin"></i>');
        $.ajax({
            url: '/wp-admin/admin-ajax.php',
            type: 'post',
            data: {
                'action': 'upgrade_subscription_confirm',
                '_wpnonce': '<?= wp_create_nonce() ?>',
                'size': $('body').find('#variation').val(),
            },
            success: function(res) {
                if (res.success) {
                    $btn.parent().prepend('<p class="alert alert-success">' + res.data + '</a>');
                    location.reload();
                }
            }
        });
    });

    // $("#sortable").sortable({
    //     start: function(e, ui) {
    //         $(this).attr('data-previndex', ui.item.index());
    //     },
    //     update: function(e, ui) {
    //         var dataid = ui.item.data('id');
    //         var newIndex = ui.item.index();
    //         var oldIndex = $(this).attr('data-previndex');
    //         var element_id = ui.item.attr('id');
    //         var ajaxurl = "/wp-admin/admin-ajax.php";

    //         $.ajax({
    //             type: 'POST',
    //             url: ajaxurl,
    //             data: {
    //                 "action": "post_data_drag",
    //                 "postdataid": dataid,
    //                 "old_pos": oldIndex,
    //                 "new_pos": newIndex
    //             },
    //             success: function(res) {
    //                 if (res.status)
    //                     location.reload();
    //             }
    //         });
    //     }
    // });
    $(".sortable").sortable({
        connectWith: ".sortable",
        start: function(e, info) {
            info.item.siblings(".selected").appendTo(info.item);
        },
        stop: function(e, info) {
            info.item.after(info.item.find(".single-sidebarproduct"))
        }
    });

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
                if (response.status)
                    location.reload();
            }
        });
    });
</script>

<?php get_footer(); ?>