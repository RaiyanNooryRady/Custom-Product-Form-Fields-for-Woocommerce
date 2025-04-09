<?php
/*
Plugin Name: Custom Product Form Fields PRO
Description: WooCommerce plugin with proper DOM logic for dynamic field creation, form saving, live price update, and multiple forms.
Version: 1.0
Author: Raiyan Noory
*/

if (!defined('ABSPATH'))
    exit;

// Load all parts
require_once plugin_dir_path(__FILE__) . 'admin-ui.php';
require_once plugin_dir_path(__FILE__) . 'frontend-display.php';
require_once plugin_dir_path(__FILE__) . 'pricing-logic.php';

// Enqueue global styles
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('cpff-style', plugin_dir_url(__FILE__) . 'style.css');
});