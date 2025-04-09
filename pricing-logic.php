<?php
// Save to cart
add_filter('woocommerce_add_cart_item_data', function ($cart_item_data, $product_id) {
    $forms = get_option('cpff_forms', []);
    if (!is_array($forms)) {
        $forms = [];
    }
    $forms = array_values($forms);
    $custom_data = [];
    $extra_price = 0;

    foreach ($forms as $formIndex => $form) {
        if (!isset($_POST["cpff_nonce_field_$formIndex"]) || !wp_verify_nonce($_POST["cpff_nonce_field_$formIndex"], "cpff_form_nonce_$formIndex"))
            continue;
        if (!isset($form['fields']) || !is_array($form['fields']))
            continue;

        foreach ($form['fields'] as $i => $field) {
            $name = "cpff_{$formIndex}_{$i}";
            if (!isset($_POST[$name]))
                continue;

            $value = $_POST[$name];
            $custom_data[$field['label']] = $value;

            $options = [];
            if (!empty($field['options'])) {
                foreach (explode("\n", $field['options']) as $line) {
                    [$label, $price] = array_map('trim', explode(':', $line) + [null, 0]);
                    if ($label)
                        $options[] = ['label' => $label, 'price' => (float) $price];
                }
            }

            if ($field['type'] === 'number' && is_numeric($value)) {
                $val = floatval($value);
                if (!empty($field['multiply_base'])) {
                    $product = wc_get_product($product_id);
                    $base = $product->get_sale_price() ?: $product->get_regular_price();
                    $extra_price += $base * ($val - 1);
                } elseif (!empty($field['custom_price'])) {
                    $extra_price += floatval($field['custom_price']) * $val;
                }
            } elseif (is_array($value)) {
                foreach ($value as $val) {
                    foreach ($options as $opt) {
                        if ($opt['label'] === $val)
                            $extra_price += $opt['price'];
                    }
                }
            } else {
                foreach ($options as $opt) {
                    if ($opt['label'] === $value)
                        $extra_price += $opt['price'];
                }
            }
        }
    }

    if (!empty($custom_data)) {
        $cart_item_data['cpff_fields'] = $custom_data;
        $cart_item_data['cpff_extra_price'] = $extra_price;
        $cart_item_data['unique_key'] = md5(microtime() . rand());
    }

    return $cart_item_data;
}, 10, 2);

// Display in cart/checkout
add_filter('woocommerce_get_item_data', function ($item_data, $cart_item) {
    if (!empty($cart_item['cpff_fields'])) {
        foreach ($cart_item['cpff_fields'] as $label => $value) {
            $item_data[] = ['name' => $label, 'value' => is_array($value) ? implode(', ', $value) : $value];
        }
    }
    return $item_data;
}, 10, 2);

// Adjust product price (do not multiply by quantity)
add_action('woocommerce_before_calculate_totals', function ($cart) {
    if (is_admin() && !defined('DOING_AJAX'))
        return;
    foreach ($cart->get_cart() as $cart_item) {
        if (isset($cart_item['cpff_extra_price'])) {
            $base_price = $cart_item['data']->get_sale_price() ?: $cart_item['data']->get_regular_price();
            $cart_item['data']->set_price($base_price + $cart_item['cpff_extra_price']);
        }
    }
});

// Save to order
add_action('woocommerce_checkout_create_order_line_item', function ($item, $cart_item_key, $values, $order) {
    if (!empty($values['cpff_fields'])) {
        foreach ($values['cpff_fields'] as $label => $value) {
            $item->add_meta_data($label, is_array($value) ? implode(', ', $value) : $value);
        }
    }
}, 10, 4);