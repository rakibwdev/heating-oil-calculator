<?php
if (!defined('ABSPATH')) exit;

class HOC_Settings {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_menu_page() {
        add_menu_page(
            __('Oil Calculator Settings', 'heating-oil-calculator'),
            __('Oil Calculator', 'heating-oil-calculator'),
            'manage_options',
            'heating-oil-calculator',
            [$this, 'render_settings_page'],
             'dashicons-admin-generic',
            50,
        );
    }

    public function register_settings() {
        register_setting('hoc_settings_group', 'hoc_min_liters');
        register_setting('hoc_settings_group', 'hoc_max_liters');

        add_settings_section('hoc_main_section', __('Allgemeine Einstellungen', 'heating-oil-calculator'), null, 'heating-oil-calculator');

        add_settings_field('hoc_min_liters', __('Minimale Liter', 'heating-oil-calculator'), [$this, 'render_number_field'], 'heating-oil-calculator', 'hoc_main_section', ['label_for' => 'hoc_min_liters']);
        add_settings_field('hoc_max_liters', __('Maximale Liter', 'heating-oil-calculator'), [$this, 'render_number_field'], 'heating-oil-calculator', 'hoc_main_section', ['label_for' => 'hoc_max_liters']);
    }

    public function render_number_field($args) {
        $option = get_option($args['label_for']);
        $default = ($args['label_for'] === 'hoc_min_liters') ? 1500 : 6000;
        if (!$option) $option = $default;
        echo '<input type="number" id="' . esc_attr($args['label_for']) . '" name="' . esc_attr($args['label_for']) . '" value="' . esc_attr($option) . '" />';
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Heizöl Rechner Einstellungen', 'heating-oil-calculator'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('hoc_settings_group');
                do_settings_sections('heating-oil-calculator');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
}
