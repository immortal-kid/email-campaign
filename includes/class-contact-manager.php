<?php
// File: includes/class-contact-manager.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Ensure WP_List_Table is available
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Custom list table for displaying contacts.
 */
class EC_Contacts_List extends WP_List_Table {
    private $table_name;

    public function __construct( $table_name ) {
        parent::__construct([
            'singular' => 'contact',
            'plural'   => 'contacts',
            'ajax'     => false,
        ]);
        global $wpdb;
        $this->table_name = $wpdb->prefix . $table_name;
    }

    public function get_columns() {
        return [
            'cb'     => '<input type="checkbox" />',
            'email'  => __( 'Email',  'email-campaign' ),
            'name'   => __( 'Name',   'email-campaign' ),
            'status' => __( 'Status', 'email-campaign' ),
            'date'   => __( 'Added',  'email-campaign' ),
        ];
    }

    public function column_cb( $item ) {
        return sprintf(
            '<input type="checkbox" name="contacts[]" value="%s" />',
            esc_attr( $item->id )
        );
    }

    public function prepare_items() {
        global $wpdb;
        $per_page = 50;
        $current  = $this->get_pagenum();
        $total    = $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name}" );

        $this->set_pagination_args([
            'total_items' => $total,
            'per_page'    => $per_page,
        ]);

        $offset = ( $current - 1 ) * $per_page;
        $this->items = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->table_name} ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page, $offset
        ) );
    }

    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'email':
            case 'name':
            case 'status':
            case 'date':
                return esc_html( $item->{$column_name} );
            default:
                return '';
        }
    }
}

class EC_Contact_Manager {

    private $contacts_table;

    public function __construct() {
        global $wpdb;
        $this->contacts_table = $wpdb->prefix . 'email_contacts';

        add_action( 'admin_menu',                         [ $this, 'add_contacts_submenu' ] );
        add_action( 'admin_post_ec_export_contacts',      [ $this, 'handle_export' ] );
    }

    public function add_contacts_submenu() {
        add_submenu_page(
            'edit.php?post_type=email_campaign',
            __( 'Contacts', 'email-campaign' ),
            __( 'Contacts', 'email-campaign' ),
            'manage_options',
            'ec_contacts',
            [ $this, 'render_contacts_page' ]
        );
    }

    public function render_contacts_page() {
        echo '<div class="wrap"><h1>' . esc_html__( 'Contacts', 'email-campaign' ) . '</h1>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="ec_export_contacts">';
        submit_button( __( 'Export to CSV', 'email-campaign' ), 'secondary', 'submit', false );

        $list_table = new EC_Contacts_List( 'email_contacts' );
        $list_table->prepare_items();
        $list_table->display();

        echo '</form></div>';
    }

    public function handle_export() {
        global $wpdb;
        $results = $wpdb->get_results( "SELECT * FROM {$this->contacts_table}", ARRAY_A );
        if ( empty( $results ) ) {
            wp_die( __( 'No contacts to export.', 'email-campaign' ) );
        }

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=contacts.csv' );

        $output = fopen( 'php://output', 'w' );
        fputcsv( $output, array_keys( $results[0] ) );
        foreach ( $results as $row ) {
            fputcsv( $output, $row );
        }
        fclose( $output );
        exit;
    }
}

new EC_Contact_Manager();
