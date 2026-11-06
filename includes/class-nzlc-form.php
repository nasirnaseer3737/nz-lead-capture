<?php
/**
 * Public lead form: shortcode [nz_lead_form], AJAX + plain-POST submission,
 * nonce + honeypot spam protection, server-side validation.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NZLC_Form {

    public static function init() {
        add_shortcode( 'nz_lead_form', array( __CLASS__, 'render_shortcode' ) );
        add_action( 'wp_ajax_nzlc_submit', array( __CLASS__, 'ajax_submit' ) );
        add_action( 'wp_ajax_nopriv_nzlc_submit', array( __CLASS__, 'ajax_submit' ) );
        add_action( 'init', array( __CLASS__, 'handle_plain_post' ) );
    }

    public static function render_shortcode( $atts ) {
        $atts = shortcode_atts(
            array(
                'title'  => __( 'Get a free quote', 'nz-lead-capture' ),
                'button' => __( 'Send my request', 'nz-lead-capture' ),
            ),
            $atts,
            'nz_lead_form'
        );
        $services = NZLC_Storage::allowed_services();
        ob_start();
        ?>
        <form class="nzlc-form" method="post" action="<?php echo esc_url( get_permalink() ); ?>" novalidate>
            <h3 class="nzlc-form-title"><?php echo esc_html( $atts['title'] ); ?></h3>
            <div class="nzlc-notice" hidden></div>
            <p>
                <label for="nzlc-name"><?php esc_html_e( 'Your name *', 'nz-lead-capture' ); ?></label>
                <input id="nzlc-name" name="nzlc_name" type="text" required maxlength="120" autocomplete="name" />
            </p>
            <p>
                <label for="nzlc-email"><?php esc_html_e( 'Email *', 'nz-lead-capture' ); ?></label>
                <input id="nzlc-email" name="nzlc_email" type="email" required maxlength="120" autocomplete="email" />
            </p>
            <p>
                <label for="nzlc-phone"><?php esc_html_e( 'Phone / WhatsApp', 'nz-lead-capture' ); ?></label>
                <input id="nzlc-phone" name="nzlc_phone" type="tel" maxlength="40" autocomplete="tel" />
            </p>
            <p>
                <label for="nzlc-service"><?php esc_html_e( 'What do you need?', 'nz-lead-capture' ); ?></label>
                <select id="nzlc-service" name="nzlc_service">
                    <?php foreach ( $services as $key => $label ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p>
                <label for="nzlc-message"><?php esc_html_e( 'Tell us about your project *', 'nz-lead-capture' ); ?></label>
                <textarea id="nzlc-message" name="nzlc_message" rows="4" required maxlength="2000"></textarea>
            </p>
            <?php // Honeypot: hidden from humans, filled by naive bots. ?>
            <p class="nzlc-honeypot" aria-hidden="true">
                <label>Leave this field empty<input name="nzlc_company" type="text" tabindex="-1" autocomplete="off" /></label>
            </p>
            <?php wp_nonce_field( 'nzlc_submit', 'nzlc_nonce' ); ?>
            <input type="hidden" name="nzlc_submitted" value="1" />
            <p><button class="nzlc-submit" type="submit"><?php echo esc_html( $atts['button'] ); ?></button></p>
        </form>
        <?php
        return ob_get_clean();
    }

    /**
     * Validate + sanitize raw input. Returns [ $data, $errors ].
     * Shared by the AJAX and plain-POST paths so both behave identically.
     */
    public static function validate( array $input ) {
        $errors = array();
        $data   = array(
            'name'    => isset( $input['nzlc_name'] ) ? sanitize_text_field( wp_unslash( $input['nzlc_name'] ) ) : '',
            'email'   => isset( $input['nzlc_email'] ) ? sanitize_email( wp_unslash( $input['nzlc_email'] ) ) : '',
            'phone'   => isset( $input['nzlc_phone'] ) ? sanitize_text_field( wp_unslash( $input['nzlc_phone'] ) ) : '',
            'service' => isset( $input['nzlc_service'] ) ? sanitize_key( wp_unslash( $input['nzlc_service'] ) ) : '',
            'message' => isset( $input['nzlc_message'] ) ? sanitize_textarea_field( wp_unslash( $input['nzlc_message'] ) ) : '',
            'source'  => isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
        );
        if ( mb_strlen( $data['name'] ) < 2 ) {
            $errors[] = __( 'Please enter your name.', 'nz-lead-capture' );
        }
        if ( ! is_email( $data['email'] ) ) {
            $errors[] = __( 'Please enter a valid email address.', 'nz-lead-capture' );
        }
        if ( mb_strlen( $data['message'] ) < 10 ) {
            $errors[] = __( 'Please tell us a little more about your project (at least 10 characters).', 'nz-lead-capture' );
        }
        if ( $data['service'] && ! array_key_exists( $data['service'], NZLC_Storage::allowed_services() ) ) {
            $errors[] = __( 'Please choose a valid service.', 'nz-lead-capture' );
        }
        // Honeypot must stay empty; a filled one is a bot, not a user error.
        if ( ! empty( $input['nzlc_company'] ) ) {
            $errors[] = 'spam';
        }
        return array( $data, $errors );
    }

    /**
     * Store a validated lead and notify the site owner. Returns the lead ID.
     */
    public static function store_and_notify( array $data ) {
        $lead_id = NZLC_Storage::insert( $data );
        if ( ! $lead_id ) {
            return 0;
        }
        $notify = get_option( 'nzlc_notify_email', get_option( 'admin_email' ) );
        if ( $notify && is_email( $notify ) ) {
            $subject = sprintf(
                /* translators: %s: lead name */
                __( 'New lead: %s', 'nz-lead-capture' ),
                $data['name']
            );
            $body = sprintf(
                "Name: %s\nEmail: %s\nPhone: %s\nService: %s\n\n%s\n",
                $data['name'], $data['email'], $data['phone'], $data['service'], $data['message']
            );
            wp_mail( $notify, $subject, $body );
        }
        return $lead_id;
    }

    public static function ajax_submit() {
        check_ajax_referer( 'nzlc_submit', 'nonce' );
        list( $data, $errors ) = self::validate( $_POST );
        if ( $errors ) {
            wp_send_json_error( array( 'errors' => $errors ), 422 );
        }
        $lead_id = self::store_and_notify( $data );
        if ( ! $lead_id ) {
            wp_send_json_error( array( 'errors' => array( __( 'Something went wrong. Please try again.', 'nz-lead-capture' ) ) ), 500 );
        }
        wp_send_json_success( array( 'lead_id' => $lead_id, 'message' => get_option( 'nzlc_success_message' ) ) );
    }

    /**
     * Non-JS fallback: a plain POST to the same page, verified by nonce.
     * On success the visitor sees the success message instead of the form.
     */
    public static function handle_plain_post() {
        if ( empty( $_POST['nzlc_submitted'] ) || ! is_singular() ) {
            return;
        }
        if ( ! isset( $_POST['nzlc_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['nzlc_nonce'] ) ), 'nzlc_submit' ) ) {
            return;
        }
        list( $data, $errors ) = self::validate( $_POST );
        if ( $errors ) {
            return; // Form re-renders; the user can correct and resubmit.
        }
        self::store_and_notify( $data );
        add_filter(
            'the_content',
            function ( $content ) {
                return '<div class="nzlc-success" role="status">' . esc_html( get_option( 'nzlc_success_message' ) ) . '</div>' . $content;
            }
        );
    }
}
