<?php
/**
 * Plugin Name: Quick Fix Kit (MU)
 * Description: Minimal diagnostics + safe logging for fast WP/Woo triage (portfolio demo).
 * Version: 0.2.0
 * Author: Your Name
 */

if (!defined('ABSPATH')) {
    exit;
}

final class QFK_QuickFixKit {
    const LOG_SUBDIR = 'quickfix-kit/logs';
    const CONFIG_FLAG = 'QFK_DEBUG_LOG'; // define('QFK_DEBUG_LOG', true) in wp-config.php
    const ACTION_DOWNLOAD_REPORT = 'qfk_download_report';

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'register_admin_page']);
        add_action('admin_init', [__CLASS__, 'maybe_prepare_log_dir']);
        add_action('admin_init', [__CLASS__, 'handle_report_download']);
    }

    public static function register_admin_page(): void {
        add_management_page(
            'Quick Fix Kit',
            'Quick Fix Kit',
            'manage_options',
            'quickfix-kit',
            [__CLASS__, 'render_admin_page']
        );
    }

    public static function render_admin_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions.');
        }

        $php_version     = PHP_VERSION;
        $memory_limit    = ini_get('memory_limit');
        $wp_debug        = defined('WP_DEBUG') ? (WP_DEBUG ? 'true' : 'false') : 'not defined';
        $wp_debug_log    = defined('WP_DEBUG_LOG') ? (WP_DEBUG_LOG ? 'true' : 'false') : 'not defined';
        $qfk_log_enabled = self::is_logging_enabled() ? 'true' : 'false';

        $active_plugins = (array) get_option('active_plugins', []);
        $wc_version = self::get_woocommerce_version();

        // Best-effort cron details: enabled/disabled + last run (if available)
        $cron_status = self::get_wp_cron_status();
        $cron_last_run = self::get_wp_cron_last_run_best_effort();

        $download_url = wp_nonce_url(
            admin_url('tools.php?page=quickfix-kit&' . self::ACTION_DOWNLOAD_REPORT . '=1'),
            self::ACTION_DOWNLOAD_REPORT
        );

        echo '<div class="wrap">';
        echo '<h1>Quick Fix Kit</h1>';

        echo '<p>Minimal local diagnostics + safe logging. No destructive actions.</p>';

        echo '<table class="widefat striped" style="max-width: 900px;">';
        echo '<tbody>';
        echo '<tr><th>PHP version</th><td>' . esc_html($php_version) . '</td></tr>';
        echo '<tr><th>memory_limit</th><td>' . esc_html($memory_limit ?: 'unknown') . '</td></tr>';
        echo '<tr><th>WP_DEBUG</th><td>' . esc_html($wp_debug) . '</td></tr>';
        echo '<tr><th>WP_DEBUG_LOG</th><td>' . esc_html($wp_debug_log) . '</td></tr>';
        echo '<tr><th>QFK_DEBUG_LOG (wp-config.php)</th><td>' . esc_html($qfk_log_enabled) . '</td></tr>';
        echo '<tr><th>WP-Cron</th><td>' . esc_html($cron_status) . '</td></tr>';
        echo '<tr><th>WP-Cron last run (best-effort)</th><td>' . esc_html($cron_last_run) . '</td></tr>';
        echo '<tr><th>WooCommerce</th><td>' . esc_html($wc_version ?: 'not detected') . '</td></tr>';
        echo '</tbody>';
        echo '</table>';

        echo '<h2 style="margin-top:24px;">Active plugins</h2>';
        echo '<pre style="max-width: 900px; white-space: pre-wrap;">' . esc_html(implode("\n", $active_plugins)) . '</pre>';

        echo '<h2 style="margin-top:24px;">Diagnostics</h2>';
        echo '<p><a class="button button-primary" href="' . esc_url($download_url) . '">Generate Diagnostic Report (.txt)</a></p>';

        echo '<p><em>Report is sanitized (best-effort) to remove common secrets/tokens/keys.</em></p>';
        echo '</div>';

        // Example log line (only if enabled)
        self::log('Viewed Quick Fix Kit admin page.', ['page' => 'quickfix-kit']);
    }

    /* =========================
     * Logging
     * ========================= */

    private static function is_logging_enabled(): bool {
        return defined(self::CONFIG_FLAG) && constant(self::CONFIG_FLAG) === true;
    }

    public static function log(string $message, array $context = []): void {
        if (!self::is_logging_enabled()) {
            return;
        }

        $uploads = wp_get_upload_dir();
        if (empty($uploads['basedir']) || !is_string($uploads['basedir'])) {
            return;
        }

        $log_dir = trailingslashit($uploads['basedir']) . self::LOG_SUBDIR;
        if (!is_dir($log_dir) || !is_writable($log_dir)) {
            // Do not attempt risky chmod; just bail safely.
            return;
        }

        $file = trailingslashit($log_dir) . 'quickfix-kit-' . gmdate('Y-m-d') . '.log';

        $line = sprintf(
            "[%s] %s | %s\n",
            gmdate('c'),
            self::sanitize_for_log($message),
            wp_json_encode(self::sanitize_context($context), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        // Atomic-ish append. Suppress warnings; do not break admin.
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    private static function sanitize_for_log(string $s): string {
        // Prevent log injection/newlines.
        $s = str_replace(["\r", "\n", "\t"], ' ', $s);
        return trim($s);
    }

    private static function sanitize_context(array $context): array {
        // Shallow sanitize of values (strings).
        foreach ($context as $k => $v) {
            if (is_string($v)) {
                $context[$k] = self::sanitize_for_log($v);
            }
        }
        return $context;
    }

    public static function maybe_prepare_log_dir(): void {
        $uploads = wp_get_upload_dir();
        if (empty($uploads['basedir']) || !is_string($uploads['basedir'])) {
            return;
        }

        $log_dir = trailingslashit($uploads['basedir']) . self::LOG_SUBDIR;

        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        // Best-effort: deny web access.
        $htaccess = $log_dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "Deny from all\n");
        }
        $index = $log_dir . '/index.php';
        if (!file_exists($index)) {
            @file_put_contents($index, "<?php\n// Silence is golden.\n");
        }
    }

    /* =========================
     * Diagnostic report download
     * ========================= */

    public static function handle_report_download(): void {
        if (!is_admin()) {
            return;
        }
        if (!isset($_GET[self::ACTION_DOWNLOAD_REPORT])) {
            return;
        }
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions.');
        }

        check_admin_referer(self::ACTION_DOWNLOAD_REPORT);

        $report = self::generate_report();

        // Deliver as a download (no external calls).
        $filename = 'quickfix-kit-report-' . gmdate('Ymd-His') . '.txt';

        nocache_headers();
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');

        echo $report;
        exit;
    }

    private static function generate_report(): string {
        $lines = [];

        $lines[] = 'Quick Fix Kit — Diagnostic Report';
        $lines[] = 'Generated (UTC): ' . gmdate('c');
        $lines[] = 'Site URL: ' . home_url('/');
        $lines[] = 'Admin URL: ' . admin_url('/');
        $lines[] = str_repeat('=', 60);

        // Runtime/limits
        $lines[] = 'PHP version: ' . PHP_VERSION;
        $lines[] = 'memory_limit: ' . (ini_get('memory_limit') ?: 'unknown');
        $lines[] = 'max_execution_time: ' . (ini_get('max_execution_time') ?: 'unknown');

        // WP debug flags
        $lines[] = 'WP_DEBUG: ' . (defined('WP_DEBUG') ? (WP_DEBUG ? 'true' : 'false') : 'not defined');
        $lines[] = 'WP_DEBUG_LOG: ' . (defined('WP_DEBUG_LOG') ? (WP_DEBUG_LOG ? 'true' : 'false') : 'not defined');
        $lines[] = 'QFK_DEBUG_LOG: ' . (self::is_logging_enabled() ? 'true' : 'false');

        // Cron
        $lines[] = 'WP-Cron: ' . self::get_wp_cron_status();
        $lines[] = 'WP-Cron last run (best-effort): ' . self::get_wp_cron_last_run_best_effort();

        // Woo
        $lines[] = 'WooCommerce: ' . (self::get_woocommerce_version() ?: 'not detected');

        $lines[] = str_repeat('-', 60);
        $lines[] = 'Active plugins (paths):';
        $active_plugins = (array) get_option('active_plugins', []);
        foreach ($active_plugins as $p) {
            $lines[] = '  - ' . $p;
        }

        $lines[] = str_repeat('-', 60);
        $lines[] = 'Environment (sanitized):';

        // Collect a small set of environment-ish values; sanitize aggressively.
        $env = [
            'WP version' => get_bloginfo('version'),
            'Theme' => wp_get_theme()->get('Name') . ' ' . wp_get_theme()->get('Version'),
            'Server software' => isset($_SERVER['SERVER_SOFTWARE']) ? (string) $_SERVER['SERVER_SOFTWARE'] : 'unknown',
            'PHP SAPI' => PHP_SAPI,
            'DB host' => defined('DB_HOST') ? (string) DB_HOST : 'not defined',
        ];

        foreach ($env as $k => $v) {
            $lines[] = $k . ': ' . self::sanitize_report_value((string) $v);
        }

        $lines[] = str_repeat('=', 60);
        $lines[] = 'End of report.';

        $report = implode("\n", $lines);

        // Best-effort global scrub for common secrets patterns.
        return self::scrub_secrets($report);
    }

    private static function sanitize_report_value(string $v): string {
        // Remove newlines and tabs; keep it single-line.
        $v = str_replace(["\r", "\n", "\t"], ' ', $v);
        return trim($v);
    }

    private static function scrub_secrets(string $text): string {
        // Best-effort redaction: common key/token/password patterns.
        $patterns = [
            // key=value patterns
            '/(api[_-]?key\s*=\s*)([^\s]+)/i',
            '/(secret\s*=\s*)([^\s]+)/i',
            '/(token\s*=\s*)([^\s]+)/i',
            '/(password\s*=\s*)([^\s]+)/i',
            '/(passwd\s*=\s*)([^\s]+)/i',

            // header-like
            '/(authorization:\s*bearer\s+)([^\s]+)/i',

            // JWT-ish
            '/\beyJ[a-zA-Z0-9_-]+\.[a-zA-Z0-9_-]+\.[a-zA-Z0-9_-]+\b/',

            // Long random-looking strings (very rough): 32+ of base64/hex-ish
            '/\b[A-Fa-f0-9]{32,}\b/',
            '/\b[A-Za-z0-9\/\+]{40,}={0,2}\b/',
        ];

        foreach ($patterns as $p) {
            $text = preg_replace($p, '$1[REDACTED]', $text);
        }

        return $text;
    }

    /* =========================
     * Helpers
     * ========================= */

    private static function get_woocommerce_version(): ?string {
        if (defined('WC_VERSION')) {
            return (string) WC_VERSION;
        }
        if (class_exists('WooCommerce')) {
            if (function_exists('WC') && is_object(WC()) && isset(WC()->version)) {
                return (string) WC()->version;
            }
            return 'detected (version unknown)';
        }
        return null;
    }

    private static function get_wp_cron_status(): string {
        $disabled = (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON);
        return $disabled ? 'disabled (DISABLE_WP_CRON=true)' : 'enabled';
    }

    private static function get_wp_cron_last_run_best_effort(): string {
        // WP stores cron array in option 'cron'. No official "last run" timestamp, so we approximate:
        // - Look for the most recent "timestamp <= now" among scheduled events as a proxy for last due run.
        $cron = get_option('cron');
        if (!is_array($cron)) {
            return 'unknown';
        }

        $now = time();
        $candidates = [];

        foreach ($cron as $timestamp => $hooks) {
            if (!is_numeric($timestamp)) {
                continue;
            }
            $ts = (int) $timestamp;
            if ($ts <= $now) {
                $candidates[] = $ts;
            }
        }

        if (empty($candidates)) {
            return 'unknown (no due events found)';
        }

        rsort($candidates);
        return gmdate('c', $candidates[0]);
    }
}

QFK_QuickFixKit::init();