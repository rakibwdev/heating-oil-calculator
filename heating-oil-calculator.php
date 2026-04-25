<?php
/**
 * Plugin Name: Heating Oil Calculator for WooCommerce
 * Plugin URI: https://forazitech.com/
 * Description: Dynamic heating oil pricing calculator with delivery points and multi-address checkout
 * Version: 1.0.1
 * Author: Ftech
 * License: GPL v2 or later
 * Text Domain: heating-oil-calculator
 * Requires Plugins: woocommerce
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('HOC_VERSION', '1.0.0');
define('HOC_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('HOC_PLUGIN_URL', plugin_dir_url(__FILE__));

// Main plugin class
class Heating_Oil_Calculator {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        // Frontend hooks
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('woocommerce_before_calculate_totals', [$this, 'override_cart_price'], 99);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'save_calculator_data'], 10, 4);
        
        // AJAX handlers
        add_action('wp_ajax_calculate_heating_oil_price', [$this, 'ajax_calculate_price']);
        add_action('wp_ajax_nopriv_calculate_heating_oil_price', [$this, 'ajax_calculate_price']);
        
        // Checkout modifications
        add_filter('woocommerce_checkout_fields', [$this, 'add_delivery_points_fields'], 999);
        add_action('woocommerce_before_checkout_form', [$this, 'render_checkout_steps'], 5);
        add_action('woocommerce_checkout_before_customer_details', [$this, 'start_checkout_grid']);
        add_action('woocommerce_checkout_after_customer_details', [$this, 'middle_checkout_grid']);
        add_action('woocommerce_checkout_after_order_review', [$this, 'end_checkout_grid'], 20);

        // Billing form styling
        add_action('woocommerce_before_checkout_billing_form', [$this, 'before_billing_form']);
        add_action('woocommerce_after_checkout_billing_form', [$this, 'after_billing_form']);
        
        add_action('woocommerce_after_checkout_billing_form', [$this, 'render_delivery_point_fields'], 20);
        add_action('woocommerce_checkout_process', [$this, 'validate_checkout_fields']);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'save_delivery_points_meta']);
        add_filter('woocommerce_checkout_required_field_notice', [$this, 'clean_required_field_notices'], 10, 2);
        
        // Cart modifications
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_calculator_data_to_cart'], 10, 3);
        add_filter('woocommerce_get_item_data', [$this, 'display_calculator_data_in_cart'], 10, 2);
        add_filter('woocommerce_add_to_cart_redirect', [$this, 'redirect_to_checkout']);
        
        // Display calculator on product page
        add_action('woocommerce_before_add_to_cart_button', [$this, 'display_calculator']);
        
        // Admin hooks
        add_action('add_meta_boxes', [$this, 'add_order_meta_box']);
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'display_order_data_in_admin']);
        
        // Custom Checkout Overview Sidebar
        add_action('woocommerce_checkout_order_review', [$this, 'render_checkout_sidebar_summary'], 5);
        
        // Force add to cart if parameters are present
        add_action('template_redirect', [$this, 'force_add_to_cart_from_url']);
    }

    public function render_checkout_steps() {
        ?>
        <div class="hoc-checkout-steps">
            <div class="steps-track">
                <div class="step-item active">
                    <div class="step-circle">1</div>
                    <span class="step-label">Daten & Zahlung</span>
                </div>
                <div class="step-line"></div>
                <div class="step-item">
                    <div class="step-circle">2</div>
                    <span class="step-label">Liefertermin</span>
                </div>
                <div class="step-line"></div>
                <div class="step-item">
                    <div class="step-circle">3</div>
                    <span class="step-label">Bestätigung</span>
                </div>
            </div>
        </div>
        <?php
    }

    public function start_checkout_grid() {
        echo '<div class="hoc-checkout-main-grid"><div class="hoc-row"><div class="hoc-col-left">';
    }

    public function middle_checkout_grid() {
        echo '</div><div class="hoc-col-right">';
    }

    public function end_checkout_grid() {
        echo '</div></div></div>';
        echo '<div class="sticky-footer">
                <div class="container d-flex justify-content-end">
                    <button type="submit" class="btn btn-success custom-next-btn">Weiter zu: Liefertermin →</button>
                </div>
              </div>';
    }

    public function render_checkout_sidebar_summary() {
        $cart = WC()->cart->get_cart();
        $oil_data = null;
        $product_name = '';

        foreach ($cart as $item) {
            if (isset($item['heating_oil_data'])) {
                $oil_data = $item['heating_oil_data'];
                $product_name = $item['data']->get_name();
                break;
            }
        }

        if (!$oil_data) return;

        $liters = $oil_data['liters'];
        $points = $oil_data['delivery_points'];
        $total_brutto = $oil_data['calculated_price'];
        
        // Calculations based on snippet logic
        $ggvs = 42.59;
        $netto = ($total_brutto) / 1.19;
        $mwst = $total_brutto - $netto;
        $price_per_100l = ($total_brutto - $ggvs) / ($liters / 100);
        
        // SEPA Discount (example 5% if applicable)
        $payment_method = WC()->session->get('chosen_payment_method');
        $sepa_discount = ($payment_method === 'sepa') ? $total_brutto * 0.05 : 0;
        $final_price = $total_brutto - $sepa_discount;

        ?>
        <div class="sidebar-card hoc-checkout-summary" id="sidebar">
            <h5 class="fw-bold mb-3"><?php _e('Übersicht', 'heating-oil-calculator'); ?></h5>
            <div id="sidebar-main">
                <div class="sidebar-row"><strong><?php echo esc_html($product_name); ?></strong></div>
                <div class="sidebar-row"><span>Menge:</span><span><?php echo $liters; ?> L</span></div>
                <div class="sidebar-row"><span>Lieferstellen:</span><span><?php echo $points; ?> <?php echo ($points > 1) ? 'Lieferstellen' : 'Lieferstelle'; ?></span></div>
                <div class="sidebar-row"><span>Preis/100L:</span><span><?php echo number_format($price_per_100l, 2, ',', '.'); ?> €</span></div>
            </div>
            
            <div id="sidebar-delivery">
                <div class="sidebar-row"><strong>Versand:</strong> Standardlieferung</div>
                <div class="sidebar-row"><span>Versandkosten:</span><span>Kostenlos</span></div>
            </div>

            <div id="sidebar-payment">
                <div class="sidebar-row"><strong>Zahlung:</strong> <?php echo ($payment_method === 'sepa') ? 'SEPA-Überweisung' : 'Vorkasse'; ?></div>
            </div>

            <div class="sidebar-divider"></div>
            
            <div class="price-box">
                <div id="sidebar-final-price" class="sidebar-row fw-bold fs-5">
                    <span>Gesamtbetrag:</span>
                    <span><?php echo number_format($final_price, 2, ',', '.'); ?> €</span>
                </div>
                
                <button type="button" id="price-details-toggle" class="price-details-toggle">
                    Preisdetails anzeigen 
                    <svg class="price-toggle-icon" width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2.5 4.5L6 8L9.5 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                </button>
                
                <div id="price-details-panel" style="display:none;">
                    <div class="sidebar-divider"></div>
                    <div class="sidebar-row"><span>Netto:</span><span><?php echo number_format($netto, 2, ',', '.'); ?> €</span></div>
                    <div class="sidebar-row"><span>MWSt:</span><span><?php echo number_format($mwst, 2, ',', '.'); ?> €</span></div>
                    <div class="sidebar-row"><span>GGVS-Umlage:</span><span><?php echo number_format($ggvs, 2, ',', '.'); ?> €</span></div>
                    <div class="sidebar-divider"></div>
                    <div class="sidebar-row"><span>Brutto:</span><span><?php echo number_format($total_brutto, 2, ',', '.'); ?> €</span></div>
                    <?php if ($sepa_discount > 0): ?>
                        <div class="sidebar-row"><span>Skonto bei Vorkasse (-5%):</span><span style="color:#18c341;">-<?php echo number_format($sepa_discount, 2, ',', '.'); ?> €</span></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    public function force_add_to_cart_from_url() {
        if (isset($_GET['add-to-cart']) && isset($_GET['hoc_liters']) && !is_admin()) {
            $product_id = intval($_GET['add-to-cart']);
            $quantity = isset($_GET['quantity']) ? intval($_GET['quantity']) : 1;
            
            WC()->cart->add_to_cart($product_id, $quantity);
            
            // Redirect to checkout
            wp_safe_redirect(wc_get_checkout_url());
            exit;
        }
    }

    public function enqueue_scripts() {
        wp_enqueue_style('hoc-style', HOC_PLUGIN_URL . 'assets/css/style.css', [], HOC_VERSION);
        wp_enqueue_script('hoc-calculator', HOC_PLUGIN_URL . 'assets/js/calculator.js', ['jquery'], HOC_VERSION, true);
        
        $product_id = is_product() ? get_the_ID() : 0;
        
        wp_localize_script('hoc-calculator', 'hoc_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('hoc_calculator_nonce'),
            'product_id' => $product_id,
            'checkout_url' => wc_get_checkout_url(),
            'home_url' => home_url('/')
        ]);
    }

    public function redirect_to_checkout($url) {
        if (isset($_REQUEST['hoc_liters'])) {
            return wc_get_checkout_url();
        }
        return $url;
    }

    public function display_calculator() {
        global $product;
        
        // Only show for specific product categories (adjust as needed)
        $product_categories = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'slugs']);
        
        // Check if this is a heating oil product
        $is_heating_oil = false;
        foreach ($product_categories as $category) {
            if (in_array($category, ['heating-oil', 'fuel-oil', 'heating-fuel'])) {
                $is_heating_oil = true;
                break;
            }
        }
        
        if (!$is_heating_oil && !has_shortcode(get_post()->post_content, 'heating_oil_calculator')) {
            return;
        }
        
        include HOC_PLUGIN_PATH . 'templates/calculator-form.php';
    }

    public function ajax_calculate_price() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'hoc_calculator_nonce')) {
            wp_die('Security check failed');
        }
        
        $liters = floatval($_POST['liters']);
        $delivery_points = intval($_POST['delivery_points']);
        $postal_code = sanitize_text_field($_POST['postal_code']);
        $product_id = intval($_POST['product_id']);
        
        // Validation
        $errors = [];
        
        if ($liters < 1500) {
            $errors[] = 'Minimum order is 1500 liters';
        }
        
        if ($liters > 6000) {
            $errors[] = 'Maximum order is 6000 liters';
        }
        
        if ($delivery_points < 1) {
            $errors[] = 'Minimum 1 delivery point required';
        }
        
        if ($delivery_points > 5) {
            $errors[] = 'Maximum 5 delivery points allowed';
        }
        
        if (!preg_match('/^\d{5}$/', $postal_code)) {
            $errors[] = 'Please enter a valid 5-digit postal code';
        }
        
        if (!empty($errors)) {
            wp_send_json_error(['message' => implode(', ', $errors)]);
            return;
        }
        
        // Get product base price
        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error(['message' => 'Invalid product ID: ' . $product_id]);
            return;
        }
        $price_per_100l = $this->get_base_price($product_id, $postal_code);
        
        // SIMPLE CALCULATION: (Price * Liters * Delivery Points) / 100
        $total_price = ($price_per_100l * $liters * $delivery_points) / 100;
        
        // Calculate price per liter for WooCommerce
        $price_per_liter = $total_price / $liters;
        
        wp_send_json_success([
            'total_price' => number_format($total_price, 2, ',', '.'),
            'total_price_raw' => $total_price,
            'price_per_100l' => number_format($price_per_100l, 2, ',', '.'),
            'price_per_liter' => number_format($price_per_liter, 2, ',', '.'),
            'delivery_surcharge' => "0,00",
            'liters' => $liters,
            'delivery_points' => $delivery_points,
            'product_id' => $product_id
        ]);
    }
    
    private function get_base_price($product_id, $postal_code) {
        $product = wc_get_product($product_id);
        if (!$product) return 104.00;

        // Default prices
        $prices = [
            'standard' => 104.00,
            'premium' => 106.00
        ];
        
        $product_sku = $product->get_sku();
        $product_name = $product->get_name();
        
        // Check if it's premium product
        if (strpos(strtolower($product_sku), 'premium') !== false || strpos(strtolower($product_name), 'premium') !== false) {
            return $prices['premium'];
        }
        
        return $prices['standard'];
    }
    
    private function calculate_delivery_surcharge($delivery_points, $liters) {
        return 0;
    }
    
    public function add_calculator_data_to_cart($cart_item_data, $product_id, $variation_id) {
        $liters = isset($_REQUEST['hoc_liters']) ? floatval($_REQUEST['hoc_liters']) : null;
        $delivery_points = isset($_REQUEST['hoc_delivery_points']) ? intval($_REQUEST['hoc_delivery_points']) : null;
        $postal_code = isset($_REQUEST['hoc_postal_code']) ? sanitize_text_field($_REQUEST['hoc_postal_code']) : null;
        
        if ($liters && $delivery_points) {
            // Recalculate total price server-side for security and accuracy
            $price_per_100l = $this->get_base_price($product_id, $postal_code);
            $total_price = ($price_per_100l * $liters * $delivery_points) / 100;

            $cart_item_data['heating_oil_data'] = [
                'liters' => $liters,
                'delivery_points' => $delivery_points,
                'postal_code' => $postal_code,
                'calculated_price' => $total_price
            ];
            
            // Add unique key to prevent merging with other calculator items
            $cart_item_data['unique_key'] = md5(microtime().rand());
        }
        
        return $cart_item_data;
    }
    
    public function override_cart_price($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }
        
        foreach ($cart->get_cart() as $cart_item) {
            if (isset($cart_item['heating_oil_data']['calculated_price'])) {
                $cart_item['data']->set_price($cart_item['heating_oil_data']['calculated_price'] / $cart_item['quantity']);
            }
        }
    }
    
    public function display_calculator_data_in_cart($item_data, $cart_item) {
        if (isset($cart_item['heating_oil_data'])) {
            $data = $cart_item['heating_oil_data'];
            $item_data[] = [
                'name' => __('Liters', 'heating-oil-calculator'),
                'value' => $data['liters'] . ' L'
            ];
            $item_data[] = [
                'name' => __('Delivery Points', 'heating-oil-calculator'),
                'value' => $data['delivery_points']
            ];
            $item_data[] = [
                'name' => __('Postal Code', 'heating-oil-calculator'),
                'value' => $data['postal_code']
            ];
        }
        
        return $item_data;
    }
    
    public function save_calculator_data($item, $cart_item_key, $values, $order) {
        if (isset($values['heating_oil_data'])) {
            $item->add_meta_data('_liters', $values['heating_oil_data']['liters']);
            $item->add_meta_data('_delivery_points', $values['heating_oil_data']['delivery_points']);
            $item->add_meta_data('_postal_code', $values['heating_oil_data']['postal_code']);
        }
    }
    
    public function render_delivery_point_fields($checkout) {
        $delivery_points = 1;
        if (WC()->cart) {
            foreach (WC()->cart->get_cart() as $item) {
                if (isset($item['heating_oil_data']['delivery_points'])) {
                    $delivery_points = max($delivery_points, intval($item['heating_oil_data']['delivery_points']));
                }
            }
        }

        if ($delivery_points <= 1) return;

        echo '<div id="extra_delivery_points" class="extra-delivery-points-wrapper">';
        
        for ($i = 2; $i <= $delivery_points; $i++) {
            $section = "delivery_point_{$i}";
            echo '<div class="lieferanschrift-card dp-card-' . $i . '">';
            
            // Output header
            echo '<h4 class="dp-header">' . sprintf(__('Lieferanschrift für Lieferstelle #%d', 'heating-oil-calculator'), $i) . '</h4>';

            $fields = $checkout->get_checkout_fields($section);

            if (is_array($fields)) {
                foreach ($fields as $key => $field) {
                    $type = isset($field['type']) ? $field['type'] : 'text';
                    if ($type !== 'heading') {
                        woocommerce_form_field($key, $field, $checkout->get_value($key));
                    }
                }
            }
            echo '</div>';
        }
        
        echo '</div>';
    }

    public function before_billing_form() {
        $delivery_points = 1;
        if (WC()->cart) {
            foreach (WC()->cart->get_cart() as $item) {
                if (isset($item['heating_oil_data']['delivery_points'])) {
                    $delivery_points = max($delivery_points, intval($item['heating_oil_data']['delivery_points']));
                }
            }
        }
        $header = ($delivery_points > 1) ? __('Lieferanschrift für Lieferstelle #1', 'heating-oil-calculator') : __('Lieferanschrift für alle Lieferstellen', 'heating-oil-calculator');
        
        echo '<div class="lieferanschrift-card billing-card">';
        echo '<h4 class="dp-header">' . $header . '</h4>';
    }

    public function after_billing_form() {
        echo '</div>';
    }

    public function add_delivery_points_fields($fields) {
        $delivery_points = 1;
        if (WC()->cart) {
            foreach (WC()->cart->get_cart() as $item) {
                if (isset($item['heating_oil_data']['delivery_points'])) {
                    $delivery_points = max($delivery_points, intval($item['heating_oil_data']['delivery_points']));
                }
            }
        }

        // Rename Billing Fields to match Reference
        $fields['billing']['billing_salutation'] = [
            'type' => 'select',
            'label' => __('Anrede', 'heating-oil-calculator'),
            'options' => [
                '' => __('Bitte auswählen', 'heating-oil-calculator'),
                'Herr' => 'Herr',
                'Frau' => 'Frau',
                'Divers' => 'Divers'
            ],
            'required' => true,
            'class' => ['form-row-wide'],
            'priority' => 5
        ];

        $fields['billing']['billing_first_name']['label'] = __('Vorname', 'woocommerce');
        $fields['billing']['billing_last_name']['label'] = __('Nachname', 'woocommerce');
        $fields['billing']['billing_address_1']['label'] = __('Straße & Hausnummer', 'woocommerce');
        $fields['billing']['billing_city']['label'] = __('Ort', 'woocommerce');
        $fields['billing']['billing_postcode']['label'] = __('Postleitzahl', 'woocommerce');
        $fields['billing']['billing_country']['label'] = __('Land', 'woocommerce');

        if ($delivery_points <= 1) {
            return $fields;
        }

        // Add fields for additional delivery points (Point 2 and up)
        for ($i = 2; $i <= $delivery_points; $i++) {
            $section = "delivery_point_{$i}";
            
            $fields[$section]["dp_{$i}_salutation"] = [
                'type' => 'select',
                'label' => __('Anrede', 'heating-oil-calculator'),
                'options' => [
                    '' => __('Bitte auswählen', 'heating-oil-calculator'),
                    'Herr' => 'Herr',
                    'Frau' => 'Frau',
                    'Divers' => 'Divers'
                ],
                'required' => true,
                'class' => ['form-row-wide'],
                'priority' => 5
            ];

            $fields[$section]["dp_{$i}_first_name"] = [
                'type' => 'text',
                'label' => __('Vorname', 'woocommerce'),
                'required' => true,
                'class' => ['form-row-first'],
                'priority' => 10
            ];

            $fields[$section]["dp_{$i}_last_name"] = [
                'type' => 'text',
                'label' => __('Nachname', 'woocommerce'),
                'required' => true,
                'class' => ['form-row-last'],
                'priority' => 20
            ];

            $fields[$section]["dp_{$i}_address_1"] = [
                'type' => 'text',
                'label' => __('Straße & Hausnummer', 'woocommerce'),
                'required' => true,
                'class' => ['form-row-wide'],
                'priority' => 30
            ];

            $fields[$section]["dp_{$i}_postcode"] = [
                'type' => 'text',
                'label' => __('Postleitzahl', 'woocommerce'),
                'required' => true,
                'class' => ['form-row-first'],
                'priority' => 40
            ];

            $fields[$section]["dp_{$i}_city"] = [
                'type' => 'text',
                'label' => __('Ort', 'woocommerce'),
                'required' => true,
                'class' => ['form-row-last'],
                'priority' => 50
            ];
        }

        return $fields;
    }

    public function clean_required_field_notices($notice, $field_label) {
        return sprintf(__('%s ist ein Pflichtfeld.', 'heating-oil-calculator'), $field_label);
    }

    public function validate_checkout_fields() {
        // Custom sections validation is already handled by WC
    }

    public function save_delivery_points_meta($order_id) {
        if (isset($_POST['billing_salutation'])) {
            update_post_meta($order_id, '_billing_salutation', sanitize_text_field($_POST['billing_salutation']));
        }
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'dp_') === 0) {
                update_post_meta($order_id, '_' . $key, sanitize_text_field($value));
            }
        }
    }
    
    public function add_order_meta_box() {
        add_meta_box(
            'heating_oil_order_data',
            __('Heating Oil Order Details', 'heating-oil-calculator'),
            [$this, 'render_order_meta_box'],
            'shop_order',
            'side',
            'default'
        );
    }
    
    public function render_order_meta_box($post) {
        $order = wc_get_order($post->ID);
        $delivery_info = get_post_meta($post->ID, '_delivery_points_info', true);
        
        echo '<div class="heating-oil-order-details">';
        echo '<p><strong>' . __('Delivery Points Info:', 'heating-oil-calculator') . '</strong></p>';
        echo '<p>' . nl2br(esc_html($delivery_info)) . '</p>';
        echo '</div>';
    }
    
    public function display_order_data_in_admin($order) {
        $delivery_info = get_post_meta($order->get_id(), '_delivery_points_info', true);
        if ($delivery_info) {
            echo '<div class="heating-oil-admin-data">';
            echo '<h3>' . __('Heating Oil Delivery Details', 'heating-oil-calculator') . '</h3>';
            echo '<p><strong>' . __('Delivery Points Information:', 'heating-oil-calculator') . '</strong></p>';
            echo '<pre>' . esc_html($delivery_info) . '</pre>';
            echo '</div>';
        }
    }
}

// Initialize the plugin
function heating_oil_calculator_init() {
    if (class_exists('WooCommerce')) {
        return Heating_Oil_Calculator::get_instance();
    } else {
        add_action('admin_notices', function() {
            echo '<div class="error"><p>' . __('Heating Oil Calculator requires WooCommerce to be installed and active.', 'heating-oil-calculator') . '</p></div>';
        });
    }
}
add_action('plugins_loaded', 'heating_oil_calculator_init');