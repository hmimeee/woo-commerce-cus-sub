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
        $date = $date->modify('+1 month');
}
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
                                    <p class="mb-2"><?= _e('Current month charge:') ?> <b><?= $price ?>$</b></p>
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
                        $catchYear = date('Y');
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
                    alert_box(res.data, 'success');
                    location.reload();
                }
            }
        });
    });

    $(".queue-sort").sortable({
        placeholder: "ui-state-highlight",
        connectWith: ".queue-sort",
        start: function(e, info) {
            info.item.siblings(".selected").appendTo(info.item);
            $prevPos = $(e.target);
            $has_item = $prevPos.find('.card').length;
            $prevPos.append('<div id="blank-prop" style="height:134px;border: 1px dotted gray"></div>');
        },
        beforeStop() {
            $('#blank-prop').remove();
        },
        over: function(e, info) {
            $hover = $(e.target);
            if ($hover.hasClass('queue-new')) {
                $hover.children().hide();
            } else {
                $('.queue-new').children().show();
            }
        },
        stop: function(e, info) {
            info.item.after(info.item.find(".single-sidebarproduct"));
            $prop = $(info.item[0]);
            $position = $prop.parent().data('position');
            $item = $prop.data('id');

            if ($prop.parent().children().length > 2) {
                $(".queue-sort").sortable("cancel");
                $(window).scrollTop($("body").offset().top);
                alert_box('Max item for the month has exceeded', 'warning');
                return false;
            }

            if ($prevPos.data('position') == $position)
                return false;

            $.ajax({
                type: 'POST',
                url: "/wp-admin/admin-ajax.php",
                data: {
                    "action": "post_data_drag",
                    "item": $item,
                    "position": $position,
                    "prev_position": $prevPos.data('position'),
                },
                success: function(res) {
                    if (res.status)
                        location.reload();

                    if (!res.status) {
                        $(".queue-sort").sortable("cancel");
                        $(window).scrollTop($("body").offset().top);
                        alert_box(res.message, 'danger');
                    }
                }
            });
        }
    });

    function alert_box(message, status) {
        $prop = $('#main .my-queue-section');
        $html = '<div class="alert alert-' + status + ' alert-dismissible fade show">';
        $html += message;
        $html += '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>';

        $prop.find('.alert').remove();
        $prop.prepend($html);
    }

    jQuery('.delbtn').click(function(e) {
        let id = jQuery(this).data('id');
        if (!confirm("Are you sure you’d like to remove this item"))
            return false;

        e.preventDefault();
        jQuery.ajax({
            type: 'POST',
            url: "/wp-admin/admin-ajax.php",
            data: {
                "action": "post_data_del",
                "item": id,
            },
            success: function(response) {
                if (response.status)
                    location.reload();
            }
        });
    });
</script>

<?php get_footer(); ?>