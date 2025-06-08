<?php
/**
 * Handles parsing uploaded Excel/CSV files and storing subscribers.
 *
 * @package Email_Campaign_Pro
 * @subpackage Includes
 */

namespace EmailCampaign;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Subscriber_Handler {

    /**
     * Create the custom database table for campaign subscribers.
     */
    public static function create_subscriber_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'email_campaigns_subscribers';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) UNSIGNED NOT NULL,
            email varchar(255) NOT NULL,
            name varchar(255) DEFAULT '',
            status varchar(50) DEFAULT 'pending' NOT NULL, -- pending, sent, failed, unsubscribed, bounced
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY campaign_id (campaign_id),
            KEY email (email),
            KEY status (status)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    /**
     * Parses an Excel/CSV file and stores valid subscribers.
     *
     * @param int    $campaign_id      The ID of the email campaign post.
     * @param string $file_path        The full path to the uploaded file.
     * @return array|WP_Error An array with upload statistics or WP_Error on failure.
     */
    public function parse_and_store_subscribers( $campaign_id, $file_path ) {
        global $wpdb;
        $table_subscribers = $wpdb->prefix . 'email_campaigns_subscribers';

        $valid_emails_count = 0;
        $invalid_emails_count = 0;
        $duplicates_skipped_count = 0;
        $processed_emails = array(); // To track duplicates within the current file.

        // Clear existing subscribers for this campaign before importing new ones.
        $wpdb->delete( $table_subscribers, array( 'campaign_id' => $campaign_id ) );

        try {
            $spreadsheet = IOFactory::load( $file_path );
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = $sheet->getHighestRow();

            // Prepare data for bulk insert.
            $insert_data = array();
            $contact_manager = new Contact_Manager(); // To interact with central contacts table.

            for ( $row = 1; $row <= $highestRow; $row++ ) {
                $email = ec_pro_sanitize_email( $sheet->getCell( 'A' . $row )->getValue() );
                $first_name = ec_pro_sanitize_text( $sheet->getCell( 'B' . $row )->getValue() );

                if ( ! is_email( $email ) ) {
                    $invalid_emails_count++;
                    continue;
                }

                if ( in_array( $email, $processed_emails ) ) {
                    $duplicates_skipped_count++;
                    continue;
                }

                // Check against central contacts for unsubscribed/bounced
                $contact_status = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$wpdb->prefix}email_contacts WHERE email = %s", $email ) );
                if ( in_array( $contact_status, array( 'unsubscribed', 'bounced' ) ) ) {
                    $duplicates_skipped_count++; // Treat as skipped for this campaign
                    continue;
                }

                $processed_emails[] = $email; // Mark as processed for this file.

                $insert_data[] = $wpdb->prepare(
                    "(%d, %s, %s, %s, %s)",
                    $campaign_id,
                    $email,
                    $first_name,
                    'pending', // Initial status for campaign subscriber.
                    current_time( 'mysql' )
                );
                $valid_emails_count++;

                // Import/update in central contacts table.
                $contact_manager->add_or_update_contact( $email, $first_name, $campaign_id );
            }

            // Perform bulk insert if data exists.
            if ( ! empty( $insert_data ) ) {
                $values = implode( ',', $insert_data );
                $sql = "INSERT INTO {$table_subscribers} (campaign_id, email, name, status, created_at) VALUES $values";
                $wpdb->query( $sql );
            }

        } catch ( Exception $e ) {
            error_log( 'Email Campaign Pro: PhpSpreadsheet error - ' . $e->getMessage() );
            return new \WP_Error( 'file_parse_error', __( 'Error parsing the uploaded file.', 'email-campaign' ) );
        } catch ( \Exception $e ) {
            error_log( 'Email Campaign Pro: General file processing error - ' . $e->getMessage() );
            return new \WP_Error( 'file_process_error', __( 'An unexpected error occurred during file processing.', 'email-campaign' ) );
        }

        return array(
            'valid'      => $valid_emails_count,
            'invalid'    => $invalid_emails_count,
            'duplicates' => $duplicates_skipped_count,
        );
    }
}