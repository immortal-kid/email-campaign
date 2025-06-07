<?php
// File: templates/contacts-list.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Ensure our list class is available
if ( ! class_exists( 'EC_Contacts_List' ) ) {
    require_once WP_PLUGIN_DIR . '/email-campaign/includes/class-contact-manager.php';
}

// Instantiate and prepare the list table
$list_table = new EC_Contacts_List( 'email_contacts' );
$list_table->prepare_items();
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Contacts', 'email-campaign' ); ?></h1>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="ec_export_contacts">
        <?php submit_button( __( 'Export to CSV', 'email-campaign' ), 'secondary', 'submit', false ); ?>
    </form>
    <form id="contacts-table-form">
        <?php $list_table->display(); ?>
    </form>
</div>
