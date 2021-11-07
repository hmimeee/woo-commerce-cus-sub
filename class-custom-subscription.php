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
     * @param string|array $order Ordering data as `ASC` or `DESC`
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
                ORDER BY position ASC";
        } elseif (is_numeric($single) && $order == 'product') {
            $query = "SELECT * FROM $table
                WHERE  `customer_id` = $this->user_id
                AND `product_id` = $single
                ORDER BY position ASC";
        } elseif ($order == 'row') {
            $query = "SELECT * FROM $table 
            WHERE  `customer_id` = $this->user_id
            AND  `id` = $single
            ORDER BY position ASC";
        } elseif ($order == 'position') {
            $query = "SELECT * FROM $table 
            WHERE  `customer_id` = $this->user_id
            AND `position` = $single
            ORDER BY position ASC";
        } else {
            $query = "SELECT * FROM $table
                WHERE  `customer_id` = $this->user_id
                ORDER BY position ASC";
        }

        $result = $wpdb->get_results($query);
        if ((!is_string($single) && $single == true) || $order == 'row')
            return reset($result);

        return $result;
    }

    public function empty_queue()
    {
        //Queue data parsing
        global $wpdb;
        $table = 'woocommerce_queue_data';
        $query = "DELETE FROM $table WHERE  `customer_id` = $this->user_id";

        return $wpdb->query($query);
    }

    /**
     * Delete the specific queue data
     * 
     * @param int $id ID of the queue data
     * @return bool
     */
    public function delete_data($id, $position = null)
    {
        //Queue data parsing
        global $wpdb;
        $table = 'woocommerce_queue_data';
        $id = intval($id);

        $query = "DELETE FROM $table WHERE `id` = $id";

        if ($position)
            $query = "DELETE FROM $table WHERE `position` = $id";

        return $wpdb->query($query);
    }

    /**
     * Update the specific queue data
     * 
     * @param int $id ID of the queue data
     * @param array $data Data to be updated fo the queue. Array should be associative
     * @return bool
     */
    public function update_data(int $id, array $data)
    {
        global $wpdb;
        $table = 'woocommerce_queue_data';
        $wpdb->update($table, $data, array('id' => $id));

        return true;
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
        $this->update_subscription_items($sub, false, true);

        //Calculate the amounts
        $sub->calculate_totals();

        //Status update
        $sub->update_status('active');

        return $sub;
    }

    public function update_subscription_items($sub, $has_delivery = false, $is_new = false)
    {
        // Add product to the subscription
        $queues = $this->get_queues();
        $position = reset($queues)->position ?? 1;
        $fposition = reset($queues)->position;

        if ($has_delivery && !empty($sub->get_items())) {
            $items = array_map(function ($itm) {
                return $itm->get_meta('Delivered') == 'Yes' ? $itm : null;
            }, $sub->get_items());

            $items = array_filter($items);
            $date = DateTime::createFromFormat('F Y', end($items)->get_meta('Deliverable Date'));
            $date->modify('+1 month');

            $parent = wc_get_order($sub->parent_id);
            $parent_items = array_values($parent->get_items());
        } elseif (!empty($sub->get_items())) {
            $date = (new DateTime())->modify('+1 month');
        } else {
            $date = new DateTime();
        }

        foreach ($queues as $key => $queue) {
            $product = wc_get_product($queue->product_id);
            $variation = new WC_Product_Variation($queue->variation_id);

            //Check if product exist
            if (!$product)
                continue;

            if ($queue->position != $position) {
                $date->modify('+1 month');
                $position = $queue->position;
            }

            $item = $sub->add_product($product, 1, [
                'month' => $date->format('F Y'),
                'total' => $variation->price
            ]); //Set the product for the subscription with price for first product

            wc_add_order_item_meta($item, 'Size', $variation->attributes['pa_size']);
            wc_add_order_item_meta($item, 'Deliverable Date', $date->format('F Y'), true);

            if ($has_delivery && isset($parent_items[$key])) {
                wc_add_order_item_meta($item, 'Delivered', 'Yes', true);
            }

            if ($is_new && $fposition == $queue->position) {
                wc_add_order_item_meta($item, 'Delivered', 'Yes', true);
            }
        }

        //Update the dates
        $date->modify('+1 month');
        $sub->update_dates(array('end' => $date->format('Y-m-d H:i:s')));
    }

    public function add_subscription_item($sub, $product_id, $variation_id, $date)
    {
        $product = wc_get_product($product_id);
        $variation = new WC_Product_Variation($variation_id);

        $item = $sub->add_product($product, 1, [
            'month' => $date->format('F Y'),
            'total' => $variation->price
        ]); //Set the product for the subscription with price for first product

        wc_add_order_item_meta($item, 'Size', $variation->attributes['pa_size']);
        wc_add_order_item_meta($item, 'Deliverable Date', $date->format('F Y'), true);

        //Calculate the amounts
        $sub->calculate_totals();

        //Update the dates
        $end_date = $date->modify('last day of this month')->format('Y-m-d H:i:s');
        $sub->update_dates(array('end' => $end_date));

        return true;
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

        if (empty($results)) {
            $insert = $wpdb->insert(
                'woocommerce_queue_data',
                array(
                    'product_id' => $product_id,
                    'variation_id' => $variation_id,
                    'customer_id' => $this->user->ID,
                    'position' => 1
                ),
                array(
                    '%s'
                )
            );
        } else {
            $exist_product = end($results);
            $count_data = count($this->get_queues($exist_product->position, 'position'));

            $insert = $wpdb->insert(
                'woocommerce_queue_data',
                array(
                    'product_id' => $product_id,
                    'variation_id' => $variation_id,
                    'customer_id' => $this->user->ID,
                    'position' => $count_data > 1 ? $exist_product->position + 1 : $exist_product->position
                ),
                array(
                    '%s'
                )
            );
        }

        //Get the subscription data
        $sub = $this->get_subscription();
        if ($sub) {
            $items = $sub->get_items();
            $last_item = end($items);

            if (count($items) > 1) {
                $last_prev_item = prev($items);
                $last_date = $last_item->get_meta('Deliverable Date');
                $last_prev_date = $last_prev_item->get_meta('Deliverable Date');
                $date = DateTime::createFromFormat('F Y', $last_date);

                if ($last_date == $last_prev_date)
                    $date->modify('+1 month');
            } elseif ($last_item) {
                $last_date = $last_item->get_meta('Deliverable Date');
                $date = DateTime::createFromFormat('F Y', $last_date)->modify('+1 month');
            } else {
                $date = new DateTime();
            }

            $this->add_subscription_item($sub, $product_id, $variation_id, $date);
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

    public function make_charge($order, $source = null)
    {
        $stripe = new WC_Stripe_Order_Handler;

        // Get source from order
        $prepared_source = $source ?? $stripe->prepare_order_source($order);
        add_filter('wc_stripe_idempotency_key', [$this, 'change_idempotency_key'], 10, 2);

        $request            = $stripe->generate_payment_request($order, $prepared_source);
        $request['capture'] = 'true';
        $request['amount']  = WC_Stripe_Helper::get_stripe_amount($order->get_total(), $request['currency']);
        $response           = WC_Stripe_API::request($request);

        if (empty($response->error)) {
            do_action('wc_gateway_stripe_process_payment', $response, $order);
            $stripe->process_response($response, $order);
        }

        return true;
    }

    public function change_idempotency_key($idempotency_key, $request)
    {
        $customer = !empty($request['customer']) ? $request['customer'] : '';
        $source   = !empty($request['source']) ? $request['source'] : $customer;

        return $request['metadata']['order_id'] . '-' . date('Ymd') . '-' . $source;
    }
}
