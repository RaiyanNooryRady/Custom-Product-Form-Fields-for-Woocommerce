<?php
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
                                Multiply by product base price
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