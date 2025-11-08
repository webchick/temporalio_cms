<?php

class Temporal_CMS_Hooks {
    protected $client;

    public function __construct(Temporal_CMS_Client $client) {
        $this->client = $client;
        add_action('save_post', [$this, 'maybe_start_workflow'], 10, 3);
        add_action('add_meta_boxes', [$this, 'add_meta_box']);
        add_action('admin_post_temporal_cms_signal', [$this, 'handle_signal']);
    }

    public function maybe_start_workflow($post_id, $post, $update) {
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }
        $options = $this->client->get_options();
        if (!in_array($post->post_type, $options['post_types'], true)) {
            return;
        }
        $has_workflow = get_post_meta($post_id, Temporal_CMS_Client::META_WORKFLOW_ID, true);
        $trigger_create = !$update && !empty($options['start_on_create']);
        $trigger_publish = !empty($options['start_on_publish']) && $post->post_status === 'publish';

        if (!$has_workflow && ($trigger_create || $trigger_publish)) {
            $workflow_id = $this->client->start_workflow($post_id, get_current_user_id());
            if ($workflow_id) {
                update_post_meta($post_id, Temporal_CMS_Client::META_WORKFLOW_ID, $workflow_id);
            }
        }
    }

    public function add_meta_box() {
        $options = $this->client->get_options();
        foreach ($options['post_types'] as $type) {
            add_meta_box('temporal-workflow', 'Temporal Workflow', [$this, 'render_meta_box'], $type, 'side', 'high');
        }
    }

    public function render_meta_box($post) {
        $workflow_id = get_post_meta($post->ID, Temporal_CMS_Client::META_WORKFLOW_ID, true);
        if (!$workflow_id) {
            echo '<p>No workflow linked.</p>';
            return;
        }
        $status = $this->client->fetch_status($workflow_id);
        echo '<p><strong>ID:</strong> ' . esc_html($workflow_id) . '</p>';
        if ($status) {
            echo '<p><strong>Stage:</strong> ' . esc_html($status['stage'] ?? 'unknown') . '</p>';
            echo '<p><strong>Pending locales:</strong> ' . esc_html(implode(', ', $status['pendingLocales'] ?? [])) . '</p>';
            $this->render_actions($post, $workflow_id, $status);
        } else {
            echo '<p>Status unavailable.</p>';
        }
    }

    protected function render_actions($post, $workflow_id, $status) {
        $stage = $status['stage'] ?? '';
        $locales = $this->client->parse_locales($this->client->get_options()['default_locales']);
        echo '<div class="temporal-actions">';
        if (in_array($stage, ['translation', 'compliance', 'awaitingApproval'], true)) {
            foreach ($locales as $locale) {
                $this->render_button($post->ID, $workflow_id, 'translationComplete', 'Mark ' . esc_html($locale) . ' translated', ['locale' => $locale]);
            }
        }
        if ($stage === 'awaitingApproval') {
            $this->render_button($post->ID, $workflow_id, 'approvalGranted', 'Approve content');
        }
        if (in_array($stage, ['scheduled', 'awaitingApproval'], true)) {
            $this->render_button($post->ID, $workflow_id, 'publishNow', 'Publish now');
        }
        echo '</div>';
    }

    protected function render_button($post_id, $workflow_id, $signal, $label, $extra = []) {
        $params = array_merge([
            'action' => 'temporal_cms_signal',
            'post_id' => $post_id,
            'workflow_id' => $workflow_id,
            'signal' => $signal,
        ], $extra);
        $nonce = wp_create_nonce($this->nonce_action($workflow_id, $signal, $extra['locale'] ?? ''));
        $params['_wpnonce'] = $nonce;
        printf(
            '<p><a href="%s" class="button button-small">%s</a></p>',
            esc_url(add_query_arg($params, admin_url('admin-post.php'))),
            esc_html($label)
        );
    }

    public function handle_signal() {
        if (!current_user_can('edit_posts')) {
            wp_die('Not allowed');
        }
        $post_id = intval($_REQUEST['post_id'] ?? 0);
        $workflow_id = sanitize_text_field($_REQUEST['workflow_id'] ?? '');
        $signal = sanitize_text_field($_REQUEST['signal'] ?? '');
        $locale = isset($_REQUEST['locale']) ? sanitize_text_field($_REQUEST['locale']) : null;
        if (!$post_id || !$workflow_id || !$signal) {
            wp_safe_redirect(admin_url());
            exit;
        }
        $nonce = $_REQUEST['_wpnonce'] ?? '';
        if (!wp_verify_nonce($nonce, $this->nonce_action($workflow_id, $signal, $locale))) {
            wp_die('Invalid nonce');
        }
        $payload = $signal === 'translationComplete' ? ['locale' => $locale] : [];
        $success = $this->client->send_signal($workflow_id, $signal, $payload);
        if ($success) {
            wp_safe_redirect(get_edit_post_link($post_id, 'redirect'));
        } else {
            wp_die('Failed to send signal');
        }
        exit;
    }

    protected function nonce_action($workflow_id, $signal, $locale = '') {
        return 'temporal_cms_' . $workflow_id . '_' . $signal . '_' . $locale;
    }
}
