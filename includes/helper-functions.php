<?php
/**
 * Helper functions for Email Campaign Pro.
 *
 * @package Email_Campaign_Pro
 * @subpackage Includes
 */

namespace EmailCampaign; // Use namespace to prevent conflicts.

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Sanitizes an email address.
 *
 * @param string $email The email address to sanitize.
 * @return string The sanitized email address.
 */
function ec_pro_sanitize_email( $email ) {
    return sanitize_email( $email );
}

/**
 * Sanitizes text input.
 *
 * @param string $text The text to sanitize.
 * @return string The sanitized text.
 */
function ec_pro_sanitize_text( $text ) {
    return sanitize_text_field( $text );
}

/**
 * Sanitizes content (allows HTML).
 *
 * @param string $content The content to sanitize.
 * @return string The sanitized content.
 */
function ec_pro_sanitize_content( $content ) {
    // WordPress's wp_kses_post() is good for post content.
    return wp_kses_post( $content );
}

/**
 * Replaces personalization tokens in content.
 *
 * @param string $content The content with tokens.
 * @param array  $data    An associative array of data (e.g., ['first_name' => 'John']).
 * @return string The content with tokens replaced.
 */
function ec_pro_replace_personalization_tokens( $content, $data ) {
    // Ensure data keys are lowercase for consistent matching.
    $data = array_change_key_case( $data, CASE_LOWER );

    $replacements = array();
    foreach ( $data as $key => $value ) {
        // Support {FIRST_NAME} and {first_name}
        $replacements['{' . strtoupper( $key ) . '}'] = $value;
        $replacements['{' . strtolower( $key ) . '}'] = $value;
    }

    return str_replace( array_keys( $replacements ), array_values( $replacements ), $content );
}

/**
 * Generates an unsubscribe URL.
 *
 * @param string $email The email address to unsubscribe.
 * @return string The unsubscribe URL.
 */
function ec_pro_get_unsubscribe_url( $email ) {
    $rest_base = rest_url( 'email-campaign/v1/unsubscribe/' );
    $encoded_email = urlencode( base64_encode( $email ) ); // Simple encoding to make it less readable.
    $nonce = wp_create_nonce( 'wp_rest' ); // REST API nonces are for authenticated users, but useful for basic protection.
                                         // For public endpoints, consider a more robust, non-user-specific token.
                                         // For production, consider a unique, hashed token stored in the DB for each unsubscribe link.
                                         // For this example, we'll use a simple email encoding.

    // A better way would be to store a unique unsubscribe token for each email sent.
    // For demonstration, we'll use a basic approach.
    $unsubscribe_url = add_query_arg(
        array(
            'email' => $encoded_email,
            // 'token' => 'YOUR_UNIQUE_UNSUB_TOKEN_HERE', // Recommended for production.
        ),
        $rest_base
    );
    return $unsubscribe_url;
}

/**
 * Retrieves the plugin's physical address for CAN-SPAM.
 *
 * @return string The physical address.
 */
function ec_pro_get_physical_address() {
    // You can store this in wp_options.
    // For this example, we'll use a placeholder.
    $address = get_option( 'ec_pro_physical_address', __( '123 Main Street, Anytown, CA 12345', 'email-campaign' ) );
    return esc_html( $address );
}

/**
 * Get the log status label.
 *
 * @param string $status
 * @return string
 */
function ec_pro_get_log_status_label( $status ) {
    switch ( $status ) {
        case 'pending':
            return esc_html__( 'Pending', 'email-campaign' );
        case 'sent':
            return esc_html__( 'Sent', 'email-campaign' );
        case 'failed':
            return esc_html__( 'Failed', 'email-campaign' );
        case 'opened':
            return esc_html__( 'Opened', 'email-campaign' );
        case 'bounced':
            return esc_html__( 'Bounced', 'email-campaign' );
        case 'unsubscribed':
            return esc_html__( 'Unsubscribed', 'email-campaign' );
        default:
            return ucfirst( esc_html( $status ) );
    }
}