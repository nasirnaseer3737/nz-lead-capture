<?php
/**
 * Plugin Name:       NZ Lead Capture + SEO
 * Plugin URI:        https://nzgenix.digital/
 * Description:       Lead-capture form (shortcode + AJAX), admin lead inbox with statuses and CSV export, and lightweight on-page SEO (meta description, Open Graph and LocalBusiness schema). Built as a portfolio demo by Nasir Naseer.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Nasir Naseer
 * Author URI:        https://nzgenix.digital/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       nz-lead-capture
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'NZLC_VERSION', '1.0.0' );
define( 'NZLC_PATH', plugin_dir_path( __FILE__ ) );
define( 'NZLC_URL', plugin_dir_url( __FILE__ ) );
define( 'NZLC_TABLE', 'nzlc_leads' );

require_once NZLC_PATH . 'includes/class-nzlc-storage.php';
require_once NZLC_PATH . 'includes/class-nzlc-form.php';
require_once NZLC_PATH . 'includes/class-nzlc-admin.php';
require_once NZLC_PATH . 'includes/class-nzlc-seo.php';
require_once NZLC_PATH . 'includes/class-nzlc-rest.php';

/**
 * Activation: create the leads table and set sensible defaults.
 * The table is created with dbDelta so re-activation / upgrades are safe.
 */
function nzlc_activate() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $table   = $wpdb->prefix . NZLC_TABLE;
    $charset = $wpdb->get_charset_collate();
    $sql     = "CREATE TABLE {$table} (
        id mediumint(9) UNSIGNED NOT NULL AUTO_INCREMENT,
        name varchar(120) NOT NULL,
        email varchar(120) NOT NULL,
        phone varchar(40) DEFAULT '',
        service varchar(80) DEFAULT '',
        message text NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'new',
        source varchar(120) DEFAULT '',
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY email (email),
        KEY status (status),
        KEY created_at (created_at)
    ) {$charset};";
    dbDelta( $sql );
    add_option( 'nzlc_notify_email', get_option( 'admin_email' ) );
    add_option( 'nzlc_success_message', __( 'Thanks! We will get back to you within one business day.', 'nz-lead-capture' ) );
    add_option( 'nzlc_seo_enabled', '1' );
    add_option( 'nzlc_business_name', get_bloginfo( 'name' ) );
}
register_activation_hook( __FILE__, 'nzlc_activate' );

/**
 * Deactivation: stop the cron-free plugin cleanly. Data is kept so a
 * re-activation does not wipe the lead inbox. See uninstall.php for removal.
 */
function nzlc_deactivate() {
    wp_clear_scheduled_hook( 'nzlc_daily_cleanup' );
}
register_deactivation_hook( __FILE__, 'nzlc_deactivate' );

/**
 * Boot the plugin once WordPress has loaded its APIs.
 */
function nzlc_boot() {
    NZLC_Storage::init();
    NZLC_Form::init();
    NZLC_Admin::init();
    NZLC_SEO::init();
    NZLC_REST::init();
}
add_action( 'plugins_loaded', 'nzlc_boot' );

/**
 * Load plugin text domain for translations.
 */
function nzlc_load_textdomain() {
    load_plugin_textdomain( 'nz-lead-capture', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'nzlc_load_textdomain' );

/**
 * Enqueue front-end assets only on pages that actually contain the form,
 * so the plugin adds no weight to the rest of the site.
 */
function nzlc_enqueue_assets() {
    if ( ! is_singular() ) {
        return;
    }
    global $post;
    if ( $post && has_shortcode( $post->post_content, 'nz_lead_form' ) ) {
        wp_enqueue_style( 'nzlc-frontend', NZLC_URL . 'assets/css/frontend.css', array(), NZLC_VERSION );
        wp_enqueue_script( 'nzlc-frontend', NZLC_URL . 'assets/js/frontend.js', array(), NZLC_VERSION, true );
        wp_localize_script(
            'nzlc-frontend',
            'NZLC',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'nzlc_submit' ),
            )
        );
    }
}
add_action( 'wp_enqueue_scripts', 'nzlc_enqueue_assets' );
