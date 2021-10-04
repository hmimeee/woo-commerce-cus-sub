<?php

class Custom_Subscription
{
    public $user_id, $user, $date;

    public function __construct()
    {
        $this->user_id = get_current_user_id();
        $this->user = get_user_by('ID', $this->user_id);

        //Date object
        $this->date = new DateTime();
    }


    public function order_details()
    {
        $fname     = $this->user->first_name;
        $lname     = $this->user->last_name;
        $email     = $this->user->user_email;

        $data = array(
            'terms' => 0,
            'createaccount' => 0,
            'payment_method' => 'stripe',
            'shipping_method' => array(
                'free_shipping:1'
            ),
            'ship_to_different_address' => '',
            'woocommerce_checkout_update_totals' => '',
            'order_comments' => ''
        );

        $b_address_1 = get_user_meta($this->user_id, 'billing_address_1', true);
        $b_address_2 = get_user_meta($this->user_id, 'billing_address_2', true);
        $b_city      = get_user_meta($this->user_id, 'billing_city', true);
        $b_postcode  = get_user_meta($this->user_id, 'billing_postcode', true);
        $b_country   = get_user_meta($this->user_id, 'billing_country', true);
        $b_state     = get_user_meta($this->user_id, 'billing_state', true);
        $b_phone     = get_user_meta($this->user_id, 'billing_phone', true);
        $b_company     = get_user_meta($this->user_id, 'billing_company', true);

        $billing_address = array(
            'billing_first_name' => $fname,
            'billing_last_name' => $lname,
            'billing_company' => $b_company,
            'billing_country' => $b_country,
            'billing_address_1' => $b_address_1,
            'billing_address_2' => $b_address_2,
            'billing_city' => $b_city,
            'billing_state' => $b_state,
            'billing_postcode' => $b_postcode,
            'billing_phone' => $b_phone,
            'billing_email' => $email,
        );

        $s_address_1 = get_user_meta($this->user_id, 'shipping_address_1', true);
        $s_address_2 = get_user_meta($this->user_id, 'shipping_address_2', true);
        $s_city      = get_user_meta($this->user_id, 'shipping_city', true);
        $s_postcode  = get_user_meta($this->user_id, 'shipping_postcode', true);
        $s_country   = get_user_meta($this->user_id, 'shipping_country', true);
        $s_state     = get_user_meta($this->user_id, 'shipping_state', true);
        $s_company     = get_user_meta($this->user_id, 'shipping_company', true);

        $shipping_address = array(
            'shipping_first_name' => $fname,
            'shipping_last_name' => $lname,
            'shipping_company' => $s_company,
            'shipping_country' => $s_country,
            'shipping_address_1' => $s_address_1,
            'shipping_address_2' => $s_address_2,
            'shipping_city' => $s_city,
            'shipping_state' => $s_state,
            'shipping_postcode' => $s_postcode
        );

        return (object) [
            'data' => $data,
            'billing_address' => $billing_address,
            'shipping_address' => $shipping_address,
            'user' => $this->user,
            'date' => $this->date
        ];
    }

    /**
     * Get the queues data
     * 
     * @param bool $single `true` means single row, else all rows and `is_numeric` value for product id. Also $order = `row` means row id.
     * @param string $order Ordering data as `ASC` or `DESC`
     * @return mixed Array|Object
     */
    public function get_queues($single = false, $order = 'ASC')
    {
        //Queue data parsing
        global $wpdb;
        $table = 'woocommerce_queue_data';

        if ($order == 'products') {
            $query = "SELECT * FROM $table
                WHERE  `customer_id` = $this->user_id
                AND  `product_id` IN ($single)
                AND `status` = 'Active'
                ORDER BY year ASC, month_id ASC";
        } elseif (is_numeric($single) && $order == 'product') {
            $query = "SELECT * FROM $table
                WHERE  `customer_id` = $this->user_id
                AND `product_id` = $single
                AND `status` = 'Active'
                ORDER BY year ASC, month_id ASC";
        } elseif ($order == 'row') {
            $query = "SELECT * FROM $table 
            WHERE  `customer_id` = $this->user_id
            AND  `id` = $single
            ORDER BY year ASC, month_id ASC";
        } elseif ($order == 'date') {
            $range = explode('-', $single);
            $year = reset($range);
            $month = end($range);

            $query = "SELECT * FROM $table 
            WHERE  `customer_id` = $this->user_id
            AND  `year` = $year
            AND  `month_id` = $month
            ORDER BY year ASC, month_id ASC";
        } else {
            $query = "SELECT * FROM $table
                WHERE  `customer_id` = $this->user_id
                AND `status` = 'Active'
                ORDER BY year $order, month_id $order";
        }

        $result = $wpdb->get_results($query);
        if (!is_string($single) && $single == true)
            return end($result);

        return $result;
    }

    public function empty_queue()
    {
        //Queue data parsing
        global $wpdb;
        $table = 'woocommerce_queue_data';

        $query = "DELETE FROM $table
                WHERE  `customer_id` = $this->user_id
                ORDER BY year ASC, month_id ASC";

        return $wpdb->query($query);
    }

    /**
     * Subscription charge amount calculation
     * 
     * @param $product_id Product id to get the category of subscription
     * @return float
     */
    public function get_amount($queue = null)
    {
        $queue = $queue ?? $this->get_queues(true);
        $variation = new WC_Product_Variation($queue->variation_id);
        $price = $variation->price;

        return $price;
    }

    /**
     * Create subscription against an order
     * 
     * @param $order_id Order ID to create the subscription against it
     * @return object
     */
    public function create_subscription($order_id)
    {
        $details = $this->order_details();

        //Create the subscription
        $sub = wcs_create_subscription(array(
            'order_id' => $order_id,
            'status' => 'pending', // Status should be initially set to pending
            'billing_period' => 'month',
            'billing_interval' => 1,
        ));

        //Check if subscription created
        if (is_wp_error($sub))
            wp_redirect('queue');

        //Set the addresses for the subscription
        $sub->set_billing_first_name($details->billing_address['billing_first_name']);
        $sub->set_billing_last_name($details->billing_address['billing_last_name']);
        $sub->set_billing_email($details->billing_address['billing_email']);
        $sub->set_billing_phone($details->billing_address['billing_phone']);
        $sub->set_billing_country($details->billing_address['billing_country']);
        $sub->set_billing_state($details->billing_address['billing_state']);
        $sub->set_billing_city($details->billing_address['billing_city']);
        $sub->set_billing_postcode($details->billing_address['billing_postcode']);
        $sub->set_billing_address_1($details->billing_address['billing_address_1']);
        $sub->set_billing_address_2($details->billing_address['billing_address_2']);
        $sub->set_billing_company($details->billing_address['billing_company']);

        $sub->set_shipping_first_name($details->shipping_address['shipping_first_name']);
        $sub->set_shipping_last_name($details->shipping_address['shipping_last_name']);
        $sub->set_shipping_country($details->shipping_address['shipping_country']);
        $sub->set_shipping_state($details->shipping_address['shipping_state']);
        $sub->set_shipping_city($details->shipping_address['shipping_city']);
        $sub->set_shipping_postcode($details->shipping_address['shipping_postcode']);
        $sub->set_shipping_address_1($details->shipping_address['shipping_address_1']);
        $sub->set_shipping_address_2($details->shipping_address['shipping_address_2']);
        $sub->set_shipping_company($details->shipping_address['shipping_company']);

        //Store source details in the subscription
        $stripe = new WC_Gateway_Stripe();
        $order = $sub->get_parent();
        $prepared_source = $stripe->prepare_order_source($order);
        $stripe->save_source_to_order($sub, $prepared_source);

        //Add items to the subscription
        $this->update_subscription_items($sub);

        //Calculate the amounts
        $sub->calculate_totals();

        //Status update
        $sub->update_status('active');

        return $sub;
    }

    public function update_subscription_items($sub)
    {
        // Add product to the subscription
        $queues = $this->get_queues();
        $date = clone $this->date;
        $count = 0;
        foreach ($queues as $queue) {
            $product = wc_get_product($queue->product_id);
            $variation = new WC_Product_Variation($queue->variation_id);

            //Check if product exist
            if (!$product)
                continue;

            if ($count == 0) {
                $item = $sub->add_product($product, 1, ['total' => $this->get_amount($queue), 'month' => $date->format('F Y')]); //Set the product for the subscription with price for first product
                wc_add_order_item_meta($item, 'Size', $variation->attributes['pa_size']);
                wc_add_order_item_meta($item, 'Deliverable Date', $date->format('F Y'), true);
            } else {
                $item = $sub->add_product($product, 1, ['total' => 0]); //Set the product for the subscription
                wc_add_order_item_meta($item, 'Size', $variation->attributes['pa_size']);
                wc_add_order_item_meta($item, 'Deliverable Date', $date->format('F Y'), true);
            }

            $count++;
            $date->modify('+1 month');
        }

        //Update the dates
        $end_date = (clone $this->date->modify('+' . count($queues) . ' month'))->modify('last day of this month')->format('Y-m-d H:i:s');
        $sub->update_dates(array('end' => $end_date));
    }

    /**
     * Update the subscription
     * 
     * @return void
     */
    public function update_subscription($status = null)
    {
        $sub = $this->get_subscription();

        if ($status) {
            $sub->update_status('active');
            return true;
        }

        $this->update_subscription_items($sub);
        return true;
    }

    public function get_subscription($user = null)
    {
        $user = $user ?? wp_get_current_user();
        $sub_array = wcs_get_users_subscriptions($user->ID);
        $sub_array = array_map(function ($sub) {
            return in_array($sub->get_status(), ['cancelled', 'pending-cancel']) ? null : $sub;
        }, $sub_array);
        $sub = array_filter($sub_array);

        if (!empty($sub))
            $sub = end($sub);

        return $sub;
    }

    /**
     * Deprecated function
     */
    public function add_monthly_item($item_id, $product_id, $year = null, $month = null)
    {
        $year = $year ?? date('Y');
        $month = $month ?? date('F');

        global $wpdb;
        $wpdb->show_errors();
        $insert = $wpdb->insert('wp_subscriptions', array(
            'item_id' => $item_id,
            'product_id' => $product_id,
            'year' => $year,
            'month' => $month,
        ));

        return $insert;
    }

    public function add_to_queue($product_id, $variation_id)
    {
        $results = $this->get_queues();
        global $wpdb;

        if (!$results) {
            $insert = $wpdb->insert(
                'woocommerce_queue_data',
                array(
                    'product_id' => $product_id,
                    'variation_id' => $variation_id,
                    'customer_id' => $this->user->ID,
                    'month_id' => date('n'),
                    'year' => date("Y"),
                    'status' => 'Active'
                ),
                array(
                    '%s'
                )
            );
        } else {
            $exist_product = end($this->get_queues());
            $date = DateTime::createFromFormat('Y-m', $exist_product->year . '-' . $exist_product->month_id);
            $date->modify('+1 month');

            $insert = $wpdb->insert(
                'woocommerce_queue_data',
                array(
                    'product_id' => $product_id,
                    'variation_id' => $variation_id,
                    'customer_id' => $this->user->ID,
                    'month_id' => $date->format('n'),
                    'year' => $date->format('Y'),
                    'status' => 'Active'
                ),
                array(
                    '%s'
                )
            );
        }

        return $insert;
    }

    public function get_subscription_metas($queue)
    {
        $variation = new WC_Product_Variation($queue->variation_id);
        $product = wc_get_product($queue->product_id);

        if (has_term('luxury', 'product_cat', $queue->product_id)) {
            $package = 'Luxury';
        } else {
            $package = 'Regular';
        }

        return [
            'price' => $variation->price,
            'type' => $package
        ];
    }

    public function get_delivered_items($sub = null)
    {
        $sub = $sub ?? $this->get_subscription();

        //Get the items
        $items = $sub->get_items();

        $delivered = [];
        foreach ($items as $key => $item) {
            if ($item->get_meta('Delivered')) {
                $delivered[] = $item;
                continue;
            }
        }

        return $delivered;
    }

    /**
     * Update subscription items
     * 
     * @param mixed $sub Subscription object
     * @param mixed|null $list Queue list
     */
    public function update_items($sub, $list = null)
    {
        //Marge the lines into a list
        $list = $list ?? $this->get_queues();

        //Get the first day of the current month
        $date = $this->date;

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
        $add_price = false;
        foreach ($list as $key => $data) {

            //Check if the item has been delivered, then skip it
            if (in_array($data->product_id, $items_to_keep)) {
                $add_price = true;
                continue;
            }

            $product = wc_get_product($data->product_id); //Get the product object
            $variation = new WC_Product_Variation($data->variation_id); //Get the variation object
            $price = $variation->price; //Get the amount of the variation

            //Add amount if the item is first one, else 0 amount
            if ($add_price) {
                $item = $sub->add_product($product, 1, [
                    'total' => $price
                ]);
                $add_price = false;
            } else {
                $item = $sub->add_product($product, 1, [
                    'total' => 0
                ]);
            }

            //Add item meta to track the month
            wc_add_order_item_meta($item, 'Size', $variation->attributes['pa_size']);
            wc_add_order_item_meta($item, 'Deliverable Date', $date->format('F Y'), true);

            //Update queue data placement
            $query .= "UPDATE " . $table . " SET month_id='" . $date->format('n') . "', year='" . $date->format('Y') . "' WHERE id=$data->id;";

            //Check if the list has end
            if (end($list) != $data)
                $date->modify('+1 month');
        }

        //Get the subscription data
        $sub = $this->get_subscription();

        //Calculate the amounts
        $sub->calculate_totals();

        //Update the subscription date
        $sub->update_dates(array('end' => $date->modify('last day of this month')->format('Y-m-d H:i:s')));

        //Update the queue
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($query);
    }

    public function update_queue_only($list)
    {
        //Queue data parsing
        global $wpdb;
        $table = 'woocommerce_queue_data';
        $date = $this->date;

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

    public function make_charge($sub)
    {
        $stripe = new WC_Stripe_Sepa_Subs_Compat;

        // Get source from order
        $prepared_source = $stripe->prepare_order_source($sub);
        add_filter('wc_stripe_idempotency_key', [$this, 'change_idempotency_key'], 10, 2);

        $request            = $stripe->generate_payment_request($sub, $prepared_source);
        $request['capture'] = 'true';
        $request['amount']  = WC_Stripe_Helper::get_stripe_amount($sub->get_total(), $request['currency']);
        $response           = WC_Stripe_API::request($request);

        if (empty($response->error)) {
            do_action('wc_gateway_stripe_process_payment', $response, $sub);
            $stripe->process_response($response, $sub);
        }
    }

    public function change_idempotency_key($idempotency_key, $request)
    {
        $customer = !empty($request['customer']) ? $request['customer'] : '';
        $source   = !empty($request['source']) ? $request['source'] : $customer;

        return $request['metadata']['order_id'] . '-' . date('Ymd') . '-' . $source;
    }
}
