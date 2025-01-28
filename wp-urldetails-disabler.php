<?php
/*
* Plugin Name:          WP-URLDetails Disabler
* Description:          Blocks outgoing requests with the WP-URLDetails user-agent.
* Version:              1.0
* Author:               MrBoombastic
* Requires at least:    6.7.1
* Requires PHP:         8.2
* Plugin URI:           https://github.com/MrBoombastic/wp-urldetails-disabler
* Author URI:           https://amroz.xyz
* License:              WTFPL
* License URI:          https://choosealicense.com/licenses/wtfpl/
*/

// Settings boilerplate and shitfest begin

function block_requests_register_settings()
{
    register_setting('block_requests_settings_group', 'block_requests_enable');

    add_settings_section(
        'block_requests_settings_section',
        __('Request Blocking Settings', 'wp-urldetails-disabler'),
        'block_requests_settings_section_callback',
        'wp-urldetails-disabler-settings'
    );

    add_settings_field(
        'block_requests_enable',
        __('Enable', 'wp-urldetails-disabler'),
        'block_requests_enable_callback',
        'wp-urldetails-disabler-settings',
        'block_requests_settings_section'
    );
}

function block_requests_settings_section_callback()
{
    echo '<p>' . esc_html__('Configure the request blocking settings for your site.', 'wp-urldetails-disabler') . '</p>';
}

add_action('admin_init', 'block_requests_register_settings');

function block_requests_add_settings_page()
{
    add_options_page(
        __('WP-URLDetails Disabler', 'wp-urldetails-disabler'),
        __('Block WP-URLDetails', 'wp-urldetails-disabler'),
        'manage_options',
        'wp-urldetails-disabler-settings',
        'block_requests_settings_page'
    );
}

add_action('admin_menu', 'block_requests_add_settings_page');

function block_requests_settings_page()
{
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('WP-URLDetails Disabler', 'wp-urldetails-disabler'); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('block_requests_settings_group');
            do_settings_sections('wp-urldetails-disabler-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}


function block_requests_enable_callback()
{
    $option = get_option('block_requests_enable', '0');
    ?>
    <label>
        <input type="checkbox" name="block_requests_enable" value="1" <?php checked($option, '1'); ?>>
        <?php esc_html_e('Enable blocking requests with the WP-URLDetails user agent.', 'wp-urldetails-disabler'); ?>
    </label>
    <?php
}

// Settings boilerplate and shitfest end

// Actual blocking logic

function block_requests_filter($pre, $args, $url)
{
    $is_blocking_enabled = get_option('block_requests_enable', '0');

    if ($is_blocking_enabled === '1' && isset($args['user-agent']) && strpos($args['user-agent'], 'WP-URLDetails') !== false) {
        return new WP_Error('blocked', 'Requests with the WP-URLDetails user agent are blocked by the plugin.');
    }
    return $pre;
}

add_filter('pre_http_request', 'block_requests_filter', 10, 3);
