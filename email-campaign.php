 
<?php
/**
 * Plugin Name: Email Campaign
 * Description: Custom transactional email campaign plugin with precise scheduling and detailed reporting.
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: email-campaign
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'EC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Autoloader for PhpSpreadsheet and dependencies
require_once EC_PLUGIN_DIR . 'vendor/autoload.php';

// Include core classes
require_once EC_PLUGIN_DIR . 'includes/class-cpt-email-campaign.php';
require_once EC_PLUGIN_DIR . 'includes/class-subscriber-handler.php';
require_once EC_PLUGIN_DIR . 'includes/class-contact-manager.php';
require_once EC_PLUGIN_DIR . 'includes/class-scheduler.php';
require_once EC_PLUGIN_DIR . 'includes/class-tracking.php';
require_once EC_PLUGIN_DIR . 'includes/class-reports.php';

// Include helper functions (defines EC_Helper_Functions and EC_Utils)
require_once EC_PLUGIN_DIR . 'includes/helper-functions.php';

// Instantiate and hook core handlers
new EC_CPT_Email_Campaign();
new EC_Subscriber_Handler();
new EC_Contact_Manager();
new EC_Scheduler();
new EC_Tracking();
new EC_Reports();


// Activation: create tables via helper class

register_activation_hook( __FILE__, [ 'EC_Helper_Functions', 'create_database_tables' ] );
