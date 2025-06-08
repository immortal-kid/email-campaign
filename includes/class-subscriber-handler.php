<?php
namespace EC;

defined( 'ABSPATH' ) || exit;

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Handles importing subscribers from CSV/XLSX
 * and keeps the centralized contacts table in sync.
 */
class Subscriber_Handler {

	/**
	 * Wire up the AJAX endpoint.
	 */
	public static function init() {
		add_action( 'wp_ajax_ec_upload_contacts', [ __CLASS__, 'ajax_upload' ] );
	}

	/**
	 * AJAX handler for the “Upload & Import” button.
	 * Returns {valid, invalid, duplicates} in JSON.
	 */
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

		wp_send_json_success(
			[
				'valid'      => $valid,
				'invalid'    => $invalid,
				'duplicates' => $duplicates,
			]
		);
	}

	/**
	 * Import a CSV/XLSX file.
	 *
	 * @param int   $campaign_id The email_campaign post-ID.
	 * @param array $file        $_FILES entry.
	 * @param bool  $silent      When true, returns counts instead of adding redirect notice.
	 *
	 * @return array [valid, invalid, duplicates]
	 */
	public function import_file( $campaign_id, $file, $silent = false ) : array {

		$tmp = $file['tmp_name'];
		$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

		// ── Load spreadsheet (CSV or Excel) ───────────────────────────────
		try {
			if ( $ext === 'csv' ) {
				$reader = IOFactory::createReader( 'Csv' );
				$reader->setDelimiter( ',' );
				$reader->setEnclosure( '"' );
				$reader->setSheetIndex( 0 );
				$spreadsheet = $reader->load( $tmp );
			} else {
				$spreadsheet = IOFactory::load( $tmp );
			}
		} catch ( \Throwable $e ) {
			wp_die( 'Failed to read spreadsheet: ' . $e->getMessage() );
		}

		// ── Read all rows to array ────────────────────────────────────────
		$rows   = $spreadsheet->getActiveSheet()->toArray( null, true, true, true );
		$header = array_map( 'strtolower', $rows[1] ); // first row assumed header

		// Locate “email/mail” column, and optional “name” column
		$emailCol = array_search( 'email', $header, true );
		if ( $emailCol === false ) {
			$emailCol = array_search( 'mail', $header, true );
		}
		if ( $emailCol === false ) {
			wp_die( 'No “email” column found. Make sure the first row contains a header called “email” or “mail”.' );
		}
		$nameCol = array_search( 'name', $header, true );

		global $wpdb;
		$contacts_table = $wpdb->prefix . 'email_contacts';
		$subs_table     = $wpdb->prefix . 'email_campaigns_subscribers';

		$valid = $invalid = $duplicates = 0;

		foreach ( $rows as $index => $row ) {
			if ( $index === 1 ) {
				continue; // skip header row
			}

			$email = trim( $row[ $emailCol ] ?? '' );
			$name  = $nameCol !== false ? trim( $row[ $nameCol ] ?? '' ) : '';

			if ( ! is_email( $email ) ) {
				$invalid++;
				continue;
			}

			// Skip duplicates within the same campaign
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM $subs_table WHERE campaign_id=%d AND email=%s LIMIT 1",
					$campaign_id,
					$email
				)
			);
			if ( $exists ) {
				$duplicates++;
				continue;
			}

			// Insert / update centralized contact
			$wpdb->query(
				$wpdb->prepare(
					"INSERT INTO $contacts_table (email, name, created_at)
					 VALUES (%s, %s, NOW())
					 ON DUPLICATE KEY UPDATE name = IF(name='', VALUES(name), name)",
					$email,
					$name
				)
			);

			// Subscriber row for this campaign
			$wpdb->insert(
				$subs_table,
				[
					'campaign_id' => $campaign_id,
					'email'       => $email,
					'name'        => $name,
					'status'      => 'Pending',
					'created_at'  => current_time( 'mysql' ),
				],
				[ '%d', '%s', '%s', '%s', '%s' ]
			);

			$valid++;
		}

		// If not silent (called during normal post save) add redirect notice
		if ( ! $silent ) {
			add_filter(
				'redirect_post_location',
				function ( $location ) use ( $valid, $invalid, $duplicates ) {
					return add_query_arg(
						[
							'ec_imported' => $valid,
							'ec_invalid'  => $invalid,
							'ec_dupes'    => $duplicates,
						],
						$location
					);
				}
			);
		}

		return [ $valid, $invalid, $duplicates ];
	}
}

// Make sure the hooks are active.
Subscriber_Handler::init();
