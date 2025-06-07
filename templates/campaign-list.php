<?php
// File: templates/campaign-list.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Add our custom columns to the Email Campaign CPT list.
 */
add_filter( 'manage_email_campaign_posts_columns', function( $columns ) {
    $columns['ec_status'] = __( 'Status', 'email-campaign' );
    $columns['ec_report'] = __( 'Report', 'email-campaign' );
    return $columns;
} );

/**
 * Render content for our custom columns.
 */
add_action( 'manage_email_campaign_posts_custom_column', function( $column, $post_id ) {
    global $wpdb;
    $subs_table = $wpdb->prefix . 'email_campaigns_subscribers';
    $sent_count = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$subs_table} WHERE campaign_id = %d AND status = %s",
        $post_id, 'sent'
    ) );
    $total_count = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$subs_table} WHERE campaign_id = %d",
        $post_id
    ) );

    if ( $column === 'ec_status' ) {
        printf(
            '<span class="ec-status-in-progress">%d/%d sent</span>',
            intval( $sent_count ),
            intval( $total_count )
        );
    }

    if ( $column === 'ec_report' ) {
        $url = admin_url( 'admin.php?page=email_campaign_report&post_id=' . $post_id );
        printf( '<a href="%s">%s</a>', esc_url( $url ), esc_html__( 'View Report', 'email-campaign' ) );
    }
}, 10, 2 );
