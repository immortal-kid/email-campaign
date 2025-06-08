<?php
namespace EC;

defined( 'ABSPATH' ) || exit;

class Reports {
    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'menu' ] );
    }

    public static function menu() {
        add_submenu_page(
            null, // hidden in menu
            'Campaign Report',
            'Campaign Report',
            'manage_options',
            'ec_campaign_report',
            [ __CLASS__, 'render' ]
        );
    }

    public static function render() {
        $post_id = absint( $_GET['post_id'] ?? 0 );
        if ( ! $post_id ) {
            wp_die( 'Invalid campaign' );
        }
        global $wpdb;
        $table = $wpdb->prefix . 'email_campaigns_subscribers';
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT email,status FROM $table WHERE campaign_id=%d ORDER BY id ASC",
            $post_id
        ), ARRAY_A );
        echo '<div class="wrap"><h1>Campaign Report</h1><table class="widefat"><thead><tr><th>Email</th><th>Status</th></tr></thead><tbody>';
        foreach ( $rows as $r ) {
            printf( '<tr><td>%s</td><td>%s</td></tr>', esc_html( $r['email'] ), esc_html( $r['status'] ) );
        }
        echo '</tbody></table></div>';
    }
}
