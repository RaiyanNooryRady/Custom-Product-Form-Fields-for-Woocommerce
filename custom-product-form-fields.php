<?php
/*
Plugin Name: Custom Product Form Fields
Description: Adds customizable form fields to WooCommerce products based on product categories. Includes live price update and a visual admin form builder.
Version: 2.0
Author: Michel
*/

if (!defined('ABSPATH')) exit;

// 1. Admin Menu
add_action('admin_menu', function () {
    add_menu_page('Product Form Fields', 'Product Form Fields', 'manage_options', 'cpff_settings', 'cpff_render_settings_page');
});

// 2. Register Settings
add_action('admin_init', function () {
    register_setting('cpff_settings_group', 'cpff_form_data');
});

// 3. Admin Settings Page with Drag-and-Drop Style Builder
function cpff_render_settings_page() {
    $saved = get_option('cpff_form_data', []);
    $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
    ?>
    <div class="wrap">
        <h1>Custom Product Form Fields</h1>
        <form method="post" action="options.php">
            <?php settings_fields('cpff_settings_group'); ?>

            <h2>Select Product Categories</h2>
            <div>
                <?php foreach ($categories as $cat): ?>
                    <label>
                        <input type="checkbox" name="cpff_form_data[categories][]" value="<?php echo esc_attr($cat->term_id); ?>"
                            <?php if (!empty($saved['categories']) && in_array($cat->term_id, $saved['categories'])) echo 'checked'; ?>>
                        <?php echo esc_html($cat->name); ?>
                    </label><br>
                <?php endforeach; ?>
            </div>

            <h2>Form Fields</h2>
            <div id="cpff-fields-container">
                <?php
                if (!empty($saved['fields'])):
                    foreach ($saved['fields'] as $index => $field): ?>
                        <div class="cpff-field-group" style="margin-bottom: 15px; padding: 10px; border: 1px solid #ccc;">
                            <select name="cpff_form_data[fields][<?php echo $index; ?>][type]">
                                <option value="text" <?php selected($field['type'], 'text'); ?>>Text</option>
                                <option value="number" <?php selected($field['type'], 'number'); ?>>Number</option>
                                <option value="date" <?php selected($field['type'], 'date'); ?>>Date</option>
                                <option value="color" <?php selected($field['type'], 'color'); ?>>Color</option>
                                <option value="email" <?php selected($field['type'], 'email'); ?>>Email</option>
                                <option value="dropdown" <?php selected($field['type'], 'dropdown'); ?>>Dropdown</option>
                                <option value="radio" <?php selected($field['type'], 'radio'); ?>>Radio</option>
                                <option value="checkbox" <?php selected($field['type'], 'checkbox'); ?>>Checkbox</option>
                            </select>
                            <input type="text" name="cpff_form_data[fields][<?php echo $index; ?>][label]" value="<?php echo esc_attr($field['label']); ?>" placeholder="Field Label">
                            <textarea name="cpff_form_data[fields][<?php echo $index; ?>][options]" placeholder='Options (label:price, one per line)'><?php echo esc_textarea($field['options'] ?? ''); ?></textarea>
                        </div>
                    <?php endforeach;
                endif;
                ?>
            </div>

            <button type="button" id="cpff-add-field" class="button">+ Add Field</button>
            <br><br>
            <?php submit_button(); ?>
        </form>
    </div>

    <script>
        let fieldIndex = <?php echo isset($saved['fields']) ? count($saved['fields']) : 0; ?>;
        document.getElementById('cpff-add-field').addEventListener('click', function () {
            const container = document.getElementById('cpff-fields-container');
            const div = document.createElement('div');
            div.className = 'cpff-field-group';
            div.style.marginBottom = '15px';
            div.style.padding = '10px';
            div.style.border = '1px solid #ccc';
            div.innerHTML = `
                <select name="cpff_form_data[fields][${fieldIndex}][type]">
                    <option value="text">Text</option>
                    <option value="number">Number</option>
                    <option value="date">Date</option>
                    <option value="color">Color</option>
                    <option value="email">Email</option>
                    <option value="dropdown">Dropdown</option>
                    <option value="radio">Radio</option>
                    <option value="checkbox">Checkbox</option>
                </select>
                <input type="text" name="cpff_form_data[fields][${fieldIndex}][label]" placeholder="Field Label">
                <textarea name="cpff_form_data[fields][${fieldIndex}][options]" placeholder='Options (label:price, one per line)'></textarea>
            `;
            container.appendChild(div);
            fieldIndex++;
        });
    </script>
    <?php
}

// 4. Enqueue jQuery for live price updates
add_action('wp_enqueue_scripts', function () {
    if (is_product()) {
        wp_enqueue_script('jquery');
        wp_add_inline_script('jquery', "
            jQuery(document).ready(function($) {
                const priceEl = $('.product .price .amount').first();
                let basePrice = parseFloat(priceEl.text().replace(/[^0-9.]/g, '')) || 0;

                function updateTotalPrice() {
                    let extra = 0;
                    $('.cpff-custom-form').find('input, select').each(function() {
                        const \$el = $(this);
                        const type = \$el.attr('type');
                        if (type === 'checkbox' || type === 'radio') {
                            if (\$el.is(':checked')) {
                                extra += parseFloat(\$el.data('price')) || 0;
                            }
                        } else if (\$el.is('select')) {
                            const selected = \$el.find('option:selected');
                            extra += parseFloat(selected.data('price')) || 0;
                        }
                    });
                    const newTotal = (basePrice + extra).toFixed(2);
                    priceEl.text('$' + newTotal);
                }

                $(document).on('change', '.cpff-custom-form input, .cpff-custom-form select', function () {
                    updateTotalPrice();
                });

                updateTotalPrice();
            });
        ");
    }
});

// 5. Render form on product page
add_action('woocommerce_before_add_to_cart_button', function () {
    global $product;
    $form_data = get_option('cpff_form_data', []);
    if (empty($form_data['categories']) || empty($form_data['fields'])) return;

    $cat_ids = $product->get_category_ids();
    if (!array_intersect($form_data['categories'], $cat_ids)) return;

    echo '<div class="cpff-custom-form">';
    wp_nonce_field('cpff_form_nonce', 'cpff_form_nonce_field');

    foreach ($form_data['fields'] as $i => $field) {
        $name = 'cpff_' . $i;
        $options = [];

        if (!empty($field['options'])) {
            foreach (explode(PHP_EOL, $field['options']) as $line) {
                [$optLabel, $optPrice] = array_map('trim', explode(':', $line) + [null, 0]);
                $options[] = ['label' => $optLabel, 'price' => (float)$optPrice];
            }
        }

        echo '<p><label>' . esc_html($field['label']) . '</label><br>';
        switch ($field['type']) {
            case 'text':
            case 'email':
            case 'number':
            case 'date':
            case 'color':
                echo '<input type="' . esc_attr($field['type']) . '" name="' . esc_attr($name) . '">';
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
                    echo '<label><input type="' . esc_attr($field['type']) . '" name="' . esc_attr($name) . ($field['type'] === 'checkbox' ? '[]' : '') . '" value="' . esc_attr($opt['label']) . '" data-price="' . esc_attr($opt['price']) . '">' . esc_html($opt['label']) . ' (+$' . $opt['price'] . ')</label><br>';
                }
                break;
        }
        echo '</p>';
    }

    echo '</div>';
});

// 6. Save to cart
add_filter('woocommerce_add_cart_item_data', function ($cart_item_data, $product_id) {
    if (!isset($_POST['cpff_form_nonce_field']) || !wp_verify_nonce($_POST['cpff_form_nonce_field'], 'cpff_form_nonce')) return $cart_item_data;

    $form_data = get_option('cpff_form_data', []);
    if (empty($form_data['fields'])) return $cart_item_data;

    $extra_price = 0;
    $custom_data = [];

    foreach ($form_data['fields'] as $i => $field) {
        $name = 'cpff_' . $i;
        if (!isset($_POST[$name])) continue;

        $value = $_POST[$name];
        $custom_data[$field['label']] = $value;

        $options = [];
        if (!empty($field['options'])) {
            foreach (explode(PHP_EOL, $field['options']) as $line) {
                [$optLabel, $optPrice] = array_map('trim', explode(':', $line) + [null, 0]);
                $options[] = ['label' => $optLabel, 'price' => (float)$optPrice];
            }
        }

        if (is_array($value)) {
            foreach ($value as $val) {
                foreach ($options as $opt) {
                    if ($opt['label'] === $val) $extra_price += $opt['price'];
                }
            }
        } else {
            foreach ($options as $opt) {
                if ($opt['label'] === $value) $extra_price += $opt['price'];
            }
        }
    }

    $cart_item_data['cpff_fields'] = $custom_data;
    $cart_item_data['cpff_extra_price'] = $extra_price;
    $cart_item_data['unique_key'] = md5(microtime() . rand());

    return $cart_item_data;
}, 10, 2);

// 7. Display custom data in cart & checkout
add_filter('woocommerce_get_item_data', function ($item_data, $cart_item) {
    if (!empty($cart_item['cpff_fields'])) {
        foreach ($cart_item['cpff_fields'] as $label => $value) {
            $item_data[] = ['name' => $label, 'value' => is_array($value) ? implode(', ', $value) : $value];
        }
    }
    return $item_data;
}, 10, 2);

// 8. Adjust item price
add_action('woocommerce_before_calculate_totals', function ($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    foreach ($cart->get_cart() as $cart_item) {
        if (isset($cart_item['cpff_extra_price'])) {
            $price = $cart_item['data']->get_price();
            $cart_item['data']->set_price($price + $cart_item['cpff_extra_price']);
        }
    }
});

// 9. Save to order
add_action('woocommerce_checkout_create_order_line_item', function ($item, $cart_item_key, $values, $order) {
    if (!empty($values['cpff_fields'])) {
        foreach ($values['cpff_fields'] as $label => $value) {
            $item->add_meta_data($label, is_array($value) ? implode(', ', $value) : $value);
        }
    }
}, 10, 4);
