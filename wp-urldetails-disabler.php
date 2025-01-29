<?php
/*
* Plugin Name:          WP-URLDetails Disabler
* Description:          Blocks outgoing requests with the WP-URLDetails user-agent.
* Version:              1.1
* Author:               MrBoombastic
* Requires at least:    6.7.1
* Requires PHP:         8.2
* Plugin URI:           https://github.com/MrBoombastic/wp-urldetails-disabler
* Author URI:           https://amroz.xyz
* License:              WTFPL
* License URI:          https://choosealicense.com/licenses/wtfpl/
*/

// Prepare database, create table if not exists

function wp_urldetails_disabler_create_table()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'wp_urldetails_disabler_logs';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        user_agent TEXT NOT NULL,
        request_url TEXT NOT NULL
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

register_activation_hook(__FILE__, 'wp_urldetails_disabler_create_table');

// Logging - log user-agent and request URL, limit to 100 entries

function log_request($user_agent, $request_url)
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'wp_urldetails_disabler_logs';

    $wpdb->insert(
        $table_name,
        [
            'user_agent' => $user_agent,
            'request_url' => $request_url
        ],
        ['%s', '%s']
    );

    $total_entries = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    if ($total_entries > 100) {
        $wpdb->query("DELETE FROM $table_name ORDER BY id ASC LIMIT " . ($total_entries - 100));
    }
}


// Settings boilerplate and shitfest begin

function register_settings()
{
    register_setting('wp_urldetails_disabler_settings_group', 'disable_requests');

    add_settings_section(
        'block_requests_settings_section',
        __('Settings', 'wp-urldetails-disabler'),
        'settings_section_callback',
        'wp-urldetails-disabler-settings'
    );

    add_settings_field(
        'disable_requests',
        __('Disable requests', 'wp-urldetails-disabler'),
        'enable_callback',
        'wp-urldetails-disabler-settings',
        'block_requests_settings_section'
    );
}

function settings_section_callback()
{
    echo '<p>' . esc_html__('Configure plugin settings for your site.', 'wp-urldetails-disabler') . '</p>';
}

add_action('admin_init', 'register_settings');

function add_settings_page()
{
    add_options_page(
        __('WP-URLDetails Disabler', 'wp-urldetails-disabler'),
        __('Disable WP-URLDetails', 'wp-urldetails-disabler'),
        'manage_options',
        'wp-urldetails-disabler-settings',
        'settings_page'
    );
}

add_action('admin_menu', 'add_settings_page');

// Display settings and logs page

function settings_page()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'wp_urldetails_disabler_logs';
    $logs = $wpdb->get_results("SELECT * FROM $table_name ORDER BY timestamp DESC");
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('WP-URLDetails Disabler', 'wp-urldetails-disabler'); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('wp_urldetails_disabler_settings_group');
            do_settings_sections('wp-urldetails-disabler-settings');
            submit_button();
            ?>
        </form>

        <h2><?php esc_html_e('Filtered requests (last 100)', 'logged-requests'); ?></h2>
        <form method="post">
            <?php submit_button(__('Clear logs', 'logged-requests'), 'delete', 'clear_log', false); ?>
        </form>

        <?php if (!empty($logs)) : ?>
            <table class="widefat fixed">
                <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>User-Agent</th>
                    <th>URL</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($logs as $log) : ?>
                    <tr>
                        <td><?php echo esc_html($log->timestamp); ?></td>
                        <td><?php echo esc_html($log->user_agent); ?></td>
                        <td><?php echo esc_url($log->request_url); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p><?php esc_html_e('No blocked requests logged.', 'logged-requests'); ?></p>
        <?php endif; ?>
    </div>
    <?php
}

// Clear logs button
function block_requests_clear_log()
{
    if (isset($_POST['clear_log']) && current_user_can('manage_options')) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wp_urldetails_disabler_logs';
        $wpdb->query("TRUNCATE TABLE $table_name");

        wp_redirect(admin_url('options-general.php?page=wp-urldetails-disabler-settings'));
        exit;
    }
}

add_action('admin_init', 'block_requests_clear_log');


function enable_callback()
{
    $option = get_option('disable_requests', '1');
    ?>
    <label>
        <input type="checkbox" name="disable_requests" value="1" <?php checked($option, '1'); ?>>
        <?php esc_html_e('Disable requests with the WP-URLDetails user-agent', 'wp-urldetails-disabler'); ?>
    </label>
    <?php
}

// Enable settings on plugin activation
function wp_urldetails_disabler_activate()
{
    wp_urldetails_disabler_create_table(); // Ensure table creation

    // Ensure 'disable_requests' option is set to '1' if it does not exist
    if (get_option('disable_requests') === false) {
        update_option('disable_requests', '1');
    }
}

register_activation_hook(__FILE__, 'wp_urldetails_disabler_activate');

// Settings boilerplate and shitfest end

// Actual blocking logic

function block_requests_filter($pre, $args, $url)
{
    $is_blocking_enabled = get_option('disable_requests', '1'); // Default to enabled

    if ($is_blocking_enabled && isset($args['user-agent']) && strpos($args['user-agent'], 'WP-URLDetails') !== false) {
        log_request($args['user-agent'], $url);
        return new WP_Error('blocked', 'Requests with the WP-URLDetails user-agent are disabled by the plugin.');
    }
    return $pre;
}

add_filter('pre_http_request', 'block_requests_filter', 10, 3);
