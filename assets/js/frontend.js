(function ($) {
    "use strict";

    $(document).on('change', '.shop_table .quantity .qty', function (e) {
        var _val = $(this).val(),
            _prod_id = $(this).parents('tr').find('a.remove').data('product_id'),
            gift_items = $('.shop_table [data-gift_for=' + _prod_id + ']');

        gift_items.each(function (e, i) {
            $(this).find('span').text(_val);
            $(this).find('[type=hidden]').val(_val);
        })
    });

})(jQuery);