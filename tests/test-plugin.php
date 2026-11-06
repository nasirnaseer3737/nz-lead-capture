<?php
/**
 * Offline test: loads the plugin with stubbed WordPress functions and
 * checks the shortcode registration, validation rules and storage SQL.
 * Run: php tests/test-plugin.php
 */
error_reporting( E_ALL );

define( 'ABSPATH', '/tmp/wp-stub/' );

// --- Minimal WordPress stubs -------------------------------------------
$GLOBALS['nzlc_hooks']     = array();
$GLOBALS['nzlc_shortcodes'] = array();
$GLOBALS['nzlc_options']    = array( 'nzlc_success_message' => 'Thanks!', 'nzlc_notify_email' => 'admin@example.com', 'admin_email' => 'admin@example.com' );

function add_action( $hook, $cb, $p = 10, $n = 1 ) { $GLOBALS['nzlc_hooks'][ $hook ][] = $cb; }
function add_filter( $hook, $cb, $p = 10, $n = 1 ) { $GLOBALS['nzlc_hooks'][ $hook ][] = $cb; }
function add_shortcode( $tag, $cb ) { $GLOBALS['nzlc_shortcodes'][ $tag ] = $cb; }
function register_activation_hook( $f, $cb ) {}
function register_deactivation_hook( $f, $cb ) {}
function plugin_dir_path( $f ) { return dirname( $f ) . '/'; }
function plugin_dir_url( $f ) { return 'https://example.test/wp-content/plugins/nz-lead-capture/'; }
function plugin_basename( $f ) { return 'nz-lead-capture/nz-lead-capture.php'; }
function get_option( $k, $d = false ) { return $GLOBALS['nzlc_options'][ $k ] ?? $d; }
function add_option( $k, $v ) { $GLOBALS['nzlc_options'][ $k ] = $v; }
function get_bloginfo( $k ) { return 'Test Site'; }
function __( $s, $d = null ) { return $s; }
function esc_html__( $s, $d = null ) { return $s; }
function esc_attr__( $s, $d = null ) { return $s; }
function esc_html_e( $s, $d = null ) { echo esc_html( $s ); }
function esc_attr_e( $s, $d = null ) { echo esc_attr( $s ); }
function _e( $s, $d = null ) { echo $s; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s ) { return $s; }
function esc_url_raw( $s ) { return $s; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_email( $s ) { return filter_var( trim( (string) $s ), FILTER_SANITIZE_EMAIL ); }
function sanitize_key( $s ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $s ) ); }
function wp_unslash( $v ) { return is_string( $v ) ? stripslashes( $v ) : $v; }
function is_email( $e ) { return (bool) filter_var( $e, FILTER_VALIDATE_EMAIL ); }
function shortcode_atts( $defaults, $atts, $tag = '' ) { return array_merge( $defaults, (array) $atts ); }
function get_permalink() { return 'https://example.test/contact/'; }
function wp_nonce_field( $a, $n ) { $h = '<input type="hidden" name="' . $n . '" value="testnonce" />'; echo $h; return $h; }
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }
function is_singular() { return true; }
function get_the_excerpt() { return 'Test excerpt for SEO description testing.'; }
function current_time( $t ) { return '2026-11-06 12:00:00'; }
function wp_mail( $to, $s, $b ) { $GLOBALS['nzlc_mail'][] = array( $to, $s ); return true; }

// Fake $wpdb that records queries instead of running them.
class Fake_WPDB {
    public $prefix = 'wp_';
    public $insert_id = 42;
    public $queries = array();
    public function get_charset_collate() { return 'DEFAULT CHARSET=utf8mb4'; }
    public function prepare( $q, ...$args ) { return vsprintf( str_replace( array( '%d', '%s' ), array( '%d', "'%s'" ), $q ), $args ); }
    public function insert( $t, $d, $f ) { $this->queries[] = "INSERT INTO $t"; return 1; }
    public function get_row( $q ) { return null; }
    public function get_results( $q, $o = null ) { return array(); }
    public function get_var( $q ) { return 0; }
    public function update( $t, $d, $w, $a = null, $b = null ) { return 1; }
    public function delete( $t, $w, $a = null ) { return 1; }
}
$GLOBALS['wpdb'] = new Fake_WPDB();

// --- Load the plugin ----------------------------------------------------
require dirname( __DIR__ ) . '/nz-lead-capture.php';

// Boot: run the plugins_loaded callbacks the plugin registered.
foreach ( $GLOBALS['nzlc_hooks']['plugins_loaded'] ?? array() as $cb ) {
    $cb();
}

function check( $label, $cond ) {
    echo ( $cond ? "PASS" : "FAIL" ) . ": $label\n";
    if ( ! $cond ) {
        $GLOBALS['nzlc_failed'] = true;
    }
}

check( 'shortcode [nz_lead_form] registered', isset( $GLOBALS['nzlc_shortcodes']['nz_lead_form'] ) );
check( 'AJAX actions registered', isset( $GLOBALS['nzlc_hooks']['wp_ajax_nzlc_submit'] ) && isset( $GLOBALS['nzlc_hooks']['wp_ajax_nopriv_nzlc_submit'] ) );

$html = call_user_func( $GLOBALS['nzlc_shortcodes']['nz_lead_form'], array(), '' );
check( 'form HTML has name/email/message fields', str_contains( $html, 'nzlc_name' ) && str_contains( $html, 'nzlc_email' ) && str_contains( $html, 'nzlc_message' ) );
check( 'form HTML has honeypot field', str_contains( $html, 'nzlc_company' ) );
check( 'form HTML has nonce field', str_contains( $html, 'nzlc_nonce' ) );

list( $data, $errors ) = NZLC_Form::validate( array(
    'nzlc_name' => 'Test User', 'nzlc_email' => 'test@example.com',
    'nzlc_phone' => '+92 300 1234567', 'nzlc_service' => 'wordpress',
    'nzlc_message' => 'I need a new WordPress website for my business.',
    'nzlc_company' => '',
) );
check( 'valid lead passes validation', empty( $errors ) && 'test@example.com' === $data['email'] );

list( $data2, $errors2 ) = NZLC_Form::validate( array(
    'nzlc_name' => 'A', 'nzlc_email' => 'not-an-email', 'nzlc_message' => 'hi', 'nzlc_company' => '',
) );
check( 'bad name/email/short message rejected', count( $errors2 ) >= 3 );

list( $data3, $errors3 ) = NZLC_Form::validate( array(
    'nzlc_name' => 'Bot', 'nzlc_email' => 'bot@example.com', 'nzlc_message' => 'Buy cheap stuff now, click here.',
    'nzlc_company' => 'SEO Company',
) );
check( 'honeypot-filled submission flagged as spam', in_array( 'spam', $errors3, true ) );

$lead_id = NZLC_Form::store_and_notify( array(
    'name' => 'Test User', 'email' => 'test@example.com', 'phone' => '', 'service' => 'wordpress',
    'message' => 'Hello', 'source' => 'https://example.test/contact/',
) );
check( 'validated lead stored (insert id returned)', 42 === $lead_id );
check( 'owner notification queued', ! empty( $GLOBALS['nzlc_mail'] ) );

check( 'storage allows new/contacted/won/lost', NZLC_Storage::allowed_statuses() === array( 'new', 'contacted', 'won', 'lost' ) );
check( 'storage rejects bad status update', false === NZLC_Storage::update_status( 1, 'bogus' ) );
check( 'SEO description capped at 160 chars', mb_strlen( NZLC_SEO::description() ) <= 160 );

$lines = 0;
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( dirname( __DIR__ ), FilesystemIterator::SKIP_DOTS ) ) as $f ) {
    if ( preg_match( '/\.(php|js|css)$/', $f->getFilename() ) && ! str_contains( $f->getPathname(), '/tests/' ) ) {
        $lines += count( file( $f->getPathname() ) );
    }
}
echo "Plugin code lines (PHP/JS/CSS, excl. tests): $lines\n";
check( 'plugin exceeds 300 lines of code', $lines > 300 );

echo $GLOBALS['nzlc_failed'] ?? false ? "RESULT: FAILED\n" : "RESULT: ALL PASSED\n";
exit( ( $GLOBALS['nzlc_failed'] ?? false ) ? 1 : 0 );
