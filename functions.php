<?php
// define('WP_DEBUG', true);
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// echo "<pre>";

require_once plugin_dir_path(__FILE__) . 'class-custom-subscription.php';
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

/**
 * Dump and die
 * 
 * @param mixed $data
 * @return never
 */
function dd($data, $style = true)
{
    if (!$style) {
        echo  '<pre>';
        print_r($data);
        die;
    }
    echo  '<pre style="background: #111; color: #3cb53c;">';
    print_r($data);
    die;
}

add_action('wp_enqueue_scripts', 'porto_child_css', 10);

// Load CSS
function porto_child_css()
{
    // porto child theme styles
    wp_deregister_style('styles-child');
    wp_register_style('styles-child', esc_url(get_stylesheet_directory_uri()) . '/style.css');
    wp_enqueue_style('styles-child');

    if (is_rtl()) {
        wp_deregister_style('styles-child-rtl');
        wp_register_style('styles-child-rtl', esc_url(get_stylesheet_directory_uri()) . '/style_rtl.css');
        wp_enqueue_style('styles-child-rtl');
    }
}

function porto_child_additional_info_based_on_type()
{
    echo '<div class="notice" style="border-bottom: 1px solid #e7e7e7;padding-bottom:1.25rem;margin-bottom:1rem;display:none;">';
?>
    <p id="notice" style="margin-bottom:0px;font-weight:700;"></p>
    <script>
        jQuery("#type").change(function() {
            var selectType = jQuery("#type").val();
            if (selectType == 'One Time Purchase') {
                document.getElementById('notice').innerHTML = 'You Will Get What You Select For One Time Only';
                jQuery('.notice').show();
                jQuery('.single_add_to_cart_button').show();
                jQuery('.add_to_queue').hide();
            } else if (selectType == 'Subscription') {
                document.getElementById('notice').innerHTML = 'Save Up To 35% & Choose what you\'d like to try from our Products / you\'ll get access to our full catalog after subscribing / Cancel Anytime';
                jQuery('.notice').show();
                jQuery('.single_add_to_cart_button').hide();
                jQuery('.add_to_queue').show();
            } else {
                jQuery('.notice').hide();
            }
        });
        jQuery("#types").change(function() {
            var selectType = jQuery("#types").val();
            if (selectType == 'One Time Purchase') {
                document.getElementById('notice').innerHTML = 'You Will Get What You Select For One Time Only';
                jQuery('.notice').show();
                jQuery('.single_add_to_cart_button').show();
                jQuery('.add_to_queue').hide();
            } else if (selectType == 'Subscription') {
                document.getElementById('notice').innerHTML = 'Save Up To 35% & Choose what you\'d like to try from our Products / you\'ll get access to our full catalog after subscribing / Cancel Anytime';
                jQuery('.notice').show();
                jQuery('.single_add_to_cart_button').hide();
                jQuery('.add_to_queue').show();
            } else {
                jQuery('.notice').hide();
            }
        });

        jQuery(".add_to_queue").click(function() {
            jQuery('.alert').remove();
            var product_id = jQuery('input[name="product_id"]').val();
            var variation_id = jQuery('input[name="variation_id"]').val();
            if (variation_id.length != 0) {

                var ajaxurl = "/wp-admin/admin-ajax.php";
                jQuery.ajax({
                    type: 'POST',
                    url: ajaxurl,
                    data: {
                        "action": "post_word_count",
                        "productid": product_id,
                        "variationid": variation_id
                    },

                    success: function(response) {
                        if (response.status) {
                            jQuery('.add_to_queue').hide();
                            jQuery('.queueaddedshow').html('<button class="added-queue button alt wc-variation-selection-needed" disabled>Added Queue</button> <a href="/queue/" class="view-queues added-queue button alt disabled wc-variation-selection-needed">View Queue</a>');
                        } else {
                            jQuery('.alert').remove();
                            jQuery('div.notice').after('<p class="alert alert-warning">' + response.message + '</p>');
                        }
                    }
                });
            } else {
                alert("Size Field is Required");
            }

        });
    </script>
<?php
    echo '</div>';
}
add_action('woocommerce_single_variation', 'porto_child_additional_info_based_on_type');

add_action('woocommerce_after_account_navigation', 'my_subscription_link_replace');
function my_subscription_link_replace()
{
?>
    <script>
        jQuery('.woocommerce-MyAccount-navigation-link--subscriptions a').attr('href', '/queue');
    </script>
<?php
}

function add_to_queue_btn()
{
    echo '<span class="button add_to_queue button alt disabled wc-variation-selection-needed">Add to Queue</span>';
}
add_action('woocommerce_after_add_to_cart_button', 'add_to_queue_btn');

function added_queue_div()
{
    echo '<div class="queueaddedshow"></div>';
}
add_action('woocommerce_after_add_to_cart_button', 'added_queue_div');


// add to queue submission
function add_to_queue()
{
    if (is_user_logged_in()) {

        $product_id = $_POST['productid'];
        $variation_id = $_POST['variationid'];

        //Select the package
        if (has_term('luxury', 'product_cat', $product_id)) {
            $package = 'luxury';
        } else {
            $package = 'regular';
        }

        $instance = new Custom_Subscription();
        $queue = end($instance->get_queues());

        if (has_term('luxury', 'product_cat', $queue->product_id)) {
            $has_package = 'luxury';
        } else {
            $has_package = 'regular';
        }

        if ($queue && $has_package != $package) {
            return wp_send_json([
                'status' => false,
                'message' => 'Your subscription is for <a href="/product-category/subscription/' . $has_package . '"><b>' . ucfirst($has_package) . '</b></a> products only'
            ]);
        }

        $has_variation = new WC_Product_Variation($queue->variation_id);
        $new_variation = new WC_Product_Variation($variation_id);

        if ($queue && $has_variation->price != $new_variation->price)
            return wp_send_json([
                'status' => false,
                'message' => 'Your subscription variation is <b>' . ucfirst($has_variation->attributes['pa_size']) . '</b></a> only'
            ]);

        if ($instance->add_to_queue($product_id, $variation_id)) {
            $sub = $instance->get_subscription();

            if ($sub) {
                $item_with_price = array_filter(array_map(function ($q) {
                    if ($q->get_total() == '0')
                        return null;

                    return $q;
                }, $sub->get_items()));

                $product = wc_get_product($product_id);
                $item = $sub->add_product($product, 1, [
                    'total' => $item_with_price ? 0 : $new_variation->price
                ]);

                //Add item meta to track the month
                wc_add_order_item_meta($item, 'Size', $has_variation->attributes['pa_size']);
                wc_add_order_item_meta($item, 'Deliverable Date', $date->format('F Y'), true);
                $sub->calculate_totals();

                //Update the subscription date
                $sub->update_dates(array('end' => $date->format('Y-m-d H:i:s')));
            }

            return wp_send_json([
                'status' => true,
                'message' => 'Your product has been added to the queue'
            ]);
        }

        return wp_send_json([
            'status' => false,
            'message' => 'Something went wrong, please contact support'
        ]);
    } else {
        return wp_send_json([
            'status' => false,
            'message' => 'Your must login first'
        ]);
    }
}

add_action('wp_ajax_post_word_count', 'add_to_queue');
add_action('wp_ajax_nopriv_post_word_count', 'add_to_queue');

// add to queue submission
function post_data_del()
{
    $item_id = $_POST['item'];
    if (!$item_id)
        return false;

    $instance = new Custom_Subscription;
    $queues = $instance->get_queues();
    $item = $instance->get_queues($item_id, 'row');
    $has_item = count($instance->get_queues($item->position, 'position'));
    $had_position = $item->position;

    if ($has_item >= 2)
        $instance->delete_data($item_id);

    if ($has_item < 2) {
        foreach ($queues as $data) {
            if ($item == $data)
                continue;

            $position = $data->position;
            if ($had_position < $position) {
                $instance->update_data($data->id, [
                    'position' => $position - 1
                ]);
            }
        }
    }

    // //Queue re-arrange
    // global $wpdb;
    // $table = 'woocommerce_queue_data'; //Get the table name
    // $query = ''; //Balnk variable for the query
    // $deletable = null;
    // $date = new DateTime();
    // $from_date = $date;
    // foreach ($queues as $data) {
    //     if ($item_id == $data->id) {
    //         $deletable = $data;
    //         $from_date = DateTime::createFromFormat('Y-n', $data->year . '-' . $data->month_id);
    //     } elseif ($deletable) {
    //         //Update queue data placing
    //         $query .= "UPDATE " . $table . " SET month_id='" . $date->format('n') . "', year='" . $date->format('Y') . "' WHERE id = $data->id;";
    //         $date->modify('+1 month');
    //     }
    // }

    // //Get the subscription data
    // $sub = $instance->get_subscription();

    // if (!$sub) {
    //     $wpdb->delete(
    //         $table,
    //         array(
    //             'id' => $deletable->id
    //         ),
    //         array(
    //             '%d'
    //         )
    //     );
    // } else {
    //     $items = $sub->get_items();
    //     $has_delivered = $instance->get_delivered_items();
    //     $items_to_change = array_diff($items, $has_delivered);
    //     $queue = $instance->get_queues(true);
    //     $variation = new WC_Product_Variation($queue->variation_id);

    //     $date = count($items_to_change) ? DateTime::createFromFormat('F Y', reset($items_to_change)->get_meta('Deliverable Date')) : new DateTime();
    //     foreach ($items_to_change as $item) {
    //         $delivery = DateTime::createFromFormat('Y-n', $deletable->year . '-' . $deletable->month_id);
    //         $product_id = intval($deletable->product_id);

    //         if ($item->get_product()->get_id() == $product_id && $item->get_meta('Deliverable Date') == $delivery->format('F Y')) {

    //             $wpdb->delete(
    //                 $table,
    //                 array(
    //                     'id' => $deletable->id
    //                 ),
    //                 array(
    //                     '%d'
    //                 )
    //             );

    //             wc_delete_order_item($item->get_id());
    //             continue;
    //         }

    //         wc_add_order_item_meta($item, 'Size', $variation->attributes['pa_size']);
    //         $item->update_meta_data('Deliverable Date', $date->format('F Y'));

    //         if (end($items_to_change) != $item)
    //             $date->modify('+1 month');
    //     }

    //     if (!empty($items_to_change)) {
    //         //Calculate the amounts
    //         $sub->calculate_totals();

    //         //Update the subscription date
    //         $sub->update_dates(array('end' => $date->modify('last day of this month')->format('Y-m-d H:i:s')));
    //     }
    // }

    // //Update the queue
    // dbDelta($query);

    return wp_send_json([
        'status' => true,
        'message' => 'Queue data deleted successfully'
    ]);
}

add_action('wp_ajax_post_data_del', 'post_data_del');
add_action('wp_ajax_nopriv_post_data_del', 'post_data_del');


//dragable effect for queue change
function post_data_drag()
{
    $instance = new Custom_Subscription();

    //get the required data from the database
    $queue_row = $instance->get_queues($_POST['item'], 'row');
    $queues = $instance->get_queues();
    $prev_position = $_POST['prev_position'];
    $had_items = count($instance->get_queues($prev_position, 'position'));
    $new_position = $_POST['position'];
    $has_items = count($instance->get_queues($new_position, 'position'));

    if ($has_items >= 2)
        return wp_send_json([
            'status' => false,
            'message' => 'Max item for the month exceeded'
        ]);

    if ($had_items >= 2 || end($queues) == $queue_row)
        $instance->update_data($queue_row->id, array(
            'position' => $new_position
        ));

    if ($had_items < 2 && end($queues) != $queue_row) {
        $instance->update_data($queue_row->id, array(
            'position' => $new_position
        ));

        foreach ($queues as $data) {
            if ($queue_row == $data)
                continue;

            $position = $data->position;
            if ($prev_position > $new_position) {

                if ($position >= $new_position && $position < $prev_position) {
                    $instance->update_data($data->id, [
                        'position' => $position + 1
                    ]);
                }
            }

            if ($prev_position < $new_position) {

                if ($position <= $new_position && $position > $prev_position) {
                    $instance->update_data($data->id, [
                        'position' => $position - 1
                    ]);
                }
            }
        }
    }

    //Return the response
    return wp_send_json([
        'status' => true,
        'message' => 'Queue updated successfully'
    ]);
}

//Register the actions for ajax request
add_action('wp_ajax_post_data_drag', 'post_data_drag');
add_action('wp_ajax_nopriv_post_data_drag', 'post_data_drag');


/**
 * Create subscription for the order
 */
add_action('woocommerce_before_thankyou', 'has_custom_subscription_for_order');
function has_custom_subscription_for_order()
{
    $order = wc_get_order(WC()->session->get('subscription_order'));
    WC()->session->subscription_order = null;

    if (!$order)
        return false;

    $custom = new Custom_Subscription;
    $sub = $custom->create_subscription($order->id);

    //Redirect to the payment page
    if ($sub) {
        $sub = wcs_get_subscription($sub->get_id());

        wp_redirect("/queue");
        exit;
    }
}

/**
 * Set payment gateway only stripe credit card for subscription
 */
add_filter('woocommerce_available_payment_gateways', 'payment_gateways_based_on_subscription');
function payment_gateways_based_on_subscription($available_gateways)
{
    // Not in backend (admin panel)
    if (is_admin())
        return $available_gateways;

    $order = wc_get_order(WC()->session->get('subscription_order'));

    if ($order) {
        unset($available_gateways['paypal']);
        unset($available_gateways['cod']);
    }

    return $available_gateways;
}

add_action('woocommerce_admin_order_item_values', 'hide_monthly_queue');
function hide_monthly_queue()
{
    echo "
    <script>jQuery('.woocommerce-order-details__title').parent().remove();</script>
    ";
}

add_action('add_meta_boxes',  'item_delivery_status');
function item_delivery_status()
{
    add_meta_box(
        'wc_subscription_item_delivery', // Unique ID
        'Item Delivery Status Update', // Box title
        'wc_subscription_item_delivery_html', // Content callback, must be of type callable
        'shop_subscription', // Post type
    );
}

function wc_subscription_item_delivery_html()
{
    $sub = wcs_get_subscription(get_the_ID());
    if ($sub && count($sub->get_items()))
        $items = [];

    $items = $sub->get_items();
?>
    <select id="item_id" class="postbox">
        <option value="">Select item</option>
        <?php foreach ($sub->get_items() as $key => $item) : ?>
            <?php
            if ($item->get_meta('Delivered')) continue;
            if (!$item->get_product()) continue;
            ?>
            <option value='<?= $item->get_id() ?>'><?= $item->get_product()->name ?> (<?= $item->get_meta('Deliverable Date') ?>)</option>
        <?php endforeach ?>
    </select>
    <a href="javascript:;" id="deliver_item" class="button">Set Delivered</a>

    <script>
        jQuery('#deliver_item').click(function(e) {
            e.preventDefault();

            jQuery.ajax({
                url: '<?= admin_url('admin-ajax.php?action=deliver_item') ?>',
                data: {
                    'item': jQuery('#item_id').val(),
                    '_wpnonce': '<?= wp_create_nonce() ?>',
                    'subscription': '<?= $sub->get_id() ?>'
                },
                type: 'POST',
                success: function(res) {
                    if (res.status)
                        location.reload();
                }
            })
        })
    </script>
<?php
}

add_action("wp_ajax_deliver_item", "deliver_item");
add_action("wp_ajax_nopriv_deliver_item", "deliver_item");

function deliver_item()
{
    $sub = wcs_get_subscription($_POST['subscription']);
    $item = wcs_get_order_item($_POST['item'], $sub);
    $has_delivered = $item->get_meta('Delivered');

    if ($has_delivered)
        //Return the response
        return wp_send_json([
            'status' => false,
            'message' => 'Item already delivered'
        ]);

    wc_add_order_item_meta($item->get_id(), 'Delivered', 'Yes', true);
    $sub->add_order_note('Item "' . $item->get_product()->name . '" for ' . $item->get_meta('Deliverable Date') . ' has been delivered.');

    //Return the response
    return wp_send_json([
        'status' => true,
        'message' => 'Item delivery status updated successfully'
    ]);
}

/**
 * Cart subscription section removing,
 * as we are using custom subscription feature.
 */
add_action('woocommerce_after_cart', 'woocommerce_after_cart_custom');
function woocommerce_after_cart_custom()
{
?>
    <script>
        jQuery('table.cart-total th h2').remove();
        jQuery('table.cart-total th p').remove();
        jQuery('table.cart-total th .wcsatt-options-cart').remove();
    </script>
<?php
}

add_shortcode('product_reviews', 'customer_review_home');
function customer_review_home()
{
    $reviews = get_comments(array(
        'number'      => 6,
        'status'      => 'approve',
        'post_status' => 'publish',
        'post_type'   => 'product'
    ));

?>
    <div class="container">
        <div class="row">
            <?php foreach ($reviews as $review) :
                $product = wc_get_product($review->comment_post_ID);
                $name_avatar = name_avatar_gen($review->comment_author);
                $rating = reset(get_comment_meta($review->comment_ID)['rating']);
                // echo '<pre>';
            ?>
                <div class="col-md-4">
                    <div class="ivole-review-card cr-card-product">
                        <div class="ivole-review-card-content">
                            <div class="top-row">
                                <div class="review-thumbnail">
                                    <?= $name_avatar ?>
                                </div>
                                <div class="reviewer">
                                    <div class="reviewer-name"><?= $review->comment_author ?></div>
                                    <div class="reviewer-verified">
                                        <i class="fa fa-check-circle"></i>
                                        Verified owner
                                    </div>
                                </div>
                            </div>
                            <div class="rating-row">
                                <div class="rating">
                                    <?= wc_get_rating_html($rating, 5) ?>
                                </div>
                                <div class="rating-label"><?= $rating ?>/5</div>
                            </div>
                            <div class="middle-row">
                                <div class="review-content">
                                    <p><?= $review->comment_content ?></p>
                                </div>
                                <div class="verified-review-row">
                                    <div class="verified-badge">
                                        <p class="ivole-verified-badge">
                                            <i class="fa fa-shield"></i>
                                            Verified review - <a href="/product/<?= $product->slug ?>" target="_blank" rel="nofollow noopener">view original</a>
                                        </p>
                                    </div>
                                </div>
                                <div class="datetime"><?= time_elapsed_string($review->comment_date) ?></div>
                            </div>
                            <div class="review-product">
                                <div class="product-thumbnail">
                                    <img src="<?= reset(wp_get_attachment_image_src(get_post_thumbnail_id($product->get_id()), 'single-post-thumbnail')) ?>" class="attachment-woocommerce_gallery_thumbnail size-woocommerce_gallery_thumbnail">
                                </div>
                                <div class="product-title">
                                    <a href="/product/<?= $product->slug ?>"><?= $product->get_name() ?></a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach ?>
        </div>
    </div>
    <?php
}

function time_elapsed_string($datetime, $full = false)
{
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($ago->format('Y-m-d') == date('Y-m-d'))
        return 'Today';

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

function name_avatar_gen($name)
{
    $name_parsed = explode(' ', $name);
    $name_alp = array_map(function ($n) {
        return substr($n, 0, 1);
    }, $name_parsed);
    $name_image = implode('', $name_alp);
    $name_avatar = strlen($name_image) == 1 ? substr($name, 0, 2) : $name_image;

    return $name_avatar;
}

add_action('woocommerce_pay_order_before_submit', 'force_save_information_for_subscription');
function force_save_information_for_subscription()
{
    if (WC()->session->subscription_order) {
    ?>
        <script>
            save = jQuery('#wc-stripe-new-payment-method');
            save.parent().html('<input type="hidden" id="wc-stripe-new-payment-method" name="wc-stripe-new-payment-method" value="true"/>');
        </script>
<?php
    }
}

//For recurring custom subscription
add_action('woocommerce_scheduled_subscription_payment', 'renew_custom_subscription', 0, 1);
function renew_custom_subscription($sub_id)
{
    $sub = wcs_get_subscription($sub_id);
    wc_schedule_single_action(date('Y-m-d H:i:s'), 'renew_custom_subscription_confirm', [
        'subscription_id' => $sub->get_id()
    ]);
}

add_action('renew_custom_subscription_confirm', 'renew_custom_subscription_confirm');
function renew_custom_subscription_confirm($sub_id)
{
    $sub = wcs_get_subscription($sub_id);
    $instance = new Custom_Subscription;
    $instance->make_charge($sub);
    $date = date_create()->modify('+1 month');

    $sub->update_dates([
        'next_payment' => $date->format('Y-m-d H:i:s')
    ]);
}

add_action('wp_ajax_upgrade_subscription', 'upgrade_custom_subscription');
add_action('wp_ajax_nopriv_upgrade_subscription', 'upgrade_custom_subscription');
function upgrade_custom_subscription()
{
    $instance = new Custom_Subscription;
    $sub = $instance->get_subscription();
    $queue = end($instance->get_queues());

    if (!$sub || !$queue)
        wp_send_json_error('No active subscription or empty queue');

    $items = $sub->get_items();
    $product = reset($items)->get_product();
    $has_var = new WC_Product_Variation($queue->variation_id);

    $variations = array_map(function ($v) use ($has_var) {
        $var = $v['attributes']['attribute_types'] == 'Subscription' ? $v : null;

        if ($var)
            $var = [
                'size' => $var['attributes']['attribute_pa_size'],
                'price' => $var['display_price'],
                'selected' => $has_var->attributes['pa_size'] == $var['attributes']['attribute_pa_size'] ? 'selected' : '',
            ];

        return $var;
    }, $product->get_available_variations());
    $variations = array_filter($variations);
    $variations = array_values($variations);

    wp_send_json_success(array_values($variations));
}

add_action('wp_ajax_upgrade_subscription_confirm', 'upgrade_custom_subscription_confirm');
add_action('wp_ajax_nopriv_upgrade_subscription_confirm', 'upgrade_custom_subscription_confirm');
function upgrade_custom_subscription_confirm()
{
    $instance = new Custom_Subscription;
    $sub = $instance->get_subscription();
    $queues = $instance->get_queues();
    $size = $_POST['size'];

    global $wpdb;
    $table = 'woocommerce_queue_data';

    $items_not_delivered = array_map(function ($itm) use ($instance) {
        return $itm->get_meta('Delivered') || date('F Y') == $itm->get_meta('Deliverable Date') ? null : $itm;
    }, $sub->get_items());

    $items_not_delivered = array_filter($items_not_delivered);
    $first = reset($items_not_delivered);

    foreach ($sub->get_items() as $key => $item) {
        if ($item->get_meta('Delivered') || date('F Y') == $item->get_meta('Deliverable Date')) {
            $item->set_total(0);
            $item->save();
            continue;
        }

        $product = $item->get_product();
        $variations = array_map(function ($var) use ($size) {
            if ($var['attributes']['attribute_pa_size'] == $size && $var['attributes']['attribute_types'] == 'Subscription')
                return $var;

            return null;
        }, $product->get_available_variations());
        $variation = reset(array_filter($variations));

        if ($first == $item) {
            $item->set_total($variation['display_price']);
            $item->save();
        } else {
            $item->set_total(0);
            $item->save();
        }

        wc_update_order_item_meta($item->get_id(), 'Size', $variation['attributes']['attribute_pa_size']);

        $wpdb->update($table, [
            'variation_id' => $variation['variation_id'],
        ], [
            'product_id' => $product->get_id(),
            'customer_id' => get_current_user_id(),
            'status' => 'Active',
        ]);
    }

    //Calculate the amounts
    $sub->calculate_totals();

    wp_send_json_success('Upgration successfull');
}
