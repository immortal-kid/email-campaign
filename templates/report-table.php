<?php
// File: templates/report-table.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Expect $logs array to be passed in
if ( empty( $logs ) ) {
    echo '<p>' . esc_html__( 'No records found for this campaign.', 'email-campaign' ) . '</p>';
    return;
}
?>
<table class="widefat fixed striped">
    <thead>
        <tr>
            <th><?php esc_html_e( 'Email', 'email-campaign' ); ?></th>
            <th><?php esc_html_e( 'Delivery', 'email-campaign' ); ?></th>
            <th><?php esc_html_e( 'Opened', 'email-campaign' ); ?></th>
            <th><?php esc_html_e( 'Bounced', 'email-campaign' ); ?></th>
            <th><?php esc_html_e( 'Sent At', 'email-campaign' ); ?></th>
            <th><?php esc_html_e( 'Opened At', 'email-campaign' ); ?></th>
            <th><?php esc_html_e( 'Bounced At', 'email-campaign' ); ?></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ( $logs as $row ) : ?>
        <tr>
            <td><?php echo esc_html( $row->email ); ?></td>
            <td><?php echo esc_html( ucfirst( $row->status ) ); ?></td>
            <td><?php echo $row->opened_at ? esc_html__( 'Yes', 'email-campaign' ) : esc_html__( 'No', 'email-campaign' ); ?></td>
            <td><?php echo $row->bounced_at ? esc_html__( 'Yes', 'email-campaign' ) : esc_html__( 'No', 'email-campaign' ); ?></td>
            <td><?php echo esc_html( $row->sent_at ); ?></td>
            <td><?php echo esc_html( $row->opened_at ); ?></td>
            <td><?php echo esc_html( $row->bounced_at ); ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
