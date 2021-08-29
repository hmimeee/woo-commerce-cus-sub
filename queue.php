<?php

get_header();
/*
Template Name: My Queue Page
*/

if (is_user_logged_in()) {
} else {
    wp_redirect(home_url('/my-account/'));
    exit();
}

$currentyear = date("Y");
$currentmonth = date("n");
$user = wp_get_current_user();
global $wpdb;
$table = 'woocommerce_queue_data';
$query = "SELECT * FROM $table
WHERE  `customer_id` = $user->ID
AND `status` = 'Active'
ORDER BY year ASC, month_id ASC";
$singlerowresults = $wpdb->get_row($query);
$queues = $wpdb->get_results($query);

$instance = new Custom_Subscription();
$sub = $instance->get_subscription();

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
                                <img src="/wp-content/uploads/2021/06/Scentdoor.png" alt="img">
                                <h4>Welcome</h4>
                                <h3>Get your favorite scents in sequence</h3>
                                <?php if (!empty($singlerowresults) && $sub) : ?>
                                    <a href="/unsubscribe-intend">Cancel Subscription</a>
                                <?php elseif (!empty($singlerowresults)) : ?>
                                    <a href="/subscribe-intend">Subscribe</a>
                                <?php endif ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div id="delivered">
                            <?php foreach ($queues as $key => $single) :
                                if (!empty($items) && !$items[$key]->get_meta('Delivered'))
                                    continue;
                            ?>
                                <div class="single-sidebarproduct" data-id="<?= $single->id; ?>">
                                    <h3>
                                        <?php
                                        $monthNum  = $single->month_id;
                                        $dateObj   = DateTime::createFromFormat('!m', $monthNum);
                                        $monthName = $dateObj->format('F');
                                        echo $monthName . " - " . $single->year;
                                        ?>
                                    </h3>

                                    <div class="flexdiv">
                                        <?php
                                        $product = wc_get_product($single->product_id);
                                        $url = get_permalink($single->product_id);
                                        ?>
                                        <a href="<?= $url; ?>" target="_blank">
                                            <?php $image = wp_get_attachment_image_src(get_post_thumbnail_id($single->product_id), 'single-post-thumbnail'); ?>
                                            <img src="<?php echo $image[0]; ?>" data-id="<?php echo $single->product_id; ?>">
                                            <div class="single-dt">
                                                <h4>
                                                    <?php

                                                    echo $product->get_name();
                                                    ?>
                                                </h4>
                                                <span>Get details</span>
                                                <h5>Size: <?php
                                                            $variation = wc_get_product($single->variation_id);
                                                            echo $variation->attributes['pa_size'];
                                                            ?>
                                                </h5>
                                                <h5>Delivered: Yes</h5>
                                            </div>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach ?>
                        </div>
                        <div id="sortable">
                            <?php foreach ($queues as $key => $single) :
                                if (!empty($items) && $items[$key]->get_meta('Delivered'))
                                    continue;
                            ?>
                                <div class="single-sidebarproduct" data-id="<?= $single->id; ?>">
                                    <h3>
                                        <?php
                                        $monthNum  = $single->month_id;
                                        $dateObj   = DateTime::createFromFormat('!m', $monthNum);
                                        $monthName = $dateObj->format('F');
                                        echo $monthName . " - " . $single->year;
                                        ?>
                                    </h3>

                                    <div class="flexdiv">
                                        <?php
                                        $product = wc_get_product($single->product_id);
                                        $url = get_permalink($single->product_id);
                                        ?>
                                        <a href="<?= $url; ?>" target="_blank">
                                            <?php $image = wp_get_attachment_image_src(get_post_thumbnail_id($single->product_id), 'single-post-thumbnail'); ?>
                                            <img src="<?php echo $image[0]; ?>" data-id="<?php echo $single->product_id; ?>">
                                            <div class="single-dt">
                                                <h4>
                                                    <?php

                                                    echo $product->get_name();
                                                    ?>
                                                </h4>
                                                <span>Get details</span>
                                                <h5>Size:
                                                    <?php

                                                    $variation = wc_get_product($single->variation_id);

                                                    echo $variation->attributes['pa_size'];
                                                    ?>
                                                </h5>
                                            </div>
                                        </a>

                                        <div class="controls-option">
                                            <div class="sideclose">
                                                <button class="delbtn" data-customer="<?= $single->customer_id; ?>" data-id="<?= $single->id; ?>">
                                                    <img src="/wp-content/uploads/2021/08/cancel.png" alt="">
                                                </button>
                                            </div>
                                            <div class="bars">
                                                <a href="">
                                                    <img src="/wp-content/uploads/2021/08/menu.png" alt="">
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            <?php endforeach ?>
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
    $("#sortable").sortable({
        start: function(e, ui) {
            $(this).attr('data-previndex', ui.item.index());
        },
        update: function(e, ui) {
            var dataid = ui.item.data('id');
            var newIndex = ui.item.index();
            var oldIndex = $(this).attr('data-previndex');
            var element_id = ui.item.attr('id');
            var ajaxurl = "/wp-admin/admin-ajax.php";

            $.ajax({
                type: 'POST',
                url: ajaxurl,
                data: {
                    "action": "post_data_drag",
                    "postdataid": dataid,
                    "old_pos": oldIndex,
                    "new_pos": newIndex
                },
                success: function(response) {
                    // location.reload();
                }
            });
        }
    });
    $("#sortable").disableSelection();

    jQuery('.delbtn').click(function() {
        var d = jQuery(this).data('id');
        var c = jQuery(this).data('customer');
        alert("Are you sure you’d like to remove this item");
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
                location.reload();
            }
        });
    });
</script>

<?php get_footer(); ?>