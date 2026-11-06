<?php
/**
 * REST API: read leads (admin only) and submit a lead (public, nonce-checked).
 * The public route reuses the same validate() + store_and_notify() as the form.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class NZLC_REST {

    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
    }

    public static function routes() {
        register_rest_route(
            'nzlc/v1',
            '/leads',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( __CLASS__, 'get_leads' ),
                    'permission_callback' => function () {
                        return current_user_can( 'manage_options' );
                    },
                    'args'                => array(
                        'status' => array( 'sanitize_callback' => 'sanitize_key' ),
                        'page'   => array( 'sanitize_callback' => 'absint' ),
                    ),
                ),
                array(
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => array( __CLASS__, 'create_lead' ),
                    'permission_callback' => '__return_true',
                    'args'                => array(
                        'nonce' => array( 'required' => true, 'sanitize_callback' => 'sanitize_key' ),
                    ),
                ),
            )
        );
    }

    public static function get_leads( WP_REST_Request $request ) {
        $data = NZLC_Storage::list( $request->get_param( 'status' ), $request->get_param( 'page' ) ?: 1, 20 );
        return rest_ensure_response( $data );
    }

    public static function create_lead( WP_REST_Request $request ) {
        if ( ! wp_verify_nonce( $request->get_param( 'nonce' ), 'nzlc_submit' ) ) {
            return new WP_Error( 'nzlc_bad_nonce', __( 'Invalid security token.', 'nz-lead-capture' ), array( 'status' => 403 ) );
        }
        list( $data, $errors ) = NZLC_Form::validate( $request->get_params() );
        if ( $errors ) {
            return new WP_Error( 'nzlc_invalid', implode( ' ', $errors ), array( 'status' => 422 ) );
        }
        $lead_id = NZLC_Form::store_and_notify( $data );
        if ( ! $lead_id ) {
            return new WP_Error( 'nzlc_failed', __( 'Could not save the lead.', 'nz-lead-capture' ), array( 'status' => 500 ) );
        }
        return rest_ensure_response( array( 'lead_id' => $lead_id, 'message' => get_option( 'nzlc_success_message' ) ) );
    }
}
