<?php
// define('WP_DEBUG', true);
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// echo "<pre>";

require_once plugin_dir_path(__FILE__) . 'class-custom-subscription.php';

add_action('wp_enqueue_scripts', 'porto_child_css', 1001);

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

        $has_product = $instance->get_queues($product_id, 'product');
        if ($has_product)
            return wp_send_json([
                'status' => false,
                'message' => 'The product is already exists in the queue'
            ]);

        if ($instance->add_to_queue($product_id, $variation_id)) {
            $sub = $instance->get_subscription();

            if ($sub) {
                $product = wc_get_product($product_id);
                $item = $sub->add_product($product, 1, [
                    'total' => $queue ? 0 : $new_variation->price
                ]);

                //Add item meta to track the month
                wc_add_order_item_meta($item, 'Deliverable Date', $date->format('F Y'), true);
                $sub->calculate_totals();

                //Update the subscription date
                $sub->update_dates(array('end' => $date->modify('last day of this month')->format('Y-m-d H:i:s')));
            }
            $date = $queue ? DateTime::createFromFormat('Y-m', $queue->year . '-' . $queue->month_id) : new DateTime();
            $date->modify('+1 month');

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
    $item_id = $_POST['productid'];
    if (!$item_id)
        return false;

    $instance = new Custom_Subscription;
    $queues = $instance->get_queues();

    //Queue re-arrange
    $table = 'woocommerce_queue_data'; //Get the table name
    $query = ''; //Balnk variable for the query
    $got_place = false;
    $deletable = null;
    $date = new DateTime();
    $from_date = $date;
    foreach ($queues as $key => $data) {
        if ($item_id == $data->id) {
            $got_place = true;

            $deletable = $data;
            $date = DateTime::createFromFormat('Y-m', $data->year . '-' . $data->month_id);
            $from_date = $date;
        } elseif ($got_place) {
            //Update queue data placement
            $query .= "UPDATE " . $table . " SET month_id='" . $date->format('n') . "', year='" . $date->format('Y') . "' WHERE id=$data->id;";
            $date->modify('+1 month');
        }
    }

    //Get the subscription data
    $sub = $instance->get_subscription();

    if (!$sub) {
        global $wpdb;
        $wpdb->delete(
            $table,
            array(
                'id' => $deletable->id
            ),
            array(
                '%d'
            )
        );
    } else {
        $items = $sub->get_items();
        $has_delivered = $instance->get_delivered_items();
        $items_to_change = array_diff($items, $has_delivered);

        foreach ($items_to_change as $key => $item) {
            if ($item->get_product()->get_id() == $deletable->product_id) {
                global $wpdb;
                $wpdb->delete(
                    $table,
                    array(
                        'id' => $deletable->id
                    ),
                    array(
                        '%d'
                    )
                );

                wc_delete_order_item($item->get_id());
            }

            $item->update_meta_data('Deliverable Date', $from_date->format('F Y'));
            $from_date->modify('+1 month');
        }

        //Calculate the amounts
        $sub->calculate_totals();

        //Update the subscription date
        $sub->update_dates(array('end' => $from_date->modify('last day of this month')->format('Y-m-d H:i:s')));
    }

    //Update the queue
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($query);
}

add_action('wp_ajax_post_data_del', 'post_data_del');
add_action('wp_ajax_nopriv_post_data_del', 'post_data_del');

function update_queue_only($list, $date)
{
    //Queue data parsing
    global $wpdb;
    $table = 'woocommerce_queue_data';

    $query = '';
    foreach ($list as $key => $data) {
        //Update queue data placement
        $query .= "UPDATE " . $table . " SET month_id='" . $date->format('n') . "', year='" . $date->format('Y') . "' WHERE id=$data->id;";
        $date->modify('+1 month');
    }

    //Update the queue
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($query);

    return true;
}


//dragable effect for queue change
function post_data_drag()
{
    $instance = new Custom_Subscription();

    //get the required data from the database
    $queue_row = $instance->get_queues($_POST['postdataid'], 'row');
    $queues = $instance->get_queues();
    $new_place = $_POST['new_pos'];

    //Predefine variables for the loop
    $first_line = [];
    $second_line = [];
    $got_place = false;
    $last_data = end($queues);

    //Queue re-arrange
    foreach ($queues as $place => $data) {
        if ($place == $new_place) {
            $got_place = true;

            if ($last_data != $data) {
                $first_line[] = $queue_row;
                $second_line[] = $data;
            }

            if ($last_data == $data) {
                $second_line[] = $queue_row;
                $first_line[] = $data;
            }
        } else {
            if ($got_place && $queue_row != $data)
                $second_line[] = $data;

            if (!$got_place && $queue_row != $data)
                $first_line[] = $data;
        }
    }

    //Marge the lines into a list
    $list = array_merge($first_line, $second_line);

    //Get the first day of the current month
    $date = $instance->date;

    //Get the subscription data
    $sub = $instance->get_subscription();

    if (!$sub) {
        update_queue_only($list, $date);

        //Return the response
        return wp_send_json([
            'status' => true,
            'message' => 'Queue updated successfully'
        ]);
    }

    //Get the items
    $items = $sub->get_items();

    $items_to_keep = []; //Empty array for the delivered items
    foreach ($items as $key => $item) {

        //Check if the item has been delivered, then add it to the keep list. Also update the date.
        if ($item->get_meta('Delivered')) {
            $items_to_keep[] = $item->get_product_id();
            $date->modify('+1 month');
            continue;
        }

        //Delete the item from the subscription
        wc_delete_order_item($item->get_id());
    }

    $table = 'woocommerce_queue_data'; //Get the table name
    $query = ''; //Balnk variable for the query
    foreach ($list as $key => $data) {

        //Check if the item has been delivered, then skip it
        if (in_array($data->product_id, $items_to_keep))
            continue;

        $product = wc_get_product($data->product_id); //Get the product object
        $variation = new WC_Product_Variation($data->variation_id); //Get the variation object
        $price = $variation->price; //Get the amount of the variation

        //Add amount if the item is first one, else 0 amount
        if ($key == 0 && count($items_to_keep) == 0)
            $item = $sub->add_product($product, 1, [
                'total' => $price
            ]);
        else
            $item = $sub->add_product($product, 1, [
                'total' => 0
            ]);

        //Add item meta to track the month
        wc_add_order_item_meta($item, 'Deliverable Date', $date->format('F Y'), true);

        //Update queue data placement
        $query .= "UPDATE " . $table . " SET month_id='" . $date->format('n') . "', year='" . $date->format('Y') . "' WHERE id=$data->id;";

        //Check if the list has end
        if (end($list) != $data)
            $date->modify('+1 month');
    }

    //Get the subscription data
    $sub = reset(wcs_get_users_subscriptions());

    //Calculate the amounts
    $sub->calculate_totals();

    //Update the subscription date
    $sub->update_dates(array('end' => $date->modify('last day of this month')->format('Y-m-d H:i:s')));

    //Update the queue
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($query);

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

    if ($sub)
        return true;
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

function item_delivery_status($post)
{
    add_meta_box(
        'wc_subscription_item_delivery', // Unique ID
        'Item Delivery Status Update', // Box title
        'wc_subscription_item_delivery_html', // Content callback, must be of type callable
        'shop_subscription', // Post type
    );
}
add_action('add_meta_boxes',  'item_delivery_status');

function wc_subscription_item_delivery_html($post)
{
    $sub = wcs_get_subscription(get_the_ID());
?>
    <?php wp_nonce_field() ?>
    <select name="item_id" id="item_id" class="postbox">
        <option value="">Select item</option>
        <?php foreach ($sub->get_items() as $key => $item) : ?>
            <?php if ($item->get_meta('Delivered')) continue; ?>
            <option value='<?= $item->get_id() ?>'><?= $item->get_product()->name ?></option>
        <?php endforeach ?>
    </select>
    <button type="button" id="deliver_item" class="button">Set Delivered</button>

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
