jQuery(document).ready(function($) {
    const saleEl = $('.product .price ins .amount').first();
    const baseEl = $('.product .price .amount').first();
    let basePrice = saleEl.length ? parseFloat(saleEl.text().replace(/[^0-9.]/g, '')) : parseFloat(baseEl.text().replace(/[^0-9.]/g, ''));

    function updateTotalPrice() {
        let extra = 0;

        $('.cpff-custom-form').find('input, select').each(function () {
            const $el = $(this);
            const type = $el.attr('type');

            if ((type === 'checkbox' || type === 'radio') && $el.is(':checked')) {
                extra += parseFloat($el.data('price')) || 0;

            } else if ($el.is('select')) {
                extra += parseFloat($el.find('option:selected').data('price')) || 0;

            } else if (type === 'number') {
                const val = parseFloat($el.val()) || 0;
                const multiply = parseInt($el.data('multiply'));
                const custom = parseFloat($el.data('custom')) || 0;

                if (multiply === 1 && val > 1) {
                    extra += basePrice * (val - 1);

                } else if (multiply === 0 && val > 0) {
                    extra += custom * val;
                }
            }
        });

        const newTotal = (basePrice + extra).toFixed(2);
        if (saleEl.length) saleEl.text('$' + newTotal);
        else baseEl.text('$' + newTotal);
    }

    $(document).on('input change', '.cpff-custom-form input, .cpff-custom-form select', updateTotalPrice);
    updateTotalPrice();
});
