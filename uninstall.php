<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Email_Campaign_Pro
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Performs the uninstall operations.
 *
 * @since 1.0.0
 */
function ec_pro_perform_uninstall() {
    global $wpdb;

    // Define table names.
    $table_subscribers = $wpdb->prefix . 'email_campaigns_subscribers';
    $table_contacts    = $wpdb->prefix . 'email_contacts';
    $table_logs        = $wpdb->prefix . 'email_campaigns_logs';

    // Drop custom tables.
    $wpdb->query( "DROP TABLE IF EXISTS {$table_subscribers}" );
    $wpdb->query( "DROP TABLE IF EXISTS {$table_contacts}" );
    $wpdb->query( "DROP TABLE IF EXISTS {$table_logs}" );

    // Delete custom post type posts.
    $posts = get_posts( array(
        'post_type'   => 'email_campaign',
        'numberposts' => -1,
        'post_status' => 'any',
        'fields'      => 'ids',
    ) );

    if ( $posts ) {
        foreach ( $posts as $post_id ) {
            wp_delete_post( $post_id, true ); // true to bypass trash and force delete.
        }
    }
 

    // Clear any scheduled actions (optional, but good for clean uninstall).
    if ( class_exists( 'ActionScheduler_Store' ) ) {
        $store = ActionScheduler_Store::instance();
        $actions = $store->query_actions( array(
            'hook' => 'ec_pro_send_single_email_action',
            'status' => array( 'pending', 'in-progress', 'failed' ) // Include failed actions too
        ) );
        foreach ( $actions as $action_id ) {
            $store->cancel_action( $action_id );
        }
    }

    // Flush rewrite rules.
    flush_rewrite_rules();
}