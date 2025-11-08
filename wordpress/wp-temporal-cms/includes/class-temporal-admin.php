<?php

class Temporal_CMS_Admin {
    public static function register() {
        add_action('admin_menu', [__CLASS__, 'add_settings_page']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    public static function add_settings_page() {
        add_options_page(
            'Temporal CMS',
            'Temporal CMS',
            'manage_options',
            'temporal-cms',
            [__CLASS__, 'render_settings_page']
        );
    }

    public static function register_settings() {
        register_setting('temporal_cms', Temporal_CMS_Client::OPTION_KEY, [__CLASS__, 'sanitize']);

        add_settings_section('temporal_cms_main', 'Workflow settings', '__return_false', 'temporal-cms');

        add_settings_field('endpoint', 'REST proxy URL', [__CLASS__, 'field_endpoint'], 'temporal-cms', 'temporal_cms_main');
        add_settings_field('site_identifier', 'Site identifier', [__CLASS__, 'field_site'], 'temporal-cms', 'temporal_cms_main');
        add_settings_field('default_locales', 'Default locales', [__CLASS__, 'field_locales'], 'temporal-cms', 'temporal_cms_main');
        add_settings_field('post_types', 'Post types', [__CLASS__, 'field_post_types'], 'temporal-cms', 'temporal_cms_main');
        add_settings_field('start_on_create', 'Start on draft save', [__CLASS__, 'field_start_create'], 'temporal-cms', 'temporal_cms_main');
        add_settings_field('start_on_publish', 'Start on publish', [__CLASS__, 'field_start_publish'], 'temporal-cms', 'temporal_cms_main');
    }

    public static function sanitize($input) {
        $output = [];
        $output['endpoint'] = esc_url_raw($input['endpoint'] ?? '');
        $output['site_identifier'] = sanitize_text_field($input['site_identifier'] ?? 'wordpress');
        $output['default_locales'] = implode("\n", array_filter(array_map('trim', explode("\n", $input['default_locales'] ?? ''))));
        $output['post_types'] = array_values(array_filter(array_map('sanitize_key', $input['post_types'] ?? [])));
        $output['start_on_create'] = !empty($input['start_on_create']);
        $output['start_on_publish'] = !empty($input['start_on_publish']);
        return $output;
    }

    protected static function get_options() {
        $client = new Temporal_CMS_Client();
        return $client->get_options();
    }

    public static function field_endpoint() {
        $options = self::get_options();
        printf('<input type="url" name="%1$s[endpoint]" value="%2$s" class="regular-text" required />', Temporal_CMS_Client::OPTION_KEY, esc_attr($options['endpoint']));
        echo '<p class="description">Base URL for the Temporal REST proxy.</p>';
    }

    public static function field_site() {
        $options = self::get_options();
        printf('<input type="text" name="%1$s[site_identifier]" value="%2$s" class="regular-text" />', Temporal_CMS_Client::OPTION_KEY, esc_attr($options['site_identifier']));
    }

    public static function field_locales() {
        $options = self::get_options();
        printf('<textarea name="%1$s[default_locales]" rows="4" class="large-text">%2$s</textarea>', Temporal_CMS_Client::OPTION_KEY, esc_textarea(is_array($options['default_locales']) ? implode("\n", $options['default_locales']) : $options['default_locales']));
        echo '<p class="description">One locale per line.</p>';
    }

    public static function field_post_types() {
        $options = self::get_options();
        $types = get_post_types(['public' => true], 'objects');
        foreach ($types as $type) {
            $checked = in_array($type->name, $options['post_types'], true) ? 'checked' : '';
            printf('<label><input type="checkbox" name="%1$s[post_types][]" value="%2$s" %4$s /> %3$s</label><br/>', Temporal_CMS_Client::OPTION_KEY, esc_attr($type->name), esc_html($type->label), $checked);
        }
    }

    public static function field_start_create() {
        $options = self::get_options();
        printf('<label><input type="checkbox" name="%1$s[start_on_create]" value="1" %2$s /> Trigger on initial save</label>', Temporal_CMS_Client::OPTION_KEY, checked($options['start_on_create'], true, false));
    }

    public static function field_start_publish() {
        $options = self::get_options();
        printf('<label><input type="checkbox" name="%1$s[start_on_publish]" value="1" %2$s /> Trigger when post transitions to publish</label>', Temporal_CMS_Client::OPTION_KEY, checked($options['start_on_publish'], true, false));
    }

    public static function render_settings_page() {
        echo '<div class="wrap"><h1>Temporal CMS</h1><form method="post" action="options.php">';
        settings_fields('temporal_cms');
        do_settings_sections('temporal-cms');
        submit_button();
        echo '</form></div>';
    }
}

Temporal_CMS_Admin::register();
