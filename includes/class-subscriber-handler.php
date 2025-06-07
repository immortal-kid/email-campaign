<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use PhpOffice\PhpSpreadsheet\IOFactory;

class EC_Subscriber_Handler {

    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'email_campaigns_subscribers';

        add_action( 'save_post_email_campaign', [ $this, 'handle_file_parse' ], 20, 3 );
    }

    public function handle_file_parse( $post_id, $post, $update ) {
        // Only on publish
        if ( $post->post_status !== 'publish' || $update === false ) {
            return;
        }

        $upload_id = get_post_meta( $post_id, '_ec_upload_id', true );
        if ( ! $upload_id ) {
            return;
        }

        $file_path = get_attached_file( $upload_id );
        if ( ! file_exists( $file_path ) ) {
            return;
        }

        // Load spreadsheet
        try {
            $spreadsheet = IOFactory::load( $file_path );
        } catch ( \Exception $e ) {
            error_log( 'EC Spreadsheet load error: ' . $e->getMessage() );
            return;
        }

        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();

        global $wpdb;
        $valid   = 0;
        $invalid = 0;
        $dup     = 0;
        $seen    = [];

        foreach ( $rows as $i => $row ) {
            if ( $i === 0 ) {
                // assume header row; skip if contains "Email"
                if ( stripos( $row[0], 'email' ) !== false ) {
                    continue;
                }
            }
            $email = sanitize_email( $row[0] );
            $name  = isset( $row[1] ) ? sanitize_text_field( $row[1] ) : '';

            if ( ! is_email( $email ) ) {
                $invalid++;
                continue;
            }
            if ( in_array( $email, $seen, true ) ) {
                $dup++;
                continue;
            }

            $seen[] = $email;
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE campaign_id = %d AND email = %s",
                $post_id, $email
            ) );
            if ( $exists ) {
                $dup++;
                continue;
            }

            $inserted = $wpdb->insert(
                $this->table,
                [
                    'campaign_id' => $post_id,
                    'email'       => $email,
                    'name'        => $name,
                    'status'      => 'pending',
                    'created_at'  => current_time( 'mysql', 1 ),
                ],
                [ '%d', '%s', '%s', '%s', '%s' ]
            );
            if ( $inserted ) {
                $valid++;
            }
        }

        // Delete file to save space
        wp_delete_attachment( $upload_id, true );

        // Store counts in post meta for feedback
        update_post_meta( $post_id, '_ec_import_stats', compact( 'valid', 'invalid', 'dup' ) );
    }
}

new EC_Subscriber_Handler();
