<?php

class Temporal_CMS_Client {
    const OPTION_KEY = 'temporal_cms_settings';
    const META_WORKFLOW_ID = '_temporal_workflow_id';

    public function get_options() {
        $defaults = [
            'endpoint' => 'http://localhost:4000',
            'site_identifier' => 'wordpress',
            'default_locales' => "en",
            'post_types' => ['post'],
            'start_on_publish' => true,
            'start_on_create' => true,
        ];
        $stored = get_option(self::OPTION_KEY, []);
        return wp_parse_args($stored, $defaults);
    }

    protected function base_url() {
        $options = $this->get_options();
        return untrailingslashit($options['endpoint']);
    }

    public function start_workflow($post_id, $user_id) {
        $options = $this->get_options();
        $payload = [
            'cmsId' => (string) $post_id,
            'site' => $options['site_identifier'],
            'locales' => $this->parse_locales($options['default_locales']),
            'requestedBy' => get_userdata($user_id)->user_login ?? 'system',
        ];
        $response = wp_remote_post($this->base_url() . '/workflows', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($payload),
            'timeout' => 5,
        ]);
        if (is_wp_error($response)) {
            error_log('[Temporal CMS] Failed to start workflow: ' . $response->get_error_message());
            return null;
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['workflowId'] ?? null;
    }

    public function send_signal($workflow_id, $signal, $payload = []) {
        $url = sprintf('%s/signals/%s/%s', $this->base_url(), $workflow_id, $signal);
        $response = wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($payload),
            'timeout' => 5,
        ]);
        if (is_wp_error($response)) {
            error_log('[Temporal CMS] Failed to send signal ' . $signal . ': ' . $response->get_error_message());
            return false;
        }
        $code = wp_remote_retrieve_response_code($response);
        return $code >= 200 && $code < 300;
    }

    public function fetch_status($workflow_id) {
        $url = sprintf('%s/workflows/%s/status', $this->base_url(), $workflow_id);
        $response = wp_remote_get($url, ['timeout' => 5]);
        if (is_wp_error($response)) {
            return null;
        }
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body;
    }

    public function parse_locales($raw) {
        if (is_array($raw)) {
            return array_values(array_filter(array_map('trim', $raw)));
        }
        $lines = preg_split('/[\r\n]+/', (string) $raw);
        return array_values(array_filter(array_map('trim', $lines)));
    }
}
