<?php
/**
 * Plugin Name: Heating Oil Calculator for WooCommerce
 * Description: Professional 3-step heating oil wizard with multi-address support.
 * Version: 1.1.0
 * Author: Ftech
 * License: GPL v2 or later
 * Text Domain: heating-oil-calculator
 */

if (!defined('ABSPATH')) exit;

define('HOC_VERSION', '1.1.0');
define('HOC_PATH', plugin_dir_path(__FILE__));
define('HOC_URL', plugin_dir_url(__FILE__));

// Load Classes
require_once HOC_PATH . 'includes/class-hoc-cart.php';
require_once HOC_PATH . 'includes/class-hoc-checkout.php';
require_once HOC_PATH . 'includes/class-hoc-ajax.php';

class Heating_Oil_Calculator {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        $this->init();
    }

    private function init() {
        // Initialize Modules
        new HOC_Cart();
        new HOC_Checkout();
        new HOC_AJAX();

        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets() {
        wp_enqueue_style('hoc-style', HOC_URL . 'assets/css/style.css', [], HOC_VERSION);
        wp_enqueue_script('hoc-calculator', HOC_URL . 'assets/js/calculator.js', ['jquery'], HOC_VERSION, true);

        wp_localize_script('hoc-calculator', 'hoc_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('hoc_calculator_nonce'),
            'checkout_url' => wc_get_checkout_url(),
            'home_url' => home_url('/', is_ssl() ? 'https' : 'http'),
            'product_id' => is_product() ? get_the_ID() : 0
        ]);
    }
}

Heating_Oil_Calculator::get_instance();
