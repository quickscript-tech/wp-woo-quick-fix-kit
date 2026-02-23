<?php
/**
 * Plugin Name: Quick Fix Kit (MU Loader)
 * Description: Loader for Quick Fix Kit MU Plugin.
 * Version: 0.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$plugin_file = __DIR__ . '/quickfix-kit/quickfix-kit.php';
if (file_exists($plugin_file)) {
    require_once $plugin_file;
}
