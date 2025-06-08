<?php
namespace EC;

defined( 'ABSPATH' ) || exit;

use ActionScheduler;

class Scheduler {
    const ACTION_HOOK = 'ec/send_email';

    public static function init() {
        add_action( self::ACTION_HOOK, [ __CLASS__, 'send_email' ], 10, 2 );
    }

    public static function queue_campaign( $campaign_id ) {
        global $wpdb;
        $subs_table = $wpdb->prefix . 'email_campaigns_subscribers';
        $subs = $wpdb->get_results( $wpdb->prepare(
            "SELECT id FROM $subs_table WHERE campaign_id=%d AND status='Pending'",
            $campaign_id
        ) );

        $i = 0;
        foreach ( $subs as $sub ) {
            \ActionScheduler\ActionScheduler::schedule_single_action(
                time() + ( $i * 3 ),
                self::ACTION_HOOK,
                [ $campaign_id, $sub->id ]
            );
            $i++;
        }
    }

    public static function send_email( $campaign_id, $sub_id ) {
        global $wpdb;
        $subs_table = $wpdb->prefix . 'email_campaigns_subscribers';
        $camp_post = get_post( $campaign_id );
        if ( ! $camp_post || $camp_post->post_type !== 'email_campaign' ) {
            return;
        }

        $sub = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM $subs_table WHERE id=%d",
            $sub_id
        ) );

        if ( ! $sub || $sub->status !== 'Pending' ) {
            return;
        }

        $subject   = get_post_meta( $campaign_id, '_ec_subject', true );
        $preheader = get_post_meta( $campaign_id, '_ec_preheader', true );
        $content   = Helper_Functions::replace_tokens( $camp_post->post_content, [
            'name' => $sub->name,
        ] );

        // add preheader text hidden
        $content = '<span style="display:none;opacity:0;">' . esc_html( $preheader ) . '</span>' . $content;

        // tracking pixel
        $pixel = sprintf(
            '<img src="%s" width="1" height="1" style="display:none"/>',
            esc_url( add_query_arg( [
                'ec_track' => 'open',
                'cid'      => $campaign_id,
                'sid'      => $sub_id,
            ], home_url( '/' ) ) )
        );
        $content .= $pixel;

        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];

        $sent = wp_mail( $sub->email, $subject, $content, $headers );

        if ( $sent ) {
            $wpdb->update( $subs_table, [
                'status' => 'Sent',
            ], [ 'id' => $sub_id ], [ '%s' ], [ '%d' ] );
        } else {
            // retry logic handled by Action Scheduler retry (max 3)
            $wpdb->update( $subs_table, [
                'status' => 'Failed',
            ], [ 'id' => $sub_id ], [ '%s' ], [ '%d' ] );
        }
    }

    public static function campaign_progress_text( $campaign_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'email_campaigns_subscribers';
        $total = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE campaign_id=%d",
            $campaign_id
        ) );
        $sent = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE campaign_id=%d AND status IN ('Sent','Opened')",
            $campaign_id
        ) );
        return "$sent / $total sent";
    }
}
