<?php
namespace EC;

defined( 'ABSPATH' ) || exit;

use PhpOffice\PhpSpreadsheet\IOFactory;

class Subscriber_Handler {
    
 public static function init() {
   add_action( 'wp_ajax_ec_upload_contacts', [ 'EC\\Subscriber_Handler', 'ajax_upload' ] );
    }

 
public static function ajax_upload() {
    check_ajax_referer( 'ec_upload', 'security' );

    $post_id = absint( $_POST['post_id'] ?? 0 );
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        wp_send_json_error( 'No permission', 403 );
    }

    if ( empty( $_FILES['contacts_file']['tmp_name'] ) ) {
        wp_send_json_error( 'No file', 400 );
    }

    $handler = new self();
    [$valid, $invalid, $duplicates] = $handler->import_file( $post_id, $_FILES['contacts_file'], true );

    wp_send_json_success( [
        'valid'      => $valid,
        'invalid'    => $invalid,
        'duplicates' => $duplicates,
    ] );
}
    public function import_file( $campaign_id, $file ) {
        if ( ! current_user_can( 'edit_post', $campaign_id ) ) {
            return;
        }

        $tmp  = $file['tmp_name'];
        $ext  = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        $uploads = wp_upload_dir();
        $dest    = trailingslashit( $uploads['basedir'] ) . 'email-campaign/';
        wp_mkdir_p( $dest );                          // create dir if missing
        $stored   = $dest . basename( $file['name'] );
        copy( $tmp, $stored );                        // keeps a permanent copy

        try {
            if ( $ext === 'csv' ) {
                // Explicit CSV reader so PhpSpreadsheet doesn’t mis-detect delimiters
                $reader = IOFactory::createReader( 'Csv' );
                $reader->setDelimiter( ',' );
                $reader->setEnclosure( '"' );
                $reader->setSheetIndex( 0 );   // first sheet
                $spreadsheet = $reader->load( $tmp );
            } else {
                // xls / xlsx / ods handled automatically
                $spreadsheet = IOFactory::load( $tmp );
            }
        } catch ( \Throwable $e ) {
            wp_die( 'Failed to read spreadsheet: ' . $e->getMessage() );
        }

        $sheet  = $spreadsheet->getActiveSheet();
$rows   = $sheet->toArray(null, true, true, true);

/* ── NEW: find which column contains the word "mail" or "email" ── */
$header = array_map('strtolower', $rows[1]);   // first row
$emailCol = array_search('mail', $header);
if ($emailCol === false) {
    $emailCol = array_search('email', $header);
}
$nameCol  = array_search('name',  $header);

/* Replace letters A/B with dynamic keys */
foreach ( $rows as $index => $row ) {
    if ( $index === 1 ) { continue; }          // skip header
    $email = trim( $row[ $emailCol ] ?? '' );
    $name  = $nameCol !== false ? trim( $row[ $nameCol ] ?? '' ) : '';
 
}

        global $wpdb;
        $contacts_table = $wpdb->prefix . 'email_contacts';
        $subs_table = $wpdb->prefix . 'email_campaigns_subscribers';

        $valid = 0; $invalid = 0; $duplicates = 0;

        foreach ( $rows as $row ) {
            $email = trim( $row['A'] ?? '' );
            $name  = trim( $row['B'] ?? '' );

            if ( ! is_email( $email ) ) {
                $invalid++; continue;
            }

            // Check duplicate in campaign
            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM $subs_table WHERE campaign_id=%d AND email=%s LIMIT 1",
                $campaign_id, $email
            ) );
            if ( $exists ) {
                $duplicates++; continue;
            }

            // Insert contact if not exists
            $wpdb->query( $wpdb->prepare(
                "INSERT IGNORE INTO $contacts_table (email,name,created_at) VALUES (%s,%s,now())",
                $email, $name
            ) );

            // Subscriber row
            $wpdb->insert( $subs_table, [
                'campaign_id' => $campaign_id,
                'email'       => $email,
                'name'        => $name,
                'status'      => 'Pending',
                'created_at'  => current_time( 'mysql' ),
            ], [ '%d','%s','%s','%s','%s' ] );

            $valid++;
        }

        // feedback
        add_filter( 'redirect_post_location', function( $location ) use ( $valid, $invalid, $duplicates ) {
            return add_query_arg( [
                'ec_imported' => $valid,
                'ec_invalid'  => $invalid,
                'ec_dupes'    => $duplicates,
            ], $location );
        } );
    }
        return [ $valid, $invalid, $duplicates ];

}
