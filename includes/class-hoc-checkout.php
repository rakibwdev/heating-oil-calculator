<?php
if (!defined('ABSPATH')) exit;

class HOC_Checkout {
    public function __construct() {
        add_filter('woocommerce_checkout_fields', [$this, 'add_custom_fields'], 999);
        add_action('woocommerce_before_checkout_form', [$this, 'render_steps_indicator'], 5);
        add_action('woocommerce_checkout_before_customer_details', [$this, 'start_grid']);
        add_action('woocommerce_checkout_after_customer_details', [$this, 'middle_grid']);
        add_action('woocommerce_checkout_after_order_review', [$this, 'end_grid'], 20);

        add_action('woocommerce_before_checkout_billing_form', [$this, 'wrap_billing_start']);
        add_action('woocommerce_after_checkout_billing_form', [$this, 'wrap_billing_end']);
        add_action('woocommerce_after_checkout_billing_form', [$this, 'render_step_containers'], 20);
        
        add_action('woocommerce_checkout_update_order_meta', [$this, 'save_meta']);
        add_filter('woocommerce_checkout_required_field_notice', [$this, 'clean_notices'], 10, 2);

        // Display custom fields in order details
        add_filter('woocommerce_get_order_item_totals', [$this, 'add_order_item_totals'], 10, 3);
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'display_order_data_in_admin'], 10, 1);
    }

    public function add_order_item_totals($total_rows, $order, $tax_display) {
        $date = get_post_meta($order->get_id(), '_billing_delivery_date_custom', true);
        $phone_coord = get_post_meta($order->get_id(), '_billing_delivery_phone_coord', true);
        $shipping = get_post_meta($order->get_id(), '_billing_shipping_type_custom', true);

        $new_rows = [];

        foreach ($total_rows as $key => $row) {
            $new_rows[$key] = $row;
            
            // Insert after shipping or before total
            if ($key === 'shipping' || ($key === 'payment_method' && !isset($total_rows['shipping']))) {
                if ($shipping) {
                    $options = ['standard' => 'Standardlieferung (Kostenlos)', 'express' => 'Expresslieferung (+ 45,99 €)'];
                    $new_rows['hoc_shipping_type'] = [
                        'label' => __('Versandoption', 'heating-oil-calculator') . ':',
                        'value' => $options[$shipping] ?? $shipping
                    ];
                }
                
                if ($phone_coord) {
                    $new_rows['hoc_delivery_date'] = [
                        'label' => __('Liefertermin', 'heating-oil-calculator') . ':',
                        'value' => 'Telefonische Abstimmung'
                    ];
                } elseif ($date) {
                    $new_rows['hoc_delivery_date'] = [
                        'label' => __('Liefertermin', 'heating-oil-calculator') . ':',
                        'value' => $date
                    ];
                }
            }
        }

        return $new_rows;
    }

    public function display_order_data_in_admin($order) {
        $date = get_post_meta($order->get_id(), '_billing_delivery_date_custom', true);
        $phone_coord = get_post_meta($order->get_id(), '_billing_delivery_phone_coord', true);
        $shipping = get_post_meta($order->get_id(), '_billing_shipping_type_custom', true);

        echo '<div class="order_data_column" style="width:100%; clear:both; margin-top:20px;">';
        echo '<h4>' . __('Heizöl Details', 'heating-oil-calculator') . '</h4>';
        if ($shipping) {
            $options = ['standard' => 'Standardlieferung (Kostenlos)', 'express' => 'Expresslieferung (+ 45,99 €)'];
            echo '<p><strong>' . __('Versandoption', 'heating-oil-calculator') . ':</strong> ' . ($options[$shipping] ?? $shipping) . '</p>';
        }
        if ($phone_coord) {
            echo '<p><strong>' . __('Telefonische Abstimmung', 'heating-oil-calculator') . ':</strong> Ja</p>';
        } elseif ($date) {
            echo '<p><strong>' . __('Liefertermin', 'heating-oil-calculator') . ':</strong> ' . $date . '</p>';
        }
        echo '</div>';
    }

    public function add_custom_fields($fields) {
        $points = $this->get_points();

        // 1. Shipping Options (Step 1) - We keep this here so it shows in the billing section
        $fields['billing']['billing_shipping_type_custom'] = [
            'type' => 'radio',
            'label' => 'Versandoption',
            'options' => ['standard' => 'Standardlieferung (Kostenlos)', 'express' => 'Expresslieferung (+ 45,99 €)'],
            'default' => 'standard',
            'priority' => 1
        ];

        // 2. Salutation
        $fields['billing']['billing_salutation'] = [
            'type' => 'select',
            'label' => 'Anrede',
            'options' => ['' => 'Bitte auswählen', 'Herr' => 'Herr', 'Frau' => 'Frau', 'Divers' => 'Divers'],
            'required' => true,
            'priority' => 5
        ];

        // Rename standard labels
        $fields['billing']['billing_first_name']['label'] = 'Vorname';
        $fields['billing']['billing_last_name']['label'] = 'Nachname';
        $fields['billing']['billing_address_1']['label'] = 'Straße & Hausnummer';
        $fields['billing']['billing_city']['label'] = 'Ort';

        // Additional Address Cards (Step 1)
        if ($points > 1) {
            for ($i = 2; $i <= $points; $i++) {
                $s = "delivery_point_{$i}";
                $fields[$s]["dp_{$i}_salutation"] = ['type' => 'select', 'label' => 'Anrede', 'options' => ['' => 'Bitte auswählen', 'Herr' => 'Herr', 'Frau' => 'Frau'], 'required' => true];
                $fields[$s]["dp_{$i}_first_name"] = ['type' => 'text', 'label' => 'Vorname', 'required' => true, 'class' => ['form-row-first']];
                $fields[$s]["dp_{$i}_last_name"] = ['type' => 'text', 'label' => 'Nachname', 'required' => true, 'class' => ['form-row-last']];
                $fields[$s]["dp_{$i}_address_1"] = ['type' => 'text', 'label' => 'Straße & Hausnummer', 'required' => true, 'class' => ['form-row-wide']];
                $fields[$s]["dp_{$i}_postcode"] = ['type' => 'text', 'label' => 'Postleitzahl', 'required' => true, 'class' => ['form-row-first']];
                $fields[$s]["dp_{$i}_city"] = ['type' => 'text', 'label' => 'Ort', 'required' => true, 'class' => ['form-row-last']];
            }
        }
        return $fields;
    }

    public function render_steps_indicator() {
        ?>
        <div class="hoc-checkout-steps">
            <div class="steps-track">
                <div class="step-item active"><div class="step-circle">1</div><span class="step-label">Daten & Zahlung</span></div>
                <div class="step-line"></div>
                <div class="step-item"><div class="step-circle">2</div><span class="step-label">Liefertermin</span></div>
                <div class="step-line"></div>
                <div class="step-item"><div class="step-circle">3</div><span class="step-label">Bestätigung</span></div>
            </div>
        </div>
        <?php
    }

    public function start_grid() { echo '<div class="hoc-checkout-main-grid"><div class="hoc-row"><div class="hoc-col-left">'; }
    public function middle_grid() { 
        echo '</div><div class="hoc-col-right">'; 
        $this->render_checkout_sidebar_summary();
    }

    public function render_checkout_sidebar_summary($shipping_method = null) {
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

        // Determine shipping
        if ($shipping_method === null) {
            $shipping_method = WC()->session->get('hoc_shipping_type') ?: 'standard';
        }

        $liters = $oil_data['liters'];
        $points = $oil_data['delivery_points'];
        $base_total = $oil_data['calculated_price'];
        
        $shipping_cost = ($shipping_method === 'express') ? 45.99 : 0.00;
        $total_brutto = $base_total + $shipping_cost;
        
        // Calculations
        $ggvs = 42.59;
        $netto = $total_brutto / 1.19;
        $mwst = $total_brutto - $netto;
        $price_per_100l = $base_total / ($liters / 100);
        
        // SEPA Discount
        $payment_method = WC()->session->get('chosen_payment_method');
        $sepa_discount = ($payment_method === 'sepa') ? $total_brutto * 0.05 : 0;
        $final_price = $total_brutto - $sepa_discount;

        ?>
        <div class="sidebar-card hoc-checkout-summary" id="sidebar">
            <h5 class="fw-bold mb-3"><?php _e('Übersicht', 'heating-oil-calculator'); ?></h5>
            <div id="sidebar-main">
                <div class="sidebar-row"><strong><?php echo esc_html($product_name); ?></strong></div>
                <div class="sidebar-row"><span>Menge:</span><span><?php echo number_format($liters, 0, '', '.'); ?> L</span></div>
                <div class="sidebar-row"><span>Lieferstellen:</span><span><?php echo $points; ?> <?php echo ($points > 1) ? 'Lieferstellen' : 'Lieferstelle'; ?></span></div>
                <div class="sidebar-row"><span>Preis/100L:</span><span><?php echo number_format($price_per_100l, 2, ',', '.'); ?> €</span></div>
            </div>
            
            <div id="sidebar-delivery">
                <div class="sidebar-row"><strong>Versand:</strong> <?php echo ($shipping_method === 'express') ? 'Expresslieferung' : 'Standardlieferung'; ?></div>
                <div class="sidebar-row"><span>Versandkosten:</span><span><?php echo ($shipping_cost > 0) ? number_format($shipping_cost, 2, ',', '.') . ' €' : 'Kostenlos'; ?></span></div>
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
    public function end_grid() {
        echo '</div></div></div>';
        echo '<div class="sticky-footer"><div class="container d-flex justify-content-between align-items-center">
                <div><button type="button" class="btn btn-secondary prev-step-btn" style="display:none;">← Zurück</button></div>
                <div><button type="button" class="btn btn-success next-step-btn">Weiter zu: Liefertermin →</button>
                     <button type="button" class="btn btn-success custom-submit-btn" style="display:none;">Bestellung absenden</button></div>
              </div></div>';
    }

    public function wrap_billing_start() {
        $header = ($this->get_points() > 1) ? 'Lieferanschrift for Lieferstelle #1' : 'Lieferanschrift for alle Lieferstellen';
        echo '<div class="lieferanschrift-card billing-card"><h4 class="dp-header">' . $header . '</h4>';
    }
    public function wrap_billing_end() { echo '</div>'; }

    public function render_step_containers($checkout) {
        $points = $this->get_points();
        
        // STEP 2 Container
        echo '<div id="hoc-checkout-step-2" class="hoc-step-container" style="display:none;"><div class="lieferanschrift-card">';
        echo '<h4 class="dp-header">Liefertermin</h4>';
        
        // DIRECT HTML TO ENSURE ID EXISTS
        ?>
        <p class="form-row form-row-wide" id="billing_delivery_date_custom_field">
            <label for="billing_delivery_date_custom">Wunschtermin</label>
            <span class="woocommerce-input-wrapper">
                <select name="billing_delivery_date_custom" id="billing_delivery_date_custom" class="select">
                    <option value="">Bitte auswählen</option>
                </select>
            </span>
        </p>

        <p class="form-row form-row-wide" id="billing_delivery_phone_coord_field">
            <label class="checkbox">
                <input type="checkbox" name="billing_delivery_phone_coord" id="billing_delivery_phone_coord" value="1">
                Auf Wunsch stimmen wir den Liefertermin nach Ihrer Bestellung persönlich mit Ihnen ab.
            </label>
        </p>
        <?php

        echo '</div></div>';

    // STEP 3 Container
echo '<div id="hoc-checkout-step-3" class="hoc-step-container" style="display:none;">
        <div class="lieferanschrift-card">
            <h4 class="dp-header">Bestätigung</h4>
            <p>Bitte prüfen Sie Ihre Angaben in der Übersicht rechts.</p>

            <div class="hoc-checkbox-group">
                <label class="hoc-checkbox">
                    <input type="checkbox" id="hoc_terms" required>
                    <span>I have checked and confirmed all the information.</span>
                </label>

                <label class="hoc-checkbox">
                    <input type="checkbox" id="hoc_privacy" required>
                    <span>I accept the terms and conditions and the privacy policy.</span>
                </label>
            </div>

        </div>
      </div>';

        // Extra Cards (Step 1)
        if ($points > 1) {
            echo '<div id="extra_delivery_points" class="hoc-step-1-extra">';
            for ($i = 2; $i <= $points; $i++) {
                echo '<div class="lieferanschrift-card"><h4 class="dp-header">Lieferanschrift for Lieferstelle #' . $i . '</h4>';
                foreach ($checkout->get_checkout_fields("delivery_point_{$i}") as $key => $field) {
                    woocommerce_form_field($key, $field, $checkout->get_value($key));
                }
                echo '</div>';
            }
            echo '</div>';
        }
    }

    public function save_meta($order_id) {
        foreach ($_POST as $k => $v) { 
            if (strpos($k, 'dp_') === 0 || $k === 'billing_salutation' || $k === 'billing_delivery_date_custom' || $k === 'billing_delivery_phone_coord' || $k === 'billing_shipping_type_custom') {
                update_post_meta($order_id, '_' . $k, sanitize_text_field($v)); 
            }
        }
    }

    public function clean_notices($n, $l) { return sprintf('%s ist ein Pflichtfeld.', $l); }

    private function get_points() {
        if (!WC()->cart) return 1;
        $p = 1;
        foreach (WC()->cart->get_cart() as $i) { if (isset($i['heating_oil_data']['delivery_points'])) $p = max($p, $i['heating_oil_data']['delivery_points']); }
        return $p;
    }
}
