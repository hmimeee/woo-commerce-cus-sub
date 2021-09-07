<?php

class Custom_Subscription
{
    public $user_id, $user, $date;

    public function __construct()
    {
        $this->user_id = get_current_user_id();
        $this->user = get_user_by('ID', $this->user_id);

        //Date object
        $this->date = (new DateTime())->modify('first day of this month');
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
                AND `status` = 'Active'";
        } elseif (is_numeric($single) && $order == 'product') {
            $query = "SELECT * FROM $table
                WHERE  `customer_id` = $this->user_id
                AND `product_id` = $single
                AND `status` = 'Active'";
        } elseif ($order == 'row') {
            $query = "SELECT * FROM $table 
            WHERE  `customer_id` = $this->user_id
            AND  `id` = $single";
        } else {
            $query = "SELECT * FROM $table
                WHERE  `customer_id` = $this->user_id
                AND `status` = 'Active'
                ORDER BY year $order, month_id $order";
        }

        if ($single)
            return $wpdb->get_row($query);

        return $wpdb->get_results($query);
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
    public function get_amount($product_id): float
    {
        //Select the package
        if (has_term('luxury', 'product_cat', $product_id)) {
            $package = 'luxury';
        } else {
            $package = 'regular';
        }

        //Get the package amount for the subscription
        $prices = array(
            'regular' => 9.99,
            'luxury' => 14.99
        );
        $amount = $prices[$package];

        return floatVal($amount);
    }

    /**
     * Create subscription against an order
     * 
     * @param $order_id Order ID to create the subscription against it
     * @return bool
     */
    public function create_subscription($order_id): bool
    {
        $details = $this->order_details();

        //Create the subscription
        $sub = wcs_create_subscription(array(
            'order_id' => $order_id,
            'status' => 'pending', // Status should be initially set to pending
            'billing_period' => 'month',
            'billing_interval' => 1
        ));

        //Check if subscription created
        if (is_wp_error($sub))
            wp_redirect('queue');

        //Set the addresses for the subscription
        $sub->set_address($details->billing_address, 'billing');
        $sub->set_address($details->shipping_address, 'shipping');

        //Add items to the subscription
        $this->update_subscription_items($sub);

        //Calculate the amounts
        $sub->calculate_totals();

        //Status update
        // $sub->update_status('active');

        //Activate the subscription
        WC_Subscriptions_Manager::activate_subscriptions_for_order($order_id);

        return true;
    }

    public function update_subscription_items($sub)
    {
        // Add product to the subscription
        $queues = $this->get_queues();
        $date = clone $this->date;
        $count = 0;
        foreach ($queues as $queue) {
            $product = wc_get_product($queue->product_id);

            //Check if product exist
            if (!$product)
                continue;

            if ($count == 0) {
                $item = $sub->add_product($product, 1, ['total' => $this->get_amount($queue->product_id), 'month' => $date->format('F Y')]); //Set the product for the subscription with price for first product
                wc_add_order_item_meta($item, 'Deliverable Date', $date->format('F Y'), true);
            } else {
                $item = $sub->add_product($product, 1, ['total' => 0]); //Set the product for the subscription
                wc_add_order_item_meta($item, 'Deliverable Date', $date->format('F Y'), true);
            }

            $count++;
            $date->modify('+1 month');
        }

        //Update the dates
        $date = clone $this->date;
        $next_payment = (new DateTime())->modify('+1 minute')->format('Y-m-d H:i:s');
        $end_date = ((clone $date)->modify('+' . count($queues) . ' month'))->modify('last day of this month')->format('Y-m-d H:i:s');
        $sub->update_dates(array('next_payment' => $next_payment,  'end' => $end_date));
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
        $sub = end($sub_array);

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
}
