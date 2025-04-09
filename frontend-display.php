<?php
// === FRONTEND & PRICE LOGIC ===

// Enqueue frontend script for live price update
add_action('wp_enqueue_scripts', function () {
    if (is_product()) {
        wp_enqueue_script('jquery');

        wp_add_inline_script('jquery', "
            jQuery(document).ready(function($) {
                const saleEl = $('.product .price ins .amount').first();
                const baseEl = $('.product .price .amount').first();
                let basePrice = saleEl.length ? parseFloat(saleEl.text().replace(/[^0-9.]/g, '')) : parseFloat(baseEl.text().replace(/[^0-9.]/g, ''));

                function updateTotalPrice() {
                    let extra = 0;

                    $('.cpff-custom-form').find('input, select').each(function () {
                        const \$el = $(this);
                        const type = \$el.attr('type');

                        if ((type === 'checkbox' || type === 'radio') && \$el.is(':checked')) {
                            extra += parseFloat(\$el.data('price')) || 0;

                        } else if (\$el.is('select')) {
                            extra += parseFloat(\$el.find('option:selected').data('price')) || 0;

                        } else if (type === 'number') {
                            const val = parseFloat(\$el.val()) || 0;
                            const multiply = parseInt(\$el.data('multiply'));
                            const custom = parseFloat(\$el.data('custom')) || 0;

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
        ");
    }
});


// Render form on single product page
add_action('woocommerce_before_add_to_cart_button', function () {
    global $product;
    $forms = get_option('cpff_forms', []);
    if (!is_array($forms)) {
        $forms = [];
    }
    $forms = array_values($forms);
    $product_categories = $product->get_category_ids();

    foreach ($forms as $formIndex => $form) {
        if (!isset($form['categories'], $form['fields']))
            continue;
        if (array_intersect($form['categories'], $product_categories)) {
            echo '<div class="cpff-custom-form" id="' . esc_attr($form['form_id'] ?? 'cpff_custom_form_' . $formIndex) . '">';
            echo '<h4 style="font-weight:bold;">' . esc_html($form['name']) . '</h4>'; // ✅ Show Form Name
            wp_nonce_field('cpff_form_nonce_' . $formIndex, 'cpff_nonce_field_' . $formIndex);

            foreach ($form['fields'] as $i => $field) {
                $name = "cpff_{$formIndex}_{$i}";
                echo '<p><label>' . esc_html($field['label']) . '</label><br>';
                $options = [];

                if (!empty($field['options'])) {
                    foreach (explode("\n", $field['options']) as $line) {
                        [$label, $price] = array_map('trim', explode(':', $line) + [null, 0]);
                        if ($label)
                            $options[] = ['label' => $label, 'price' => (float) $price];
                    }
                }

                switch ($field['type']) {
                    case 'text':
                    case 'email':
                    case 'date':
                    case 'color':
                        echo '<input type="' . esc_attr($field['type']) . '" name="' . esc_attr($name) . '">';
                        break;
                    case 'number':
                        $mult = !empty($field['multiply_base']) ? '1' : '0';
                        $custom_price = isset($field['custom_price']) ? (float) $field['custom_price'] : 0;
                        echo '<input type="number" name="' . esc_attr($name) . '" data-multiply="' . esc_attr($mult) . '" data-custom="' . esc_attr($custom_price) . '" min="1" value="1">';
                        break;
                    case 'dropdown':
                        echo '<select name="' . esc_attr($name) . '">';
                        foreach ($options as $opt) {
                            echo '<option value="' . esc_attr($opt['label']) . '" data-price="' . esc_attr($opt['price']) . '">' . esc_html($opt['label']) . ' (+$' . $opt['price'] . ')</option>';
                        }
                        echo '</select>';
                        break;
                    case 'radio':
                    case 'checkbox':
                        foreach ($options as $opt) {
                            echo '<label><input type="' . esc_attr($field['type']) . '" name="' . esc_attr($name) . ($field['type'] === 'checkbox' ? '[]' : '') . '" value="' . esc_attr($opt['label']) . '" data-price="' . esc_attr($opt['price']) . '"> ' . esc_html($opt['label']) . ' (+$' . $opt['price'] . ')</label><br>';
                        }
                        break;
                }
                echo '</p>';
            }

            echo '</div>';
        }
    }
});