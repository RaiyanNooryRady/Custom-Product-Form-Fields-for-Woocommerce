<?php
/*
Plugin Name: Custom Product Form Fields PRO
Description: WooCommerce plugin with proper DOM logic for dynamic field creation, form saving, live price update, and multiple forms.
Version: 1.0
Author: Raiyan Noory
*/

if (!defined('ABSPATH'))
    exit;

add_action('admin_menu', function () {
    add_menu_page('Product Form Fields', 'Product Form Fields', 'manage_options', 'cpff_forms', 'cpff_render_forms_page');
});

add_action('admin_init', function () {
    register_setting('cpff_forms_group', 'cpff_forms');
});

function cpff_render_forms_page()
{
    $forms = get_option('cpff_forms', []);
    if (!is_array($forms))
        $forms = [];
    $forms = array_values($forms);
    $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
    ?>
    <div class="wrap">
        <h1>Custom Product Forms</h1>
        <form method="post" action="options.php">
            <?php settings_fields('cpff_forms_group'); ?>
            <div id="cpff-forms-wrapper">
                <?php foreach ($forms as $formIndex => $form): ?>
                    <?php cpff_render_form($formIndex, $form, $categories); ?>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button" id="cpff-add-form">+ Add New Form</button><br><br>
            <?php submit_button(); ?>
        </form>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            let formIndex = document.querySelectorAll('.cpff-form').length;
            document.getElementById('cpff-add-form').addEventListener('click', function () {
                const wrapper = document.getElementById('cpff-forms-wrapper');
                const formDiv = document.createElement('div');
                formDiv.className = 'cpff-form';
                formDiv.style.border = '1px solid #ccc';
                formDiv.style.padding = '10px';
                formDiv.style.marginBottom = '20px';
                const html = document.createElement('div');
                html.innerHTML = `
            <label>Form Name:</label>
            <input type="text" name="cpff_forms[${formIndex}][name]" style="width:200px;"><br>
            <strong>Assign to Categories:</strong><br>
            ${<?php
            $cat_html = '';
            foreach ($categories as $cat) {
                $cat_html .= "<label><input type='checkbox' name='__FORM_CAT__' value='{$cat->term_id}'> {$cat->name}</label><br>";
            }
            echo json_encode($cat_html);
            ?>.replace(/__FORM_CAT__/g, 'cpff_forms[' + formIndex + '][categories][]')}
            <h4>Form Fields:</h4>
            <div class="cpff-fields" data-form="${formIndex}"></div>
            <button type="button" class="button add-field" data-form="${formIndex}">+ Add Field</button>
        `;
                formDiv.appendChild(html);
                wrapper.appendChild(formDiv);
                formIndex++;
            });

            document.addEventListener('click', function (e) {
                if (e.target.classList.contains('add-field')) {
                    const formId = e.target.dataset.form;
                    const wrapper = document.querySelector('.cpff-fields[data-form="' + formId + '"]');
                    const fieldIndex = wrapper.children.length;

                    const field = document.createElement('div');
                    field.className = 'cpff-field';
                    field.style.border = '1px solid #ddd';
                    field.style.padding = '10px';
                    field.style.marginBottom = '10px';

                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.className = 'remove-field button';
                    removeBtn.innerText = '❌';
                    removeBtn.addEventListener('click', () => field.remove());

                    const typeSelect = document.createElement('select');
                    typeSelect.name = `cpff_forms[${formId}][fields][${fieldIndex}][type]`;
                    ['text', 'number', 'date', 'color', 'email', 'dropdown', 'radio', 'checkbox'].forEach(type => {
                        const opt = document.createElement('option');
                        opt.value = type;
                        opt.textContent = type;
                        typeSelect.appendChild(opt);
                    });

                    const labelInput = document.createElement('input');
                    labelInput.type = 'text';
                    labelInput.name = `cpff_forms[${formId}][fields][${fieldIndex}][label]`;
                    labelInput.placeholder = 'Label';

                    const optionsTextarea = document.createElement('textarea');
                    optionsTextarea.name = `cpff_forms[${formId}][fields][${fieldIndex}][options]`;
                    optionsTextarea.placeholder = 'Options (label:price)';

                    const multiplyCheckbox = document.createElement('label');
                    multiplyCheckbox.innerHTML = `
                <input type="checkbox" name="cpff_forms[${formId}][fields][${fieldIndex}][multiply_base]">
                Multiply by product price
            `;
                    const customPriceInput = document.createElement('input');
                    customPriceInput.type = 'number';
                    customPriceInput.step = 'any';
                    customPriceInput.min = '0';
                    customPriceInput.name = `cpff_forms[${formId}][fields][${fieldIndex}][custom_price]`;
                    customPriceInput.placeholder = 'Custom Price (used if multiply unchecked)';

                    field.appendChild(customPriceInput);
                    field.appendChild(removeBtn);
                    field.appendChild(document.createElement('br'));
                    field.appendChild(typeSelect);
                    field.appendChild(labelInput);
                    field.appendChild(optionsTextarea);
                    field.appendChild(multiplyCheckbox);
                    wrapper.appendChild(field);
                }
                if (e.target.classList.contains('cpff-delete-form')) {
                    if (confirm('Are you sure you want to delete this entire form?')) {
                        e.target.closest('.cpff-form').remove();
                    }
                }
            });
        });
    </script>
    <?php
}

function cpff_render_form($formIndex, $form, $categories)
{
    ?>
    <div class="cpff-form" style="border:1px solid #ccc;padding:10px;margin-bottom:20px;">
        <label>Form Name:</label>
        <input type="text" name="cpff_forms[<?php echo $formIndex; ?>][name]" value="<?php echo esc_attr($form['name']); ?>"
            style="width:200px;"><br>
        <strong>Assign to Categories:</strong><br>
        <?php foreach ($categories as $cat): ?>
            <label>
                <input type="checkbox" name="cpff_forms[<?php echo $formIndex; ?>][categories][]"
                    value="<?php echo esc_attr($cat->term_id); ?>" <?php if (!empty($form['categories']) && in_array($cat->term_id, $form['categories']))
                           echo 'checked'; ?>>
                <?php echo esc_html($cat->name); ?>
            </label><br>
        <?php endforeach; ?>
        <h4>Form Fields:</h4>
        <div class="cpff-fields" data-form="<?php echo $formIndex; ?>">
            <?php if (!empty($form['fields'])):
                foreach ($form['fields'] as $fieldIndex => $field): ?>
                    <div class="cpff-field" style="border:1px solid #ddd;padding:10px;margin-bottom:10px;">
                        <button type="button" class="remove-field button"
                            onclick="this.closest('.cpff-field').remove()">❌</button><br>
                        <select name="cpff_forms[<?php echo $formIndex; ?>][fields][<?php echo $fieldIndex; ?>][type]">
                            <?php foreach (['text', 'number', 'date', 'color', 'email', 'dropdown', 'radio', 'checkbox'] as $type): ?>
                                <option value="<?php echo $type; ?>" <?php selected($field['type'], $type); ?>><?php echo $type; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="cpff_forms[<?php echo $formIndex; ?>][fields][<?php echo $fieldIndex; ?>][label]"
                            value="<?php echo esc_attr($field['label']); ?>" placeholder="Label">
                        <textarea name="cpff_forms[<?php echo $formIndex; ?>][fields][<?php echo $fieldIndex; ?>][options]"
                            placeholder="Options (label:price)"><?php echo esc_textarea($field['options'] ?? ''); ?></textarea>
                        <?php if ($field['type'] === 'number'): ?>
                            <label>
                                <input type="checkbox"
                                    name="cpff_forms[<?php echo $formIndex; ?>][fields][<?php echo $fieldIndex; ?>][multiply_base]"
                                    <?php checked(!empty($field['multiply_base'])); ?>>
                                Multiply by product price
                            </label>
                            <br>
                            <input type="number" step="any" min="0"
                                name="cpff_forms[<?php echo $formIndex; ?>][fields][<?php echo $fieldIndex; ?>][custom_price]"
                                value="<?php echo esc_attr($field['custom_price'] ?? ''); ?>"
                                placeholder="Custom Price (used if multiply unchecked)">

                        <?php endif; ?>
                    </div>
                <?php endforeach; endif; ?>
        </div>
        <button type="button" class="button add-field" data-form="<?php echo $formIndex; ?>">+ Add Field</button>
        <button type="button" class="button cpff-delete-form">❌ Delete Form</button>
    </div>
    <?php
}
?>


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

    // Load custom styles
    wp_enqueue_style('style-css', plugin_dir_url(__FILE__) . 'style.css');
});


// Render form on single product page
add_action('woocommerce_before_add_to_cart_button', function () {
    global $product;
    $forms = get_option('cpff_forms', []);
    $forms = array_values($forms);
    $product_categories = $product->get_category_ids();

    foreach ($forms as $formIndex => $form) {
        if (!isset($form['categories'], $form['fields']))
            continue;
        if (array_intersect($form['categories'], $product_categories)) {
            echo '<div class="cpff-custom-form">';
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

// Save to cart
add_filter('woocommerce_add_cart_item_data', function ($cart_item_data, $product_id) {
    $forms = get_option('cpff_forms', []);
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
?>