<?php
/*
Plugin Name: Email Campaign
Description: Transactional email campaigns with scheduling and reporting.
Version: 1.0.013
Author: ChatGPT
License: GPLv2 or later
*/

defined( 'ABSPATH' ) || exit;

define( 'EC_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'EC_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'EC_PLUGIN_VERSION', '1.0.0' );

/**
 * Composer autoloader if present.
 * Users must run `composer install` in plugin root to get PhpSpreadsheet and Action Scheduler.
 */
if ( file_exists( EC_PLUGIN_PATH . 'vendor/autoload.php' ) ) {
    require_once EC_PLUGIN_PATH . 'vendor/autoload.php';
}else {
    add_action( 'admin_notices', function () {
        echo '<div class="notice notice-error"><p><strong>Email Campaign:</strong> Run <code>composer install</code> in the plugin folder to load PhpSpreadsheet & Action Scheduler.</p></div>';
    } );
}

// -----------------------------------------------------------------------------
// Activation / deactivation
// -----------------------------------------------------------------------------
register_activation_hook( __FILE__, 'ec_activate_plugin' );
register_deactivation_hook( __FILE__, 'ec_deactivate_plugin' );

function ec_activate_plugin() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();

    // Central contacts
    $contacts = "{$wpdb->prefix}email_contacts";
    // Subscribers per‑campaign
    $subs = "{$wpdb->prefix}email_campaigns_subscribers";
    // Log
    $logs = "{$wpdb->prefix}email_campaigns_logs";

    $sql = "
    CREATE TABLE $contacts (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(191) NOT NULL UNIQUE,
        name VARCHAR(191) NULL,
        status ENUM('Active','Unsubscribed','Bounced') DEFAULT 'Active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_campaign_id BIGINT UNSIGNED NULL
    ) $charset;

    CREATE TABLE $subs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        campaign_id BIGINT UNSIGNED NOT NULL,
        email VARCHAR(191) NOT NULL,
        name VARCHAR(191) NULL,
        status ENUM('Pending','Sent','Failed','Bounced','Opened') DEFAULT 'Pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY campaign (campaign_id),
        KEY status (status)
    ) $charset;

    CREATE TABLE $logs (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        campaign_id BIGINT UNSIGNED NOT NULL,
        email VARCHAR(191) NOT NULL,
        status ENUM('Sent','Failed','Bounced') DEFAULT 'Sent',
        sent_at DATETIME NULL,
        opened_at DATETIME NULL,
        bounced_at DATETIME NULL,
        KEY campaign (campaign_id)
    ) $charset;
    ";

    dbDelta( $sql );
}

function ec_deactivate_plugin() {
    // Nothing to clean on deactivate; keep data safe.
}

// -----------------------------------------------------------------------------
// Autoload internal classes
// -----------------------------------------------------------------------------
foreach ( glob( EC_PLUGIN_PATH . 'includes/*.php' ) as $file ) {
    require_once $file;
}

// Initialise subsystems once plugins loaded
add_action( 'plugins_loaded', function () {
    EC\Helper_Functions::init();
    EC\CPT_Email_Campaign::init();
    EC\Subscriber_Handler::init();
    EC\Contact_Manager::init();
    EC\Scheduler::init();
    EC\Tracking::init();
    EC\Reports::init();
} );
