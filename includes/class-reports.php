<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EC_Reports {

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_report_page' ] );
    }

    public function add_report_page() {
        add_submenu_page(
            null,
            __( 'Campaign Report', 'email-campaign' ),
            __( 'Report', 'email-campaign' ),
            'manage_options',
            'email_campaign_report',
            [ $this, 'render_report_page' ]
        );
    }

    public function render_report_page() {
        if ( ! isset( $_GET['post_id'] ) ) {
            wp_die( __( 'Missing campaign ID', 'email-campaign' ) );
        }
        $campaign_id = intval( $_GET['post_id'] );
        global $wpdb;
        $logs = $wpdb->get_results( $wpdb->prepare(
            "SELECT email, status, sent_at, opened_at, bounced_at 
             FROM {$wpdb->prefix}email_campaigns_logs
             WHERE campaign_id = %d",
            $campaign_id
        ) );
        echo '<div class="wrap"><h1>' . esc_html__( 'Campaign Report', 'email-campaign' ) . '</h1>';
        echo '<table class="widefat fixed striped"><thead><tr>';
        foreach ( [ 'Email', 'Delivery', 'Opened', 'Bounced', 'Sent At', 'Opened At', 'Bounced At' ] as $col ) {
            echo '<th>' . esc_html__( $col, 'email-campaign' ) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ( $logs as $row ) {
            echo '<tr>';
            printf( '<td>%s</td>', esc_html( $row->email ) );
            printf( '<td>%s</td>', esc_html( ucfirst( $row->status ) ) );
            printf( '<td>%s</td>', esc_html( $row->opened_at ? 'Yes' : 'No' ) );
            printf( '<td>%s</td>', esc_html( $row->bounced_at ? 'Yes' : 'No' ) );
            printf( '<td>%s</td>', esc_html( $row->sent_at ) );
            printf( '<td>%s</td>', esc_html( $row->opened_at ) );
            printf( '<td>%s</td>', esc_html( $row->bounced_at ) );
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
}

new EC_Reports();
