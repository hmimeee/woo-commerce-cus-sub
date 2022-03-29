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
        $qdata = $instance->get_queues();
        $queue = end($qdata);

        //Check if the queue is empty
        if ($queue) {
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
        }

        if ($instance->add_to_queue($product_id, $variation_id)) {
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
        $instance->delete_data($item_id);

        foreach ($queues as $data) {
            if ($item == $data)
                continue;

            $position = $data->position;
            if ($had_position > $position) {
                $instance->update_data($data->id, [
                    'position' => $position - 1
                ]);
            }
        }
    }

    //Get the subscription data
    $sub = $instance->get_subscription();
    if ($sub) {
        //Remove all non-delivered the items
        foreach ($sub->get_items() as $id => $item) {
            if ($item->get_meta('Delivered') == 'Yes')
                continue;

            wc_delete_order_item($item->get_id());
        }

        $instance->update_subscription_items($sub);
    }

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
    $current_pos_item = $instance->get_queues($new_position, 'position');
    $current_variation = new WC_Product_Variation(reset($current_pos_item)->variation_id);
    $has_variation = new WC_Product_Variation($queue_row->variation_id);

    if ($current_variation->get_id() && $current_variation->get_attribute('pa_size') != $has_variation->get_attribute('pa_size'))
        return wp_send_json([
            'status' => false,
            'message' => 'You can\'t update current month after upgration'
        ]);

    if ($has_items >= 2)
        return wp_send_json([
            'status' => false,
            'message' => 'Max item for the month exceeded'
        ]);

    if ($had_items >= 2 || end($queues) == $queue_row) {
        $instance->update_data($queue_row->id, array(
            'position' => $new_position
        ));
    }

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

    //Get the subscription data
    $sub = $instance->get_subscription();

    if ($sub) {
        //Remove all non-delivered the items
        foreach ($sub->get_items() as $id => $item) {
            if ($item->get_meta('Delivered') == 'Yes')
                continue;

            wc_delete_order_item($item->get_id());
        }

        $instance->update_subscription_items($sub);
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
 * This will check and create a subscription against the order]
 * 
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

        $instance = new Custom_Subscription();
        $queue = $instance->get_queues(true);
        $instance->delete_data($queue->position, true);

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

    if ($order && (!WC()->session->get('cart') || empty(WC()->session->get('cart')))) {
        $available_gateways = array('stripe' => $available_gateways['stripe']);
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

// add_action('add_meta_boxes',  'item_delivery_status');
function item_delivery_status()
{
    add_meta_box(
        'wc_subscription_item_delivery', // Unique ID
        'Item Delivery Status Update', // Box title
        'wc_subscription_item_delivery_html', // Content callback, must be of type callable
        'shop_subscription' // Post type
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

/**
 * For recurring custom subscription
 * This will create a schedule action for the payment force charging the customer for next month
 * also will create next date schedule
 * 
 */
add_action('woocommerce_scheduled_subscription_payment', 'renew_custom_subscription', 0, 1);
function renew_custom_subscription($sub_id)
{
    $sub = wcs_get_subscription($sub_id);
    wc_schedule_single_action(date('Y-m-d H:i:s'), 'renew_custom_subscription_confirm', [
        'subscription_id' => $sub->get_id()
    ]);
}

/**
 * Recurring payment charging action handler
 * This will charge the customer for the current month
 * also will create next month schedule
 * 
 */
add_action('renew_custom_subscription_confirm', 'renew_custom_subscription_confirm');
function renew_custom_subscription_confirm($sub_id)
{
    //Get the subscription
    $sub = wcs_get_subscription($sub_id);
    $instance = new Custom_Subscription; //get the subscription instance

    //Check if the subscription is active
    if ($sub->get_status() != 'active')
        return false;

    //Get the pending payment orders of the subscription
    $orders = wc_get_orders(['parent' => $sub->get_id()]);
    $pending_orders = array_map(function ($order) {
        return $order->get_status() == 'pending' ? $order : null;
    }, $orders);
    $pending_orders = array_filter($pending_orders);

    //Check whether the payment recurring worked previously
    $has_order = !empty(reset($pending_orders)) ? reset($pending_orders) : null;

    //If order found, then grab the order
    if ($has_order)
        $order = $has_order;

    //Create the order for recurring payment
    if (!$has_order) {
        $order = wc_create_order(array(
            'customer_id'   => $sub->get_user_id(),
            'parent'        => $sub->get_id()
        ));

        //Set the parent details to the created order
        $order->set_parent_id($sub->get_id());
        $order->set_address($sub->get_address());
        $order->set_address($sub->get_address('shipping'), 'shipping');

        //Find out the deliverable items and the date
        $deliverables = array_map(function ($itm) {
            return !$itm->get_meta('Delivered') ? $itm : null;
        }, $sub->get_items());
        $deliverables = array_filter($deliverables);
        $delivery_date = !empty($deliverables) ? reset($deliverables)->get_meta('Deliverable Date') : null;

        //Add the deliverable items to the order
        foreach ($deliverables as $itm) {
            //Check if the item has current delivery date
            if ($itm->get_meta('Deliverable Date') != $delivery_date)
                continue;

            $item = $order->add_product($itm->get_product(), 1, [
                'total' => $itm->get_total()
            ]);
            wc_add_order_item_meta($item, 'Size', $itm->get_meta('Size'), true);
            wc_add_order_item_meta($item, 'Deliverable Date', $itm->get_meta('Deliverable Date'), true);

            wc_add_order_item_meta($itm->get_id(), 'Delivered', 'Yes', true);
        }

        //Calculate the amount of the order
        $order->calculate_totals();

        //Save the set data
        $order->save();
    }

    //Prepare source from the subscription order
    $stripe = new WC_Stripe_Order_Handler();
    $source = $stripe->prepare_order_source($sub);

    //Charge the user for the order
    $instance->make_charge($order, $source);

    //Delete the items from the queue of current month
    $queue = $instance->get_queues(true);
    $instance->delete_data($queue->position, true);

    if ($order->get_status() == 'processing') {
        //Update the next payment date of the subscription
        $sub->update_dates([
            'next_payment' => (new DateTime())->modify('+1 month')->format('Y-m-d H:i:s')
        ]);
    } else {
        $sub->add_order_note('Payment failed');
        $sub->update_status('on-hold');
        $order->update_status('failed');
    }
}

/**
 * Upgrade subscription sizes response endpoint
 * This will return sizes of the products
 * 
 */
add_action('wp_ajax_upgrade_subscription', 'upgrade_custom_subscription');
add_action('wp_ajax_nopriv_upgrade_subscription', 'upgrade_custom_subscription');
function upgrade_custom_subscription()
{
    $instance = new Custom_Subscription;
    $sub = $instance->get_subscription();
    $queue = end($instance->get_queues());

    if (!$sub || !$queue)
        wp_send_json_error('No active subscription or empty queue');

    $product = wc_get_product($queue->product_id);
    $has_var = new WC_Product_Variation($queue->variation_id);

    $variations = array_map(function ($v) use ($has_var) {
        $type = $v['attributes']['attribute_type'] ?? $v['attributes']['attribute_types'];
        $var = $type == 'Subscription' ? $v : null;

        if ($var) {
            $var = [
                'size' => $var['attributes']['attribute_pa_size'],
                'price' => $var['display_price'],
                'selected' => $has_var->attributes['pa_size'] == $var['attributes']['attribute_pa_size'] ? 'selected' : '',
            ];
        }

        return $var;
    }, $product->get_available_variations());

    $variations = array_filter($variations);
    $variations = array_values($variations);

    wp_send_json_success(array_values($variations));
}

/**
 * Subscription upgarde confirm request handler
 * This action will run during saving the upgrade size
 *
 */
add_action('wp_ajax_upgrade_subscription_confirm', 'upgrade_custom_subscription_confirm');
add_action('wp_ajax_nopriv_upgrade_subscription_confirm', 'upgrade_custom_subscription_confirm');
function upgrade_custom_subscription_confirm()
{
    $instance = new Custom_Subscription;
    $sub = $instance->get_subscription();
    $size = $_POST['size'];
    global $wpdb;
    $table = 'woocommerce_queue_data';

    foreach ($sub->get_items() as $item) {
        if ($item->get_meta('Delivered') || date('F Y') == $item->get_meta('Deliverable Date'))
            continue;

        $product = $item->get_product();
        $variations = array_map(function ($var) use ($size) {
            if ($var['attributes']['attribute_pa_size'] == $size && $var['attributes']['attribute_type'] == 'Subscription')
                return $var;

            return null;
        }, $product->get_available_variations());
        $variation = reset(array_filter($variations));

        if (!$variation)
            continue;

        $item->set_total($variation['display_price']);
        $item->save();
        wc_update_order_item_meta($item->get_id(), 'Size', $variation['attributes']['attribute_pa_size']);

        $wpdb->update($table, [
            'variation_id' => $variation['variation_id'],
        ], [
            'product_id' => $product->get_id(),
            'customer_id' => get_current_user_id()
        ]);
    }

    //Calculate the amounts
    $sub->calculate_totals();

    wp_send_json_success('Upgration successfull');
}

add_filter('woocommerce_cart_item_product', 'change_cart_item_product_data', 1);
function change_cart_item_product_data($product)
{
    $product->set_name(change_product_custom_name($product->get_name()));
    $product->name = change_product_custom_name($product->get_name());
    return $product;
}

add_filter('woocommerce_cart_item_name', 'change_orders_items_names', 1, 2);
function change_orders_items_names($item_html, $item_array)
{
    $dom = new DOMDocument();
    @$dom->loadHTML($item_html);
    $new_name = change_product_custom_name($dom->textContent);
    @$dom->getElementsByTagName('a')[0]->nodeValue = htmlspecialchars($new_name);

    return $dom->saveHTML();
}

function change_product_custom_name($name)
{
    $exploded  = explode('-', $name);
    $name = reset($exploded);
    $cats = explode(',', end($exploded));
    $category = reset($cats);
    $size = $category != end($cats) ? end($cats) : null;
    $new_name = $name . ' - ' . ($size ? $size .  ', ' : null) . $category;

    return $new_name;
}

// Additional custom column
add_filter('manage_edit-shop_order_columns', 'custom_shop_order_column', 20);
function custom_shop_order_column($columns)
{
    $reordered_columns = array();

    foreach ($columns as $key => $column) {
        $reordered_columns[$key] = $column;
        if ($key ==  'shipping_address') {
            $reordered_columns['coupons'] = __('Coupons', 'woocommerce');
        }
    }
    return $reordered_columns;
}

// Custom column content
add_action('manage_shop_order_posts_custom_column', 'custom_shop_order_column_used_coupons');
function custom_shop_order_column_used_coupons($column)
{
    global $post, $the_order;

    if (!is_a($the_order, 'WC_Order')) {
        $the_order = wc_get_order($post->ID);
    }

    if ('coupons' === $column) {
        $coupon_codes = $the_order->get_coupon_codes();

        if (!empty($coupon_codes)) {
            echo implode(', ', $coupon_codes);
        }
    }
}

add_action('woocommerce_admin_order_item_headers', 'woocommerce_admin_html_order_item_class_custom');
function woocommerce_admin_html_order_item_class_custom($order)
{
    $items = array_map(function ($item) {
        $item->set_name(change_product_custom_name($item->get_name()));
    }, $order->get_items());

    return $items;
}

add_filter('woocommerce_admin_order_preview_line_items', 'woocommerce_admin_order_preview_line_items_custom', 10, 2);
function woocommerce_admin_order_preview_line_items_custom($items, $order)
{
    // if ($order->get_status() != 'completed')
    //     return $items;

    $changedItems = array_map(function ($item) {
        $item->set_name(change_product_custom_name($item->get_name()));
        return $item;
    }, $items);

    return $changedItems;
}

add_action('add_meta_boxes',  'parent_order_details_show');
function parent_order_details_show()
{
    add_meta_box(
        'wc_parent_order_show', // Unique ID
        'Parent Order Details', // Box title
        'parent_order_details_show_html', // Content callback, must be of type callable
        'shop_order' // Post type
    );
}

function parent_order_details_show_html()
{
    $order = wc_get_order(get_the_ID());
    $sub = wcs_get_subscription($order->get_parent_id());

    if (!$sub)
        return false;
    ?>
    <div class="woocommerce_subscriptions_related_orders">
        <table>
            <thead>
                <tr>
                    <th>Order Number</th>
                    <th>Relationship</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <a href="/wp-admin/post.php?post=<?= $sub->get_id() ?>&amp;action=edit">
                            #<?= $sub->get_id() ?>
                        </a>
                    </td>
                    <td>
                        Subscription
                    </td>
                    <td>
                        <abbr title="<?= $sub->order_date ?>">
                            <?= human_time_diff(strtotime($sub->order_date), current_time('U')) ?> </abbr>
                    </td>
                    <td>
                        <?= $sub->get_status() ?>
                    </td>
                    <td>
                        <span class="amount">
                            <span class="woocommerce-Price-amount amount">
                                <span class="woocommerce-Price-currencySymbol">$</span><?= $sub->get_total() ?>
                            </span>
                        </span>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
<?php
}
