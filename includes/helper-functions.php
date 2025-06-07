<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
} 
/**
 * Handles creation of custom DB tables on activation.
 */
class EC_Helper_Functions {
    public static function create_database_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $subscribers = "CREATE TABLE {$wpdb->prefix}email_campaigns_subscribers (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id BIGINT UNSIGNED NOT NULL,
            email VARCHAR(255) NOT NULL,
            name VARCHAR(200) DEFAULT '',
            status VARCHAR(20) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY(id),
            KEY campaign_id (campaign_id),
            KEY email (email)
        ) $charset;";
        $contacts = "CREATE TABLE {$wpdb->prefix}email_contacts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            email VARCHAR(255) NOT NULL UNIQUE,
            name VARCHAR(200) DEFAULT '',
            status VARCHAR(20) NOT NULL,
            created_at DATETIME NOT NULL,
            last_campaign_id BIGINT UNSIGNED DEFAULT NULL,
            PRIMARY KEY(id),
            KEY status (status)
        ) $charset;";
        $logs = "CREATE TABLE {$wpdb->prefix}email_campaigns_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id BIGINT UNSIGNED NOT NULL,
            email VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL,
            sent_at DATETIME DEFAULT NULL,
            opened_at DATETIME DEFAULT NULL,
            bounced_at DATETIME DEFAULT NULL,
            PRIMARY KEY(id),
            KEY campaign_id (campaign_id),
            KEY email (email)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $subscribers );
        dbDelta( $contacts );
        dbDelta( $logs );
    }
}

/**
 * Utility methods for token replacement and unsubscribe URL.
 */
class EC_Utils {
    public static function replace_tokens( $content, $email, $name ) {
        $subs_url = add_query_arg( ['email' => rawurlencode( $email )], home_url( '/unsubscribe' ) );
        $repl = [
            '{EMAIL}'      => esc_html( $email ),
            '{FIRST_NAME}' => esc_html( $name ),
            '{UNSUB_URL}'  => esc_url( $subs_url ),
        ];
        return str_replace( array_keys( $repl ), array_values( $repl ), $content );
    }
}

