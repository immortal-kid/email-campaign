<?php
// File: includes/class-subscriber-handler.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

use PhpOffice\PhpSpreadsheet\IOFactory;

class EC_Subscriber_Handler {

    private $table;
    private $contacts_table;

    public function __construct() {
        global $wpdb;
        $this->table          = $wpdb->prefix . 'email_campaigns_subscribers';
        $this->contacts_table = $wpdb->prefix . 'email_contacts';

        // When a campaign is published, parse its upload
        add_action( 'publish_email_campaign', [ $this, 'parse_uploaded_list' ], 20, 2 );
    }

    /**
     * Parse the uploaded CSV/XLSX and insert subscribers + contacts.
     */
    public function parse_uploaded_list( $post_id, $post ) {
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

        $rows = $spreadsheet->getActiveSheet()->toArray( null, true, true, true );
        if ( empty( $rows ) ) {
            return;
        }

        // Determine header columns (case‐insensitive)
        $header = array_change_key_case( $rows[1], CASE_LOWER );
        $email_col   = array_search( 'email',   $header );
        $name_col    = array_search( 'name',    $header );
        $company_col = array_search( 'company', $header );

        if ( $email_col === false ) {
            error_log( 'EC Subscriber Handler: No "email" column found.' );
            return;
        }

        global $wpdb;
        $valid   = 0;
        $invalid = 0;
        $dup     = 0;
        $seen    = [];

        foreach ( $rows as $row_index => $row ) {
            // Skip header row
            if ( $row_index === 1 ) {
                continue;
            }

            $email   = isset( $row[ $email_col ] )   ? sanitize_email(   $row[ $email_col ] )   : '';
            $name    = isset( $name_col )    && isset( $row[ $name_col ] )    ? sanitize_text_field( $row[ $name_col ] )    : '';
            $company = isset( $company_col ) && isset( $row[ $company_col ] ) ? sanitize_text_field( $row[ $company_col ] ) : '';

            if ( ! is_email( $email ) ) {
                $invalid++;
                continue;
            }
            if ( in_array( $email, $seen, true ) ) {
                $dup++;
                continue;
            }
            $seen[] = $email;

            // Insert into subscribers table
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
                    'company'     => $company,
                    'status'      => 'pending',
                    'created_at'  => current_time( 'mysql', 1 ),
                ],
                [ '%d', '%s', '%s', '%s', '%s', '%s' ]
            );
            if ( $inserted ) {
                $valid++;
            }

            // Upsert into contacts table
            $contact_exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$this->contacts_table} WHERE email = %s",
                $email
            ) );
            if ( $contact_exists ) {
                $wpdb->update(
                    $this->contacts_table,
                    [ 'name' => $name, 'company' => $company, 'last_campaign_id' => $post_id ],
                    [ 'id'   => $contact_exists ],
                    [ '%s', '%s', '%d' ],
                    [ '%d' ]
                );
            } else {
                $wpdb->insert(
                    $this->contacts_table,
                    [
                        'email'            => $email,
                        'name'             => $name,
                        'company'          => $company,
                        'status'           => 'active',
                        'created_at'       => current_time( 'mysql', 1 ),
                        'last_campaign_id' => $post_id,
                    ],
                    [ '%s', '%s', '%s', '%s', '%s', '%d' ]
                );
            }
        }

        // Clean up the uploaded file
        wp_delete_attachment( $upload_id, true );

        // Store import stats for admin feedback
        update_post_meta( $post_id, '_ec_import_stats', compact( 'valid', 'invalid', 'dup' ) );
    }
}
