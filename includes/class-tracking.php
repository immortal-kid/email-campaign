<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EC_Tracking {

    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_endpoints' ] );
    }

    public function register_endpoints() {
        register_rest_route( 'email-campaign/v1', '/track-open', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'handle_open' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( 'email-campaign/v1', '/webhook/bounce', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_bounce' ],
            'permission_callback' => '__return_true',
        ] );
    }

    public function handle_open( WP_REST_Request $request ) {
        $email_id = intval( $request->get_param( 'email_id' ) );
        if ( ! $email_id ) {
            return new WP_REST_Response( [ 'error' => 'Missing email_id' ], 400 );
        }
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'email_campaigns_logs',
            [ 'opened_at' => current_time( 'mysql', 1 ) ],
            [ 'id'        => $email_id ],
            [ '%s' ],
            [ '%d' ]
        );

        // Return a 1x1 transparent pixel
        header( 'Content-Type: image/gif' );
        echo base64_decode( 'R0lGODlhAQABAPAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==' );
        exit;
    }

    public function handle_bounce( WP_REST_Request $request ) {
        $body = $request->get_body();
        $data = json_decode( $body, true );
        if ( empty( $data ) || ! isset( $data['email'] ) ) {
            return new WP_REST_Response( [ 'error' => 'Invalid payload' ], 400 );
        }

        global $wpdb;
        // Mark contact bounced
        $wpdb->update(
            $wpdb->prefix . 'email_contacts',
            [ 'status' => 'bounced', 'last_campaign_id' => intval( $data['campaign_id'] ) ],
            [ 'email'  => sanitize_email( $data['email'] ) ],
            [ '%s', '%d' ],
            [ '%s' ]
        );

        // Also update log
        $wpdb->update(
            $wpdb->prefix . 'email_campaigns_logs',
            [ 'bounced_at' => current_time( 'mysql', 1 ), 'status' => 'bounced' ],
            [ 'email'      => sanitize_email( $data['email'] ) ],
            [ '%s', '%s' ],
            [ '%s' ]
        );

        return new WP_REST_Response( [ 'success' => true ], 200 );
    }
}

new EC_Tracking();
