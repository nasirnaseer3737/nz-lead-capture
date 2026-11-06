<?php
/**
 * Admin: lead inbox (list, filter, status, delete), CSV export and settings.
 * Every action checks manage_options + a nonce; no action runs on GET alone
 * except the export, which also requires both.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NZLC_Admin {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
        add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
    }

    public static function menu() {
        add_menu_page(
            __( 'Leads', 'nz-lead-capture' ),
            __( 'Leads', 'nz-lead-capture' ),
            'manage_options',
            'nzlc-leads',
            array( __CLASS__, 'render_list' ),
            'dashicons-email-alt',
            26
        );
        add_submenu_page(
            'nzlc-leads',
            __( 'Lead Settings', 'nz-lead-capture' ),
            __( 'Settings', 'nz-lead-capture' ),
            'manage_options',
            'nzlc-settings',
            array( __CLASS__, 'render_settings' )
        );
    }

    public static function register_settings() {
        register_setting( 'nzlc_settings', 'nzlc_notify_email', array( 'sanitize_callback' => 'sanitize_email' ) );
        register_setting( 'nzlc_settings', 'nzlc_success_message', array( 'sanitize_callback' => 'sanitize_text_field' ) );
        register_setting( 'nzlc_settings', 'nzlc_seo_enabled', array( 'sanitize_callback' => 'sanitize_text_field' ) );
        register_setting( 'nzlc_settings', 'nzlc_business_name', array( 'sanitize_callback' => 'sanitize_text_field' ) );
    }

    /**
     * Handle status changes, deletes and CSV export from the list screen.
     */
    public static function handle_actions() {
        if ( ! isset( $_GET['page'] ) || 'nzlc-leads' !== $_GET['page'] ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $action = isset( $_GET['nzlc_action'] ) ? sanitize_key( wp_unslash( $_GET['nzlc_action'] ) ) : '';
        if ( ! $action ) {
            return;
        }
        check_admin_referer( 'nzlc_admin_' . $action );
        if ( 'export' === $action ) {
            nocache_headers();
            header( 'Content-Type: text/csv; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename=nzlc-leads-' . gmdate( 'Y-m-d' ) . '.csv' );
            NZLC_Storage::export_csv();
            exit;
        }
        $id = isset( $_GET['lead_id'] ) ? (int) $_GET['lead_id'] : 0;
        if ( 'delete' === $action && $id ) {
            NZLC_Storage::delete( $id );
        }
        if ( 'status' === $action && $id ) {
            $status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
            NZLC_Storage::update_status( $id, $status );
        }
        wp_safe_redirect( remove_query_arg( array( 'nzlc_action', 'lead_id', 'status', '_wpnonce' ) ) );
        exit;
    }

    public static function render_list() {
        $status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
        $page   = isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1;
        $data   = NZLC_Storage::list( $status, $page, 20 );
        $counts = NZLC_Storage::count_by_status();
        $export = wp_nonce_url( add_query_arg( 'nzlc_action', 'export' ), 'nzlc_admin_export' );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Leads', 'nz-lead-capture' ); ?>
                <a class="page-title-action" href="<?php echo esc_url( $export ); ?>"><?php esc_html_e( 'Export CSV', 'nz-lead-capture' ); ?></a>
            </h1>
            <p><?php printf( esc_html__( '%d leads in total.', 'nz-lead-capture' ), (int) $counts['all'] ); ?></p>
            <ul class="subsubsub">
                <?php foreach ( array_merge( array( 'all' ), NZLC_Storage::allowed_statuses() ) as $key ) : ?>
                    <li><a href="<?php echo esc_url( add_query_arg( 'status', 'all' === $key ? '' : $key ) ); ?>" class="<?php echo $status === $key || ( '' === $status && 'all' === $key ) ? 'current' : ''; ?>">
                        <?php echo esc_html( ucfirst( $key ) ); ?> <span class="count">(<?php echo (int) $counts[ $key ]; ?>)</span>
                    </a> | </li>
                <?php endforeach; ?>
            </ul>
            <table class="wp-list-table widefat fixed striped">
                <thead><tr>
                    <th><?php esc_html_e( 'Name', 'nz-lead-capture' ); ?></th>
                    <th><?php esc_html_e( 'Contact', 'nz-lead-capture' ); ?></th>
                    <th><?php esc_html_e( 'Service', 'nz-lead-capture' ); ?></th>
                    <th><?php esc_html_e( 'Message', 'nz-lead-capture' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'nz-lead-capture' ); ?></th>
                    <th><?php esc_html_e( 'Date', 'nz-lead-capture' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'nz-lead-capture' ); ?></th>
                </tr></thead>
                <tbody>
                <?php if ( empty( $data['rows'] ) ) : ?>
                    <tr><td colspan="7"><?php esc_html_e( 'No leads yet. Add the shortcode [nz_lead_form] to a page and the first submission will appear here.', 'nz-lead-capture' ); ?></td></tr>
                <?php endif; ?>
                <?php foreach ( $data['rows'] as $lead ) : ?>
                    <tr>
                        <td><strong><?php echo esc_html( $lead->name ); ?></strong></td>
                        <td><?php echo esc_html( $lead->email ); ?><br /><?php echo esc_html( $lead->phone ); ?></td>
                        <td><?php echo esc_html( $lead->service ); ?></td>
                        <td><?php echo esc_html( wp_trim_words( $lead->message, 18 ) ); ?></td>
                        <td><?php echo esc_html( ucfirst( $lead->status ) ); ?></td>
                        <td><?php echo esc_html( $lead->created_at ); ?></td>
                        <td>
                            <?php foreach ( NZLC_Storage::allowed_statuses() as $s ) : ?>
                                <?php if ( $s !== $lead->status ) : ?>
                                    <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'nzlc_action' => 'status', 'lead_id' => $lead->id, 'status' => $s ) ), 'nzlc_admin_status' ) ); ?>"><?php echo esc_html( ucfirst( $s ) ); ?></a><br />
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'nzlc_action' => 'delete', 'lead_id' => $lead->id ) ), 'nzlc_admin_delete' ) ); ?>" onclick="return confirm('<?php esc_attr_e( 'Delete this lead?', 'nz-lead-capture' ); ?>')"><?php esc_html_e( 'Delete', 'nz-lead-capture' ); ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p><?php printf( esc_html__( 'Page %1$d — %2$d leads shown of %3$d.', 'nz-lead-capture' ), $data['page'], count( $data['rows'] ), $data['total'] ); ?></p>
        </div>
        <?php
    }

    public static function render_settings() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Lead Capture Settings', 'nz-lead-capture' ); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'nzlc_settings' ); ?>
                <table class="form-table">
                    <tr><th scope="row"><label for="nzlc_notify_email"><?php esc_html_e( 'Notification email', 'nz-lead-capture' ); ?></label></th>
                        <td><input id="nzlc_notify_email" name="nzlc_notify_email" type="email" value="<?php echo esc_attr( get_option( 'nzlc_notify_email' ) ); ?>" class="regular-text" /></td></tr>
                    <tr><th scope="row"><label for="nzlc_success_message"><?php esc_html_e( 'Success message', 'nz-lead-capture' ); ?></label></th>
                        <td><input id="nzlc_success_message" name="nzlc_success_message" type="text" value="<?php echo esc_attr( get_option( 'nzlc_success_message' ) ); ?>" class="large-text" /></td></tr>
                    <tr><th scope="row"><label for="nzlc_business_name"><?php esc_html_e( 'Business name (schema)', 'nz-lead-capture' ); ?></label></th>
                        <td><input id="nzlc_business_name" name="nzlc_business_name" type="text" value="<?php echo esc_attr( get_option( 'nzlc_business_name' ) ); ?>" class="regular-text" /></td></tr>
                    <tr><th scope="row"><?php esc_html_e( 'SEO output', 'nz-lead-capture' ); ?></th>
                        <td><label><input name="nzlc_seo_enabled" type="checkbox" value="1" <?php checked( get_option( 'nzlc_seo_enabled' ), '1' ); ?> /> <?php esc_html_e( 'Add meta description, Open Graph and LocalBusiness schema', 'nz-lead-capture' ); ?></label></td></tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <h2><?php esc_html_e( 'How to use', 'nz-lead-capture' ); ?></h2>
            <p><code>[nz_lead_form]</code> — <?php esc_html_e( 'default form.', 'nz-lead-capture' ); ?><br />
            <code>[nz_lead_form title="Book a free call" button="Book now"]</code></p>
        </div>
        <?php
    }
}
