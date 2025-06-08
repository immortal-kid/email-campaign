<?php
namespace EC;

defined( 'ABSPATH' ) || exit;

class Tracking {
    public static function init() {
        add_action( 'init', [ __CLASS__, 'init_routes' ] );
    }

    public static function init_routes() {
        register_rest_route( 'ec/v1', '/open', [
            'methods'  => 'GET',
            'callback' => [ __CLASS__, 'track_open' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( 'ec/v1', '/bounce', [
            'methods'  => 'POST',
            'callback' => [ __CLASS__, 'track_bounce' ],
            'permission_callback' => '__return_true',
        ] );
    }

    public static function track_open( $req ) {
        global $wpdb;
        $sid = (int) $req->get_param( 'sid' );
        $wpdb->update(
            $wpdb->prefix . 'email_campaigns_subscribers',
            [ 'status' => 'Opened' ],
            [ 'id' => $sid ],
            [ '%s' ],
            [ '%d' ]
        );
        // Return tiny transparent gif
        header( "Content-Type: image/gif" );
        echo base64_decode( "R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" );
        exit;
    }

    public static function track_bounce( $req ) {
        $data = $req->get_json_params();
        if ( empty( $data ) ) {
            return rest_ensure_response( [ 'message' => 'No data' ] );
        }
        global $wpdb;
        $subs_table = $wpdb->prefix . 'email_campaigns_subscribers';
        foreach ( $data as $event ) {
            if ( ( $event['event'] ?? '' ) === 'bounce' ) {
                $email = sanitize_email( $event['email'] );
                $wpdb->update( $subs_table, [ 'status' => 'Bounced' ], [ 'email' => $email ], [ '%s' ], [ '%s' ] );
            }
        }
        return rest_ensure_response( [ 'message' => 'ok' ] );
    }
}
