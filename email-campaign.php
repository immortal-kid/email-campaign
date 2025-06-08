<?php
/**
 * Plugin Name:       Email Campaign Pro
 * Plugin URI:        https://your-website.com/email-campaign-pro
 * Description:       A custom WordPress plugin for sending transactional emails with precise scheduling and detailed reporting.
 * Version:           1.0.0
 * Author:            Your Name/Company
 * Author URI:        https://your-website.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       email-campaign
 * Domain Path:       /languages
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants.
define( 'EC_PRO_VERSION', '1.0.0' );
define( 'EC_PRO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EC_PRO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'EC_PRO_ASSETS_URL', EC_PRO_PLUGIN_URL . 'assets/' );

// Load Composer autoloader.
if ( file_exists( EC_PRO_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
    require_once EC_PRO_PLUGIN_DIR . 'vendor/autoload.php';
} else {
    // Fallback or warning if Composer dependencies are not installed.
    add_action( 'admin_notices', function() {
        echo '<div class="notice notice-error"><p>Email Campaign Pro requires Composer dependencies. Please run <code>composer install</code> in the plugin directory.</p></div>';
    });
    return; // Prevent further execution if dependencies are missing.
}

use EmailCampaign\CPT_Email_Campaign;
use EmailCampaign\Subscriber_Handler;
use EmailCampaign\Contact_Manager;
use EmailCampaign\Scheduler;
use EmailCampaign\Tracking;
use EmailCampaign\Reports;

/**
 * The core plugin class.
 */
class Email_Campaign_Pro {

    /**
     * Constructor.
     */
    public function __construct() {
        $this->define_hooks();
    }

    /**
     * Define the core functionality of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_hooks() {
        add_action( 'plugins_loaded', array( $this, 'load_dependencies' ) );
        add_action( 'plugins_loaded', array( $this, 'init_classes' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
    }

    /**
     * Load required dependencies.
     *
     * @since    1.0.0
     * @access   public
     */
    public function load_dependencies() {
        require_once EC_PRO_PLUGIN_DIR . 'includes/helper-functions.php'; // Load helper functions first.
    }

    /**
     * Initialize all core classes.
     *
     * @since    1.0.0
     * @access   public
     */
    public function init_classes() {
        new CPT_Email_Campaign();
        new Subscriber_Handler();
        new Contact_Manager();
        new Scheduler();
        new Tracking();
        new Reports();
    }

    /**
     * Enqueue admin scripts and styles.
     *
     * @since    1.0.0
     */
    public function enqueue_admin_scripts() {
        wp_enqueue_style( 'ec-pro-admin-styles', EC_PRO_ASSETS_URL . 'css/admin.css', array(), EC_PRO_VERSION, 'all' );

        // Enqueue on specific pages for efficiency.
        $screen = get_current_screen();
        if ( $screen && ( $screen->post_type === 'email_campaign' || $screen->id === 'toplevel_page_email_campaign_contacts' || $screen->id === 'email_campaign_page_email_campaign_report' ) ) {
            wp_enqueue_script( 'ec-pro-admin-js', EC_PRO_ASSETS_URL . 'js/admin.js', array( 'jquery' ), EC_PRO_VERSION, true );
            wp_localize_script( 'ec-pro-admin-js', 'ecProAdminVars', array(
                'confirm_publish' => esc_html__( 'Are you sure you want to publish this email campaign and start sending emails?', 'email-campaign' ),
                'nonce'           => wp_create_nonce( 'ec_pro_admin_nonce' ),
                'ajax_url'        => admin_url( 'admin-ajax.php' )
            ) );
        }
    }

    /**
     * Register REST API routes.
     *
     * @since    1.0.0
     */
    public function register_rest_routes() {
        // Routes are registered within the Tracking class.
        // The Tracking class handles its own route registration.
    }

    /**
     * Activate the plugin.
     */
    public static function activate() {
        // Create custom database tables.
        Subscriber_Handler::create_subscriber_table();
        Contact_Manager::create_contact_table();
        Tracking::create_log_table();

        // Flush rewrite rules to ensure CPT permalinks work.
        flush_rewrite_rules();
    }

    /**
     * Deactivate the plugin.
     */
    public static function deactivate() {
        // Clear scheduled actions on deactivation if any.
        // This is a safety measure; typically, you might want to finish campaigns.
        // For a more robust solution, you might offer options to pause or complete.
        if ( class_exists( 'ActionScheduler_Store' ) ) {
             $store = ActionScheduler_Store::instance();
             $actions = $store->query_actions( array(
                 'hook' => 'ec_pro_send_single_email_action',
                 'status' => array( 'pending', 'in-progress' )
             ) );
             foreach ( $actions as $action_id ) {
                 $store->cancel_action( $action_id );
             }
        }

        // Flush rewrite rules.
        flush_rewrite_rules();
    }

} // End of Email_Campaign_Pro class.

/**
 * Begins execution of the plugin.
 */
function run_email_campaign_pro() {
    new Email_Campaign_Pro();
}
run_email_campaign_pro();

// Register activation and deactivation hooks.
register_activation_hook( __FILE__, array( 'Email_Campaign_Pro', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Email_Campaign_Pro', 'deactivate' ) );

// Include the uninstall script.
if ( file_exists( EC_PRO_PLUGIN_DIR . 'uninstall.php' ) ) {
    register_uninstall_hook( __FILE__, 'ec_pro_uninstall_plugin' );
}

/**
 * Uninstall hook callback.
 * Separated into a function to be accessible by register_uninstall_hook.
 */
function ec_pro_uninstall_plugin() {
    // This function will be called on plugin uninstall.
    // The actual uninstall logic is in uninstall.php.
    require_once EC_PRO_PLUGIN_DIR . 'uninstall.php';
    ec_pro_perform_uninstall();
}