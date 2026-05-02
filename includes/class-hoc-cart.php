<?php
if (!defined('ABSPATH')) exit;

class HOC_Cart {
    public function __construct() {
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_custom_data'], 10, 3);
        add_filter('woocommerce_get_item_data', [$this, 'display_custom_data'], 10, 2);
        add_action('woocommerce_before_calculate_totals', [$this, 'override_price'], 99);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'save_data_to_order'], 10, 4);
        add_action('template_redirect', [$this, 'handle_direct_add_to_cart']);
    }

    public function add_custom_data($cart_item_data, $product_id, $variation_id) {
        $liters = isset($_REQUEST['hoc_liters']) ? floatval($_REQUEST['hoc_liters']) : null;
        $points = isset($_REQUEST['hoc_delivery_points']) ? intval($_REQUEST['hoc_delivery_points']) : null;
        $zip = isset($_REQUEST['hoc_postal_code']) ? sanitize_text_field($_REQUEST['hoc_postal_code']) : null;

        if ($liters && $points) {
            $base_price = $this->get_product_base_price($product_id);
            $total_price = ($base_price * $liters * $points) / 100;

            $cart_item_data['heating_oil_data'] = [
                'liters' => $liters,
                'delivery_points' => $points,
                'postal_code' => $zip,
                'calculated_price' => $total_price
            ];
            $cart_item_data['unique_key'] = md5(microtime() . rand());
        }
        return $cart_item_data;
    }

    public function override_price($cart) {
        if (is_admin() && !defined('DOING_AJAX')) return;
        foreach ($cart->get_cart() as $item) {
            if (isset($item['heating_oil_data']['calculated_price'])) {
                $item['data']->set_price($item['heating_oil_data']['calculated_price'] / $item['quantity']);
            }
        }
    }

    public function display_custom_data($item_data, $cart_item) {
        if (isset($cart_item['heating_oil_data'])) {
            $d = $cart_item['heating_oil_data'];
            $item_data[] = ['name' => 'Menge', 'value' => $d['liters'] . ' L'];
            $item_data[] = ['name' => 'Lieferstellen', 'value' => $d['delivery_points']];
        }
        return $item_data;
    }

    public function save_data_to_order($item, $cart_item_key, $values, $order) {
        if (isset($values['heating_oil_data'])) {
            $d = $values['heating_oil_data'];
            $item->add_meta_data('_liters', $d['liters']);
            $item->add_meta_data('_delivery_points', $d['delivery_points']);
            $item->add_meta_data('_postal_code', $d['postal_code']);
        }
    }

    public function handle_direct_add_to_cart() {
        if (isset($_GET['hoc-buy']) && isset($_GET['hoc_liters']) && !is_admin()) {
            $product_id = intval($_GET['hoc-buy']);
            
            // Clear cart to ensure only one heating oil order at a time
            WC()->cart->empty_cart();
            
            WC()->cart->add_to_cart($product_id, 1);
            wp_safe_redirect(wc_get_checkout_url());
            exit;
        }
    }

    private function get_product_base_price($product_id) {
        $product = wc_get_product($product_id);
        if (!$product) return 104.00;
        $name = strtolower($product->get_name());
        return (strpos($name, 'premium') !== false) ? 106.00 : 104.00;
    }
}
