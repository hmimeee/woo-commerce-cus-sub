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

    public function get_queues($single = false)
    {
        //Queue data parsing
        global $wpdb;
        $table = 'woocommerce_queue_data';
        $allQuery = "SELECT * FROM $table
        WHERE  `customer_id` = $this->user_id
        AND `status` = 'Active'
        ORDER BY year ASC, month_id ASC";

        if ($single)
            return $wpdb->get_row($allQuery);

        return $wpdb->get_results($allQuery);
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
        $sub->update_status('active');

        return true;
    }

    public function update_subscription_items($sub)
    {
        //Remove subscription items
        $sub->remove_order_items('line_item');
        // $items = $sub->get_items();

        // $items_to_remove = [];
        // foreach ($items as $key => $item) {
        //     $metas = wcs_get_order_item_meta($item)->get_formatted();
        //     foreach ($metas as $key => $meta) {
        //         if($meta['key'] =='Delivered')
        //         continue;

        //         $items_to_remove [] = null;
        //     }
        // }

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
        $next_payment = (clone $date)->modify('+1 month')->format('Y-m-d H:i:s');
        $end_date = ((clone $date)->modify('+' . count($queues) . ' month'))->modify('last day of this month')->format('Y-m-d H:i:s');
        $sub->update_dates(array('next_payment' => $next_payment,  'end' => $end_date));
    }

    /**
     * Update the subscription
     * 
     * @return void
     */
    public function update_subscription()
    {
        $user = wp_get_current_user();
        $sub = reset(wcs_get_users_subscriptions($user->ID));

        $this->update_subscription_items($sub);
    }

    public function get_subscription($user = null)
    {
        $user = $user ?? wp_get_current_user();
        $sub = reset(wcs_get_users_subscriptions($user->ID));

        if (wcs_user_has_subscription($user->ID, '', 'active'))
            return $sub;

        return null;
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
}
