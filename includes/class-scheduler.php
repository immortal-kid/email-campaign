<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EC_Scheduler {

    public function __construct() {
        add_action( 'publish_email_campaign', [ $this, 'schedule_campaign' ], 20, 2 );
        add_action( 'ec_send_email', [ $this, 'send_email_callback' ], 10, 1 );
    }

    public function schedule_campaign( $post_id, $post ) {
        global $wpdb;
        $table = $wpdb->prefix . 'email_campaigns_subscribers';
        $subs  = $wpdb->get_results( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE campaign_id = %d AND status = %s",
            $post_id, 'pending'
        ) );
        foreach ( $subs as $i => $sub ) {
            as_schedule_single_action(
                time() + ( $i * 3 ),
                'ec_send_email',
                [ 'subscriber_id' => $sub->id ],
                'email-campaign'
            );
        }
    }

    public function send_email_callback( $args ) {
        $sub_id = intval( $args['subscriber_id'] );
        global $wpdb;
        $sub = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}email_campaigns_subscribers WHERE id = %d",
            $sub_id
        ) );
        if ( ! $sub ) {
            return;
        }

        $campaign_id = $sub->campaign_id;
        $subject     = get_post_meta( $campaign_id, '_ec_subject', true );
        $preheader   = get_post_meta( $campaign_id, '_ec_preheader', true );
        $content     = get_post_field( 'post_content', $campaign_id );
        // Personalize
        $content = EC_Utils::replace_tokens( $content, $sub->email, $sub->name );
        $subject = EC_Utils::replace_tokens( $subject, $sub->email, $sub->name );

        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        $sent    = wp_mail( $sub->email, $subject, $content, $headers );

        if ( $sent ) {
            $status = 'sent';
            $wpdb->update(
                $wpdb->prefix . 'email_campaigns_subscribers',
                [ 'status' => $status ],
                [ 'id'     => $sub_id ],
                [ '%s' ],
                [ '%d' ]
            );
        } else {
            $attempts = get_post_meta( $sub_id, '_ec_attempts', true ) ?: 0;
            if ( $attempts < 3 ) {
                as_schedule_single_action( time() + 300, 'ec_send_email', [ 'subscriber_id' => $sub_id ], 'email-campaign' );
                update_post_meta( $sub_id, '_ec_attempts', $attempts + 1 );
            } else {
                $wpdb->update(
                    $wpdb->prefix . 'email_campaigns_subscribers',
                    [ 'status' => 'failed' ],
                    [ 'id'     => $sub_id ],
                    [ '%s' ],
                    [ '%d' ]
                );
            }
        }
    }
}

new EC_Scheduler();
