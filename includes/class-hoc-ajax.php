<?php
if (!defined('ABSPATH')) exit;

class HOC_AJAX {
    public function __construct() {
        add_action('wp_ajax_calculate_heating_oil_price', [$this, 'calculate_price']);
        add_action('wp_ajax_nopriv_calculate_heating_oil_price', [$this, 'calculate_price']);

        add_action('wp_ajax_update_checkout_sidebar', [$this, 'update_sidebar']);
        add_action('wp_ajax_nopriv_update_checkout_sidebar', [$this, 'update_sidebar']);
    }

    public function update_sidebar() {
        if (isset($_POST['nonce']) && !empty($_POST['nonce'])) {
            wp_verify_nonce($_POST['nonce'], 'hoc_calculator_nonce');
        }

        $checkout = new HOC_Checkout();
        
        // Shipping is now handled by standard WC, so we just render the summary 
        // which pulls from WC()->cart and WC()->session.
        
        ob_start();
        $checkout->render_checkout_sidebar_summary();
        $html = ob_get_clean();

        wp_send_json_success(['html' => $html]);
    }

    public function calculate_price() {
        if (isset($_POST['nonce']) && !empty($_POST['nonce'])) {
            wp_verify_nonce($_POST['nonce'], 'hoc_calculator_nonce');
        }

        $liters = isset($_POST['liters']) ? floatval($_POST['liters']) : 0;
        $points = isset($_POST['delivery_points']) ? intval($_POST['delivery_points']) : 1;
        $product_id = intval($_POST['product_id']);

        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error(['message' => 'Invalid product']);
            return;
        }

        $base_price = (strpos(strtolower($product->get_name()), 'premium') !== false) ? 106.00 : 104.00;
        $total = ($base_price * $liters * $points) / 100;

        wp_send_json_success([
            'total_price' => number_format($total, 2, ',', '.'),
            'total_price_raw' => $total,
            'price_per_100l' => number_format($base_price, 2, ',', '.'),
            'liters' => $liters,
            'delivery_points' => $points
        ]);
    }
}
