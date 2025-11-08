<?php
/**
 * Plugin Name: Temporal CMS Sync
 * Description: Starts Temporal workflows from WordPress content changes and surfaces workflow status/actions in the editor.
 * Version: 0.1.0
 * Author: Temporal CMS Prototype
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/includes/class-temporal-client.php';
require_once __DIR__ . '/includes/class-temporal-admin.php';
require_once __DIR__ . '/includes/class-temporal-hooks.php';

function temporal_cms_sync() {
    static $plugin = null;
    if ($plugin === null) {
        $plugin = new Temporal_CMS_Hooks(new Temporal_CMS_Client());
    }
    return $plugin;
}

temporal_cms_sync();
