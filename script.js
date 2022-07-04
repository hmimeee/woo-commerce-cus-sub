$('#upgrade').click(function () {
    $btn = $(this);
    $btn.append(' <i class="fa fa-circle-notch fa-spin"></i>');
    $.ajax({
        url: '/wp-admin/admin-ajax.php',
        type: 'post',
        data: {
            'action': 'upgrade_subscription',
            '_wpnonce': '<?= wp_create_nonce() ?>'
        },
        success: function (res) {
            if (res.success) {
                $btn.hide();
                $select = '<select id="variation" style="padding:8px 80px; vertical-align: none;">';
                for (let i = 0; i < res.data.length; i++) {
                    const variant = res.data[i];
                    $select += '<option value="' + variant.size + '" ' + variant.selected + '>' + variant.size + ' (' + variant.price + '$)</option>';
                }
                $select += '</select>';

                if (res.data.length) {
                    $btn.next('div.form-group').html($select);
                    $btn.next('div.form-group').append('<button id="confirm-upgrade" class="m-2 btn btn-primary btn-sm">Confirm</button>');
                    $btn.next('div.form-group').append('<button id="cancel-upgrade" class="m-2 btn btn-secondary btn-sm">Cancel</button>');
                    $btn.next('div.form-group').append('<button id="hard-upgrade" class="m-2 btn btn-danger btn-sm">Suspend Subscription</button>');
                    $btn.next('div.form-group').append('<p class="text-left"><b>Note:</b> Suspending subscription will clear the queue and you have to prepare the queue again for new subscription.');
                }
            }
        }
    })
});

$('body').on('click', '#cancel-upgrade', function () {
    $can = $(this);
    $upgrade = $can.parents().find('#upgrade');
    $upgrade.html('Upgrade/Downgrade');
    $upgrade.show();
    $can.parent().html('');
});

$('body').on('click', '#hard-upgrade', function (e) {
    e.preventDefault();
    location.href = '/unsubscribe-intend?suspend=yes'
})

$('body').on('click', '#confirm-upgrade', function () {
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
        success: function (res) {
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
    start: function (e, info) {
        info.item.siblings(".selected").appendTo(info.item);
        $prevPos = $(e.target);
        $has_item = $prevPos.find('.card').length;
        $prevPos.append('<div id="blank-prop" style="height:134px;border: 1px dotted gray"></div>');
    },
    beforeStop() {
        $('#blank-prop').remove();
    },
    over: function (e, info) {
        $hover = $(e.target);
        if ($hover.hasClass('queue-new')) {
            $hover.children().hide();
        } else {
            $('.queue-new').children().show();
        }
    },
    stop: function (e, info) {
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
            success: function (res) {
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

jQuery('.delbtn').click(function (e) {
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
        success: function (response) {
            if (response.status)
                location.reload();
        }
    });
});