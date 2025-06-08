<?php
/**
 * Handles scheduling and sending of emails using Action Scheduler.
 *
 * @package Email_Campaign_Pro
 * @subpackage Includes
 */

namespace EmailCampaign;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Scheduler {

    const HOOK_SEND_CAMPAIGN_START = 'ec_pro_campaign_start_schedule';
    const HOOK_SEND_SINGLE_EMAIL = 'ec_pro_send_single_email_action';

    /**
     * Constructor.
     */
    public function __construct() {
        // Hook for starting a campaign
        add_action( self::HOOK_SEND_CAMPAIGN_START, array( $this, 'start_campaign_sending' ), 10, 1 );

        // Hook for sending a single email
        add_action( self::HOOK_SEND_SINGLE_EMAIL, array( $this, 'send_single_email' ), 10, 2 );

        // AJAX handlers for pause/resume/cancel (defined in CPT_Email_Campaign but handled here)
        add_action( 'wp_ajax_ec_pro_pause_campaign', array( $this, 'dummy_ajax_handler' ) ); // Handled by CPT class
        add_action( 'wp_ajax_ec_pro_resume_campaign', array( $this, 'dummy_ajax_handler' ) ); // Handled by CPT class
        add_action( 'wp_ajax_ec_pro_cancel_campaign', array( $this, 'dummy_ajax_handler' ) ); // Handled by CPT class
    }

    /**
     * Dummy AJAX handler to satisfy CPT_Email_Campaign; actions are processed there.
     */
    public function dummy_ajax_handler() {
        // This function is just to make sure the AJAX hook exists.
        // The actual logic is in CPT_Email_Campaign::handle_campaign_actions().
        // wp_send_json_error() or wp_send_json_success() will be called there.
        // This prevents "0" output if another AJAX handler isn't found first.
    }

    /**
     * Schedules the initial action to start sending a campaign.
     *
     * @param int $campaign_id The ID of the campaign post.
     * @param int $timestamp The timestamp to schedule the action.
     * @return bool True on success, false on failure.
     */
    public function schedule_campaign_start( $campaign_id, $timestamp ) {
        if ( ! class_exists( '\ActionScheduler_Store' ) ) {
            return false;
        }

        // Cancel any existing 'start' schedules for this campaign to avoid duplicates.
        \as_unschedule_all_actions( self::HOOK_SEND_CAMPAIGN_START, array( $campaign_id ) );

        // Schedule the start of the campaign.
        \as_schedule_single_action( $timestamp, self::HOOK_SEND_CAMPAIGN_START, array( $campaign_id ), 'email-campaign' );
        error_log( sprintf( 'Email Campaign Pro: Campaign #%d start scheduled for %s.', $campaign_id, date( 'Y-m-d H:i:s', $timestamp ) ) );
        return true;
    }

    /**
     * Starts the campaign sending process by scheduling individual emails.
     * This is called by Action Scheduler.
     *
     * @param int $campaign_id The ID of the campaign post.
     */
    public function start_campaign_sending( $campaign_id ) {
        if ( ! class_exists( '\ActionScheduler_Store' ) ) {
            error_log( 'Email Campaign Pro: Action Scheduler not found when starting campaign.' );
            return;
        }

        global $wpdb;
        $table_subscribers = $wpdb->prefix . 'email_campaigns_subscribers';

        // Ensure campaign status is 'in_progress' or 'paused' when starting.
        // It should be 'in_progress' from CPT save_post, but double-check.
        $campaign_status = get_post_meta( $campaign_id, '_ec_pro_campaign_status', true );
        if ( 'in_progress' !== $campaign_status && 'paused' !== $campaign_status ) {
            error_log( sprintf( 'Email Campaign Pro: Campaign #%d start skipped. Status is %s, not "in_progress" or "paused".', $campaign_id, $campaign_status ) );
            return;
        }

        // Get all pending subscribers for this campaign.
        $subscribers = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, email, name FROM {$table_subscribers} WHERE campaign_id = %d AND status = 'pending' ORDER BY id ASC",
            $campaign_id
        ), ARRAY_A );

        if ( empty( $subscribers ) ) {
            update_post_meta( $campaign_id, '_ec_pro_campaign_status', 'completed' );
            error_log( sprintf( 'Email Campaign Pro: Campaign #%d has no pending subscribers or already completed.', $campaign_id ) );
            return;
        }

        error_log( sprintf( 'Email Campaign Pro: Starting to schedule %d emails for Campaign #%d.', count( $subscribers ), $campaign_id ) );

        $delay_seconds = 0; // Initial delay.
        foreach ( $subscribers as $index => $subscriber ) {
            // Check campaign status before scheduling each batch to respect pause/cancel.
            $current_campaign_status = get_post_meta( $campaign_id, '_ec_pro_campaign_status', true );
            if ( 'paused' === $current_campaign_status || 'cancelled' === $current_campaign_status ) {
                error_log( sprintf( 'Email Campaign Pro: Scheduling for Campaign #%d stopped due to status change to %s.', $campaign_id, $current_campaign_status ) );
                break; // Stop scheduling if paused or cancelled.
            }

            // Schedule one email every 3 seconds.
            \as_schedule_single_action( time() + $delay_seconds, self::HOOK_SEND_SINGLE_EMAIL, array( $campaign_id, $subscriber['id'] ), 'email-campaign' );
            $delay_seconds += 3; // Increment delay for next email.

            // To avoid scheduling too many at once for very large lists,
            // you might want to schedule in batches, and have the last action
            // of a batch schedule the next batch. This is more complex.
            // For 10,000 emails, this direct scheduling should be fine for Action Scheduler.
        }
        error_log( sprintf( 'Email Campaign Pro: Finished scheduling emails for Campaign #%d. Total scheduled: %d.', $campaign_id, count( $subscribers ) ) );
    }

    /**
     * Sends a single email for a given campaign and subscriber.
     * This is called by Action Scheduler.
     *
     * @param int $campaign_id   The ID of the campaign post.
     * @param int $subscriber_id The ID of the subscriber from wp_email_campaigns_subscribers.
     */
    public function send_single_email( $campaign_id, $subscriber_id ) {
        global $wpdb;
        $table_subscribers = $wpdb->prefix . 'email_campaigns_subscribers';
        $table_logs        = $wpdb->prefix . 'email_campaigns_logs';
        $table_contacts    = $wpdb->prefix . 'email_contacts';

        // Check if campaign is still active (not paused or cancelled).
        $campaign_status = get_post_meta( $campaign_id, '_ec_pro_campaign_status', true );
        if ( 'paused' === $campaign_status || 'cancelled' === $campaign_status ) {
            error_log( sprintf( 'Email Campaign Pro: Skipping email for Campaign #%d, Subscriber #%d. Campaign status is %s.', $campaign_id, $subscriber_id, $campaign_status ) );
            return;
        }

        $subscriber = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_subscribers} WHERE id = %d AND campaign_id = %d", $subscriber_id, $campaign_id ) );

        if ( ! $subscriber ) {
            error_log( sprintf( 'Email Campaign Pro: Subscriber #%d not found for Campaign #%d.', $subscriber_id, $campaign_id ) );
            return;
        }

        // Check central contact status: if unsubscribed or bounced, skip sending.
        $contact_status = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$table_contacts} WHERE email = %s", $subscriber->email ) );
        if ( in_array( $contact_status, array( 'unsubscribed', 'bounced' ) ) ) {
            error_log( sprintf( 'Email Campaign Pro: Skipping email for %s. Contact status is %s.', $subscriber->email, $contact_status ) );
            $wpdb->update(
                $table_subscribers,
                array( 'status' => 'skipped_due_to_unsub' ), // A more specific status for clarity.
                array( 'id' => $subscriber_id )
            );
            $this->check_campaign_completion( $campaign_id ); // Check if campaign is done.
            return;
        }

        // Get email content, subject, pre-header from campaign CPT.
        $campaign_post = get_post( $campaign_id );
        if ( ! $campaign_post ) {
            error_log( sprintf( 'Email Campaign Pro: Campaign post #%d not found for subscriber #%d.', $campaign_id, $subscriber_id ) );
            return;
        }

        $email_subject   = get_post_meta( $campaign_id, '_ec_pro_email_subject', true );
        $email_preheader = get_post_meta( $campaign_id, '_ec_pro_email_preheader', true );
        $email_content   = $campaign_post->post_content;

        // Personalization data.
        $personalization_data = array(
            'first_name' => $subscriber->name,
            'email'      => $subscriber->email,
            // Add more data from wp_email_contacts if needed.
        );

        $processed_subject   = ec_pro_replace_personalization_tokens( $email_subject, $personalization_data );
        $processed_preheader = ec_pro_replace_personalization_tokens( $email_preheader, $personalization_data );
        $processed_content   = ec_pro_replace_personalization_tokens( $email_content, $personalization_data );

        // Add unsubscribe link and physical address to email.
        $unsubscribe_link = ec_pro_get_unsubscribe_url( $subscriber->email );
        $physical_address = ec_pro_get_physical_address();

        $email_body = sprintf(
            '<div style="font-family: Arial, sans-serif; line-height: 1.6;">
                %s
                <p style="font-size: 10px; color: #888; margin-top: 20px;">
                    This email was sent to %s. <a href="%s" style="color: #0073aa;">Unsubscribe</a> from future emails.<br/>
                    %s
                </p>
            </div>',
            wpautop( $processed_content ), // wpautop for basic formatting if using classic editor.
            esc_html( $subscriber->email ),
            esc_url( $unsubscribe_link ),
            esc_html( $physical_address )
        );

        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'X-Mailer: WordPress Email Campaign Pro',
            'X-Preheader: ' . $processed_preheader, // Custom header for preheader
        );

        $to      = $subscriber->email;
        $subject = $processed_subject;
        $message = $email_body;

        $sent = false;
        try {
            // wp_mail returns true on success, false on failure.
            $sent = wp_mail( $to, $subject, $message, $headers );
        } catch ( \Exception $e ) {
            error_log( sprintf( 'Email Campaign Pro: wp_mail Exception for %s: %s', $to, $e->getMessage() ) );
            $sent = false;
        }

        $log_id = $wpdb->insert(
            $table_logs,
            array(
                'campaign_id' => $campaign_id,
                'subscriber_id' => $subscriber->id,
                'email'       => $subscriber->email,
                'status'      => $sent ? 'sent' : 'failed',
                'sent_at'     => current_time( 'mysql' ),
                'attempt_count' => 1 // Initial attempt
            ),
            array( '%d', '%d', '%s', '%s', '%s', '%d' )
        );

        if ( $sent ) {
            $wpdb->update(
                $table_subscribers,
                array( 'status' => 'sent' ),
                array( 'id' => $subscriber->id )
            );
            error_log( sprintf( 'Email Campaign Pro: Email sent to %s for Campaign #%d.', $subscriber->email, $campaign_id ) );
        } else {
            error_log( sprintf( 'Email Campaign Pro: Failed to send email to %s for Campaign #%d. Attempting retry.', $subscriber->email, $campaign_id ) );

            // Retry logic: Schedule a new action for retry.
            $current_attempts = 1; // This is the first failure.
            $last_log = $wpdb->get_row( $wpdb->prepare(
                "SELECT attempt_count FROM {$table_logs} WHERE subscriber_id = %d AND campaign_id = %d ORDER BY sent_at DESC LIMIT 1",
                $subscriber->id,
                $campaign_id
            ) );
            if ( $last_log ) {
                $current_attempts = $last_log->attempt_count + 1;
            }

            if ( $current_attempts <= 3 ) {
                $retry_delay = 5 * MINUTE_IN_SECONDS; // 5 minutes.
                \as_schedule_single_action(
                    time() + $retry_delay,
                    self::HOOK_SEND_SINGLE_EMAIL,
                    array( $campaign_id, $subscriber->id ),
                    'email-campaign',
                    true, // Unique (do not allow duplicates for this hook with these args)
                    $current_attempts // Pass attempt count for logging
                );
                error_log( sprintf( 'Email Campaign Pro: Retrying email for %s (Attempt %d) for Campaign #%d.', $subscriber->email, $current_attempts, $campaign_id ) );

                // Update the log with current attempt count or add new log entry for retry scheduling.
                // For simplicity, we'll update the initial log entry here. More robust: create new entry for each attempt.
                $wpdb->update(
                    $table_logs,
                    array(
                        'status'        => 'retrying',
                        'attempt_count' => $current_attempts
                    ),
                    array( 'id' => $log_id ) // Update the current log entry's status
                );

            } else {
                // Max retries reached.
                $wpdb->update(
                    $table_subscribers,
                    array( 'status' => 'failed' ),
                    array( 'id' => $subscriber->id )
                );
                $wpdb->update(
                    $table_logs,
                    array( 'status' => 'failed', 'attempt_count' => $current_attempts - 1 ),
                    array( 'id' => $log_id )
                );
                error_log( sprintf( 'Email Campaign Pro: Max retries reached for %s for Campaign #%d.', $subscriber->email, $campaign_id ) );
            }
        }

        // Check if all emails for the campaign are sent.
        $this->check_campaign_completion( $campaign_id );
    }

    /**
     * Checks if all emails for a campaign are sent and updates campaign status.
     *
     * @param int $campaign_id The ID of the campaign post.
     */
    private function check_campaign_completion( $campaign_id ) {
        global $wpdb;
        $table_subscribers = $wpdb->prefix . 'email_campaigns_subscribers';

        $total_subscribers = get_post_meta( $campaign_id, '_ec_pro_total_subscribers', true );
        if ( ! $total_subscribers ) {
            $total_subscribers = 0;
        }

        $sent_count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_subscribers} WHERE campaign_id = %d AND status = 'sent'",
            $campaign_id
        ) );

        $failed_count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_subscribers} WHERE campaign_id = %d AND status = 'failed'",
            $campaign_id
        ) );

        $skipped_count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_subscribers} WHERE campaign_id = %d AND status = 'skipped_due_to_unsub'",
            $campaign_id
        ) );

        // If sent + failed + skipped equals total subscribers, the campaign is completed.
        if ( ( $sent_count + $failed_count + $skipped_count ) >= $total_subscribers && $total_subscribers > 0 ) {
            update_post_meta( $campaign_id, '_ec_pro_campaign_status', 'completed' );
            error_log( sprintf( 'Email Campaign Pro: Campaign #%d marked as completed. Sent: %d, Failed: %d, Skipped: %d, Total: %d.', $campaign_id, $sent_count, $failed_count, $skipped_count, $total_subscribers ) );

            // Also clear any remaining scheduled actions for this campaign, just in case.
            \as_unschedule_all_actions( self::HOOK_SEND_SINGLE_EMAIL, array( $campaign_id ) );
            \as_unschedule_all_actions( self::HOOK_SEND_CAMPAIGN_START, array( $campaign_id ) );
        }
    }

    /**
     * Pauses an ongoing campaign.
     * Cancels all pending and in-progress actions for the campaign.
     *
     * @param int $campaign_id
     * @return bool
     */
    public function pause_campaign( $campaign_id ) {
        if ( ! class_exists( '\ActionScheduler_Store' ) ) {
            return false;
        }

        $store = \ActionScheduler_Store::instance();

        // Cancel initial start action if still pending.
        \as_unschedule_all_actions( self::HOOK_SEND_CAMPAIGN_START, array( $campaign_id ) );

        // Find and cancel all pending and in-progress single email actions for this campaign.
        $actions_to_cancel = $store->query_actions( array(
            'hook'     => self::HOOK_SEND_SINGLE_EMAIL,
            'args'     => array( $campaign_id ), // Match only actions for this campaign
            'status'   => array( 'pending', 'in-progress' ),
            'per_page' => -1, // Get all
        ) );

        foreach ( $actions_to_cancel as $action_id ) {
            $store->cancel_action( $action_id );
        }

        update_post_meta( $campaign_id, '_ec_pro_campaign_status', 'paused' );
        error_log( sprintf( 'Email Campaign Pro: Campaign #%d paused. %d actions cancelled.', $campaign_id, count( $actions_to_cancel ) ) );
        return true;
    }

    /**
     * Resumes a paused campaign.
     * Re-schedules pending emails from the last sent point.
     *
     * @param int $campaign_id
     * @return bool
     */
    public function resume_campaign( $campaign_id ) {
        if ( ! class_exists( '\ActionScheduler_Store' ) ) {
            return false;
        }

        // Schedule the campaign start action again, which will re-queue pending emails.
        // It's crucial that start_campaign_sending only schedules 'pending' emails.
        $this->schedule_campaign_start( $campaign_id, time() + 5 ); // Resume immediately.

        update_post_meta( $campaign_id, '_ec_pro_campaign_status', 'in_progress' );
        error_log( sprintf( 'Email Campaign Pro: Campaign #%d resumed.', $campaign_id ) );
        return true;
    }

    /**
     * Cancels an ongoing or paused campaign.
     * Removes all scheduled actions and sets status to cancelled.
     *
     * @param int $campaign_id
     * @return bool
     */
    public function cancel_campaign( $campaign_id ) {
        if ( ! class_exists( '\ActionScheduler_Store' ) ) {
            return false;
        }

        $store = \ActionScheduler_Store::instance();

        // Cancel initial start action if still pending.
        \as_unschedule_all_actions( self::HOOK_SEND_CAMPAIGN_START, array( $campaign_id ) );

        // Find and cancel all pending and in-progress single email actions for this campaign.
        $actions_to_cancel = $store->query_actions( array(
            'hook'     => self::HOOK_SEND_SINGLE_EMAIL,
            'args'     => array( $campaign_id ),
            'status'   => array( 'pending', 'in-progress' ),
            'per_page' => -1, // Get all
        ) );

        foreach ( $actions_to_cancel as $action_id ) {
            $store->cancel_action( $action_id );
        }

        update_post_meta( $campaign_id, '_ec_pro_campaign_status', 'cancelled' );
        error_log( sprintf( 'Email Campaign Pro: Campaign #%d cancelled. %d actions removed.', $campaign_id, count( $actions_to_cancel ) ) );

        return true;
    }
}