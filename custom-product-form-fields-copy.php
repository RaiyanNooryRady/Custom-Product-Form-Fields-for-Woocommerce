<?php


// Exit if accessed directly
if (!defined('ABSPATH')) exit;

// 1. Admin menu for plugin settings
add_action('admin_menu', 'cpff_add_admin_menu');
function cpff_add_admin_menu() {
    add_menu_page('Product Form Fields', 'Product Form Fields', 'manage_options', 'cpff_settings', 'cpff_settings_page');
}

// 2. Register settings
add_action('admin_init', 'cpff_register_settings');
function cpff_register_settings() {
    register_setting('cpff_settings_group', 'cpff_form_data');
}

// 3. Plugin settings page
function cpff_settings_page() {
    $form_data = get_option('cpff_form_data', []);
    $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
    ?>
    <div class="wrap">
        <h1>Custom Product Form Fields</h1>
        <form method="post" action="options.php">
            <?php settings_fields('cpff_settings_group'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Select Categories</th>
                    <td>
                        <?php foreach ($categories as $cat): ?>
                            <label>
                                <input type="checkbox" name="cpff_form_data[categories][]" value="<?php echo esc_attr($cat->term_id); ?>" <?php if (isset($form_data['categories']) && in_array($cat->term_id, $form_data['categories'])) echo 'checked'; ?>>
                                <?php echo esc_html($cat->name); ?>
                            </label><br>
                        <?php endforeach; ?>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Form Fields JSON</th>
                    <td>
                        <textarea name="cpff_form_data[fields]" rows="10" cols="80"><?php echo esc_textarea($form_data['fields'] ?? ''); ?></textarea>
                        <p class="description">Enter form fields as JSON. Example: [{"type":"text","label":"Your Name"},{"type":"checkbox","label":"Options","options":[{"label":"A","price":5}]}]</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

// 4. Render form on product page
add_action('woocommerce_before_add_to_cart_button', 'cpff_display_custom_form');
function cpff_display_custom_form() {
    global $product;
    $form_data = get_option('cpff_form_data', []);
    if (!isset($form_data['categories']) || !in_array($product->get_category_ids()[0], $form_data['categories'])) return;
    $fields = json_decode($form_data['fields'], true);
    if (!$fields) return;

    echo '<div class="cpff-custom-form">';
    wp_nonce_field('cpff_form_nonce', 'cpff_form_nonce_field');
    foreach ($fields as $index => $field) {
        $name = 'cpff_' . $index;
        echo '<p><label>' . esc_html($field['label']) . '</label><br>';
        switch ($field['type']) {
            case 'text':
            case 'email':
            case 'number':
            case 'date':
            case 'color':
            case 'file':
            case 'hidden':
            case 'image':
                echo '<input type="' . esc_attr($field['type']) . '" name="' . esc_attr($name) . '">';
                break;
            case 'checkbox':
            case 'radio':
                foreach ($field['options'] as $opt) {
                    echo '<label><input type="' . esc_attr($field['type']) . '" name="' . esc_attr($name) . ($field['type'] === 'checkbox' ? '[]' : '') . '" value="' . esc_attr($opt['label']) . '" data-price="' . esc_attr($opt['price']) . '">' . esc_html($opt['label']) . ' (+$' . $opt['price'] . ')</label><br>';
                }
                break;
            case 'dropdown':
                echo '<select name="' . esc_attr($name) . '">';
                foreach ($field['options'] as $opt) {
                    echo '<option value="' . esc_attr($opt['label']) . '" data-price="' . esc_attr($opt['price']) . '">' . esc_html($opt['label']) . ' (+$' . $opt['price'] . ')</option>';
                }
                echo '</select>';
                break;
        }
        echo '</p>';
    }
    echo '</div>';
}

// 5. Save to cart
add_filter('woocommerce_add_cart_item_data', 'cpff_add_cart_item_data', 10, 2);
function cpff_add_cart_item_data($cart_item_data, $product_id) {
    if (!isset($_POST['cpff_form_nonce_field']) || !wp_verify_nonce($_POST['cpff_form_nonce_field'], 'cpff_form_nonce')) return $cart_item_data;

    $form_data = get_option('cpff_form_data', []);
    $fields = json_decode($form_data['fields'], true);
    if (!$fields) return $cart_item_data;

    $extra_price = 0;
    $custom_data = [];

    foreach ($fields as $index => $field) {
        $name = 'cpff_' . $index;
        if (isset($_POST[$name])) {
            $value = $_POST[$name];
            $custom_data[$field['label']] = $value;
            if (is_array($value)) {
                foreach ($value as $val) {
                    foreach ($field['options'] as $opt) {
                        if ($opt['label'] == $val) $extra_price += (float)$opt['price'];
                    }
                }
            } else {
                foreach ($field['options'] ?? [] as $opt) {
                    if ($opt['label'] == $value) $extra_price += (float)$opt['price'];
                }
            }
        }
    }

    $cart_item_data['cpff_fields'] = $custom_data;
    $cart_item_data['cpff_extra_price'] = $extra_price;
    $cart_item_data['unique_key'] = md5(microtime().rand());
    return $cart_item_data;
}

// 6. Display in cart and checkout
add_filter('woocommerce_get_item_data', 'cpff_display_cart_item_data', 10, 2);
function cpff_display_cart_item_data($item_data, $cart_item) {
    if (isset($cart_item['cpff_fields'])) {
        foreach ($cart_item['cpff_fields'] as $label => $value) {
            $item_data[] = array(
                'name' => $label,
                'value' => is_array($value) ? implode(', ', $value) : $value
            );
        }
    }
    return $item_data;
}

// 7. Add extra price to product total
add_action('woocommerce_before_calculate_totals', 'cpff_add_custom_price');
function cpff_add_custom_price($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;
    foreach ($cart->get_cart() as $cart_item) {
        if (isset($cart_item['cpff_extra_price'])) {
            $cart_item['data']->set_price($cart_item['data']->get_price() + $cart_item['cpff_extra_price']);
        }
    }
}

// 8. Save data to order
add_action('woocommerce_checkout_create_order_line_item', 'cpff_save_order_item_meta', 10, 4);
function cpff_save_order_item_meta($item, $cart_item_key, $values, $order) {
    if (isset($values['cpff_fields'])) {
        foreach ($values['cpff_fields'] as $label => $value) {
            $item->add_meta_data($label, is_array($value) ? implode(', ', $value) : $value);
        }
    }
}
