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

            var product_id = jQuery('input[name="product_id"]').val();
            var variation_id = jQuery('input[name="variation_id"]').val();
            //console.log(variation_id);
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
                            jQuery('.queueaddedshow').html('<button class="added-queue button alt disabled wc-variation-selection-needed" disabled>Added Queue</button> <a href="/queue/" class="view-queues added-queue button alt disabled wc-variation-selection-needed">View Queue</a>');
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
function post_word_count()
{
    if (is_user_logged_in()) {

        $currentyear = date("Y");
        $currentmonth = date("n");

        $user = wp_get_current_user();

        global $wpdb;
        $table_perfixed = 'woocommerce_queue_data';

        $product_id = $_POST['productid'];
        $variation_id = $_POST['variationid'];
        $product = wc_get_product($product_id);
        
        //Select the package
        if (has_term('luxury', 'product_cat', $product_id)) {
            $package = 'luxury';
        } else {
            $package = 'regular';
        }

        $instance = new Custom_Subscription();
        $queue = $instance->get_queues(true);
        if (has_term('luxury', 'product_cat', $queue->product_id)) {
            $has_package = 'luxury';
        } else {
            $has_package = 'regular';
        }

        if ($has_package != $package) {
            return wp_send_json([
                'status' => false,
                'message' => 'Your subscription is for <a href="/product-category/subscription/' . $has_package . '"><b>' . ucfirst($has_package) . '</b></a> products only'
            ]);
        }

        if ($queue->variation_id != $variation_id)
            return wp_send_json([
                'status' => false,
                'message' => 'Your subscription is for <a href="/product-category/subscription/' . $has_package . '"><b>' . ucfirst($has_package) . '</b></a> products only'
            ]);

        $has_product = $instance->get_queues(true);
        if (!$has_product) {
            $results = $instance->get_queues();

            if (empty($results)) {
                $wpdb->insert(
                    'woocommerce_queue_data',
                    array(
                        'product_id' => $product_id,
                        'variation_id' => $variation_id,
                        'customer_id' => $user->ID,
                        'month_id' => $currentmonth,
                        'year' => $currentyear,
                        'status' => 'Active'
                    ),
                    array(
                        '%s'
                    )
                );
            } else {
                $searchresults = $wpdb->get_results("SELECT * FROM $table_perfixed
                    WHERE `customer_id` = $user->ID
                    ORDER BY year DESC, month_id DESC LIMIT 1
                    ");

                if ($searchresults[0]->month_id < 12) {
                    $newmonth = $searchresults[0]->month_id + 1;
                    $newyear = $searchresults[0]->year;
                } else {
                    $newmonth = 1;
                    $newyear = $searchresults[0]->year + 1;
                }

                $wpdb->insert(
                    'woocommerce_queue_data',
                    array(
                        'product_id' => $product_id,
                        'variation_id' => $variation_id,
                        'customer_id' => $user->ID,
                        'month_id' => $newmonth,
                        'year' => $newyear,
                        'status' => 'Active'
                    ),
                    array(
                        '%s'
                    )
                );
            }

            return wp_send_json([
                'status' => true,
                'message' => 'Your product has been added to the queue'
            ]);
        } else {
            return wp_send_json([
                'status' => false,
                'message' => 'The product is already exists in the queue'
            ]);
        }
    } else {
        return wp_send_json([
            'status' => false,
            'message' => 'Your must login first'
        ]);
    }
}

add_action('wp_ajax_post_word_count', 'post_word_count');
add_action('wp_ajax_nopriv_post_word_count', 'post_word_count');

// add to queue submission
function post_data_del()
{
    $prodid = $_POST['productid'];
    $ctid = $_POST['customerid'];
    global $wpdb;

    //delete item
    $table_perfixed = 'woocommerce_queue_data';
    $results = $wpdb->get_results("DELETE FROM $table_perfixed
    WHERE  `id` = $prodid
    AND `customer_id`=$ctid");

    //resorting
    $showresults = $wpdb->get_results("SELECT * FROM $table_perfixed
    WHERE `customer_id`=$ctid
    AND `status` = 'Active'");

    $dateincrement = 0;
    foreach ($showresults as $single) {
        $currentyear = date("Y");
        $currentmonth = date("n");
        $newcurrdate = $currentmonth + $dateincrement;
        $newcurryear = $currentyear;
        if ($newcurrdate > 12) {
            $newcurrdate = 1;
            $newcurryear = $currentyear + 1;
        }

        $updaterow = $wpdb->get_row("
		 UPDATE $table_perfixed SET `month_id` = $newcurrdate , `year` = $newcurryear WHERE  `id` = $single->id
	");

        $dateincrement++;
    }
}

add_action('wp_ajax_post_data_del', 'post_data_del');
add_action('wp_ajax_nopriv_post_data_del', 'post_data_del');


//dragable effect for queue change
function post_data_drag()
{
    global $wpdb;

    $user = wp_get_current_user();
    $table_perfixed = 'woocommerce_queue_data';
    $datapostion = $_POST['postdataid'];

    $getsrow = $wpdb->get_row("SELECT * FROM $table_perfixed WHERE  `id` = $datapostion");

    $oldposition = $_POST['old_pos'];
    $newposition = $_POST['new_pos'];

    if ($oldposition < $newposition) {

        $newcurrdate = $getsrow->month_id + $newposition + 1;
        $newcurryear = $getsrow->year;
        if ($newcurrdate == 13) {
            $newcurrdate = 1;
            $newcurryear = $getsrow->year + 1;
        }
        if ($newcurrdate == 14) {
            $newcurrdate = 2;
            $newcurryear = $getsrow->year + 1;
        }
        if ($newcurrdate == 15) {
            $newcurrdate = 3;
            $newcurryear = $getsrow->year + 1;
        }
        if ($newcurrdate == 16) {
            $newcurrdate = 4;
            $newcurryear = $getsrow->year + 1;
        }
        if ($newcurrdate == 17) {
            $newcurrdate = 5;
            $newcurryear = $getsrow->year + 1;
        }
        if ($newcurrdate == 18) {
            $newcurrdate == 6;
            $newcurryear = $getsrow->year + 1;
        }
        if ($newcurrdate == 19) {
            $newcurrdate == 7;
            $newcurryear = $getsrow->year + 1;
        }
        if ($newcurrdate == 20) {
            $newcurrdate == 8;
            $newcurryear = $getsrow->year + 1;
        }
        if ($newcurrdate == 21) {
            $newcurrdate == 9;
            $newcurryear = $getsrow->year + 1;
        }
        if ($newcurrdate == 22) {
            $newcurrdate == 10;
            $newcurryear = $getsrow->year + 1;
        }
        if ($newcurrdate == 23) {
            $newcurrdate == 11;
            $newcurryear = $getsrow->year + 1;
        }
        if ($newcurrdate == 24) {
            $newcurrdate == 12;
            $newcurryear = $getsrow->year + 1;
        }


        $dragrowupdate = $wpdb->get_row("
		 UPDATE $table_perfixed SET `month_id` = $newcurrdate , `year` = $newcurryear WHERE  `id` = $datapostion
	");
    }
    if ($oldposition > $newposition) {
        $datacal = $newposition;

        $showresultsforview = $wpdb->get_results("SELECT * FROM $table_perfixed WHERE `customer_id` = $user->ID AND `status` = 'Active' ORDER BY year ASC, month_id ASC");

        $newincrement = 1;
        foreach ($showresultsforview as $ssingle) {

            if ($newincrement == $datacal) {

                $updatecheck = $wpdb->get_row("
			 SELECT * FROM $table_perfixed WHERE `id` = $ssingle->id
		");

                $updaterow = $wpdb->get_row("
			 UPDATE $table_perfixed SET `month_id` = $updatecheck->month_id , `year` = $updatecheck->year WHERE  `id` = $datapostion
		");
            }

            $newincrement++;
        }

        if ($datacal == 0) {

            $newcheck = 1;
            foreach ($showresultsforview as $ssingle) {
                if ($newcheck == 1) {

                    $oneupdatecheck = $wpdb->get_row("
			 SELECT * FROM $table_perfixed WHERE `id` = $ssingle->id
		");
                    $order_monthid = $oneupdatecheck->month_id - 1;
                    $firstinfoupdate = $wpdb->get_row("
			 UPDATE $table_perfixed SET `month_id` = $order_monthid , `year` = $oneupdatecheck->year WHERE  `id` = $datapostion
		");
                }

                $newcheck++;
            }
        }
    }


    $showresults = $wpdb->get_results("SELECT * FROM $table_perfixed WHERE `customer_id` = $user->ID AND `status` = 'Active' ORDER BY year ASC, month_id ASC");


    $dateincrement = 0;
    foreach ($showresults as $single) {
        $currentyear = date("Y");
        $currentmonth = date("n");
        $updatenewcurrdate = $currentmonth + $dateincrement;
        $updatecurryear = $currentyear;

        if ($updatenewcurrdate == 13) {
            $updatenewcurrdate = 1;
            $updatecurryear = $currentyear + 1;
        }
        if ($updatenewcurrdate == 14) {
            $updatenewcurrdate = 2;
            $updatecurryear = $currentyear + 1;
        }
        if ($updatenewcurrdate == 15) {
            $updatenewcurrdate = 3;
            $updatecurryear = $currentyear + 1;
        }
        if ($updatenewcurrdate == 16) {
            $updatenewcurrdate = 4;
            $updatecurryear = $currentyear + 1;
        }
        if ($updatenewcurrdate == 17) {
            $updatenewcurrdate = 5;
            $updatecurryear = $currentyear + 1;
        }
        if ($updatenewcurrdate == 18) {
            $updatenewcurrdate = 6;
            $updatecurryear = $currentyear + 1;
        }
        if ($updatenewcurrdate == 19) {
            $updatenewcurrdate = 7;
            $updatecurryear = $currentyear + 1;
        }
        if ($updatenewcurrdate == 20) {
            $updatenewcurrdate = 8;
            $updatecurryear = $currentyear + 1;
        }
        if ($updatenewcurrdate == 21) {
            $updatenewcurrdate = 9;
            $updatecurryear = $currentyear + 1;
        }
        if ($updatenewcurrdate == 22) {
            $updatenewcurrdate = 10;
            $updatecurryear = $currentyear + 1;
        }
        if ($updatenewcurrdate == 23) {
            $updatenewcurrdate = 11;
            $updatecurryear = $currentyear + 1;
        }
        if ($updatenewcurrdate == 24) {
            $updatenewcurrdate = 12;
            $updatecurryear = $currentyear + 1;
        }

        $updaterow = $wpdb->get_row("UPDATE $table_perfixed
        SET `month_id` = $updatenewcurrdate ,
        `year` = $updatecurryear
        WHERE  `id` = $single->id
        ");
        $dateincrement++;
    }

    $instance = new Custom_Subscription();
    $instance->update_subscription();
}

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
