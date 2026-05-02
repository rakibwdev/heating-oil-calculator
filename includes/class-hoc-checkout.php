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
    public function middle_grid() { echo '</div><div class="hoc-col-right">'; }
    public function end_grid() {
        echo '</div></div></div>';
        echo '<div class="sticky-footer"><div class="container d-flex justify-content-between align-items-center">
                <div><button type="button" class="btn btn-secondary prev-step-btn" style="display:none;">← Zurück</button></div>
                <div><button type="button" class="btn btn-success next-step-btn">Weiter zu: Liefertermin →</button>
                     <button type="button" class="btn btn-success custom-submit-btn" style="display:none;">Bestellung absenden</button></div>
              </div></div>';
    }

    public function wrap_billing_start() {
        $header = ($this->get_points() > 1) ? 'Lieferanschrift für Lieferstelle #1' : 'Lieferanschrift für alle Lieferstellen';
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
                echo '<div class="lieferanschrift-card"><h4 class="dp-header">Lieferanschrift für Lieferstelle #' . $i . '</h4>';
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
            if (strpos($k, 'dp_') === 0 || $k === 'billing_salutation' || $k === 'billing_delivery_date_custom' || $k === 'billing_delivery_phone_coord') {
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
