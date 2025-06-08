<?php
/**
 * Manages the centralized contact database and provides a WP_List_Table interface.
 *
 * @package Email_Campaign_Pro
 * @subpackage Includes
 */

namespace EmailCampaign;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Contact_Manager
 */
class Contact_Manager {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_contacts_submenu_page' ) );
        add_action( 'admin_init', array( $this, 'handle_contacts_actions' ) );
        add_filter( 'set-screen-option', array( __CLASS__, 'set_screen_option' ), 10, 3 );
    }

    /**
     * Create the custom database table for centralized contacts.
     */
    public static function create_contact_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'email_contacts';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            email varchar(255) NOT NULL UNIQUE,
            name varchar(255) DEFAULT '',
            status varchar(50) DEFAULT 'active' NOT NULL, -- active, unsubscribed, bounced
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            last_campaign_id bigint(20) UNSIGNED DEFAULT 0 NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY email (email),
            KEY status (status)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    /**
     * Add or update a contact in the centralized database.
     *
     * @param string $email
     * @param string $name
     * @param int    $last_campaign_id
     * @return int|bool The ID of the inserted/updated contact, or false on failure.
     */
    public function add_or_update_contact( $email, $name = '', $last_campaign_id = 0 ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'email_contacts';

        // Check if the contact already exists.
        $existing_contact = $wpdb->get_row( $wpdb->prepare( "SELECT id, status FROM $table_name WHERE email = %s", $email ) );

        if ( $existing_contact ) {
            // Update existing contact, but don't override unsubscribed/bounced status.
            $data = array(
                'name'             => $name,
                'last_campaign_id' => $last_campaign_id,
            );
            $where = array( 'id' => $existing_contact->id );

            // Only update status if it's 'active' or if the new status is 'active' over 'pending' from a previous campaign
            // (e.g. if the contact was active, then failed in one campaign, but is active in another).
            // This logic might need refinement based on exact business rules for status precedence.
            if ( ! in_array( $existing_contact->status, array( 'unsubscribed', 'bounced' ) ) ) {
                // If it's currently not unsubscribed/bounced, keep it 'active' or similar.
                // You could add a 'pending' status here if desired for new imports, then update later.
                // For now, we assume if they are in the list, they are 'active' unless explicitly unsubscribed/bounced.
            }

            $updated = $wpdb->update( $table_name, $data, $where );
            return $updated !== false ? $existing_contact->id : false;

        } else {
            // Insert new contact.
            $inserted = $wpdb->insert(
                $table_name,
                array(
                    'email'            => $email,
                    'name'             => $name,
                    'status'           => 'active',
                    'created_at'       => current_time( 'mysql' ),
                    'last_campaign_id' => $last_campaign_id,
                ),
                array( '%s', '%s', '%s', '%s', '%d' )
            );
            return $inserted !== false ? $wpdb->insert_id : false;
        }
    }

    /**
     * Add "Contacts" submenu under Email Campaigns.
     */
    public function add_contacts_submenu_page() {
        add_submenu_page(
            'edit.php?post_type=email_campaign', // Parent slug
            __( 'Contacts', 'email-campaign' ),   // Page title
            __( 'Contacts', 'email-campaign' ),   // Menu title
            'manage_options',                    // Capability
            'email_campaign_contacts',           // Menu slug
            array( $this, 'render_contacts_page' ) // Callback function
        );
    }

    /**
     * Render the contacts admin page.
     */
    public function render_contacts_page() {
        $contact_list_table = new Email_Campaign_Contacts_List_Table();
        $contact_list_table->prepare_items();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Email Campaign Contacts', 'email-campaign' ); ?></h1>
            <p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=email_campaign_contacts&action=export_contacts' ) ); ?>" class="button button-primary"><?php esc_html_e( 'Export All Contacts to CSV', 'email-campaign' ); ?></a>
            </p>
            <form id="contacts-filter" method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>" />
                <?php $contact_list_table->display(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Handle actions for contacts (export, delete).
     */
    public function handle_contacts_actions() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( isset( $_GET['page'] ) && $_GET['page'] === 'email_campaign_contacts' ) {
            // Handle bulk actions.
            $contact_list_table = new Email_Campaign_Contacts_List_Table();
            $action = $contact_list_table->current_action();

            if ( 'delete' === $action ) {
                if ( ! empty( $_GET['contact'] ) && is_array( $_GET['contact'] ) ) {
                    check_admin_referer( 'bulk-contacts' );
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'email_contacts';
                    $contact_ids = array_map( 'absint', $_GET['contact'] );
                    $placeholders = implode( ', ', array_fill( 0, count( $contact_ids ), '%d' ) );
                    $wpdb->query( $wpdb->prepare( "DELETE FROM $table_name WHERE id IN ($placeholders)", $contact_ids ) );
                    wp_redirect( remove_query_arg( array( 'action', 'contact', '_wpnonce', '_wp_http_referer' ) ) );
                    exit;
                }
            } elseif ( 'export_contacts' === $action ) {
                 $this->export_all_contacts_to_csv();
            }
        }
    }

    /**
     * Export all contacts to a CSV file.
     */
    private function export_all_contacts_to_csv() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'email_contacts';

        $filename = 'email_campaign_contacts_' . date( 'Y-m-d' ) . '.csv';

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

        $output = fopen( 'php://output', 'w' );

        // Add CSV header.
        fputcsv( $output, array( 'ID', 'Email', 'Name', 'Status', 'Created At', 'Last Campaign ID' ) );

        // Fetch contacts in batches for large datasets.
        $offset = 0;
        $limit = 1000; // Adjust batch size as needed.

        while ( true ) {
            $contacts = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM $table_name ORDER BY id ASC LIMIT %d OFFSET %d",
                $limit,
                $offset
            ), ARRAY_A );

            if ( empty( $contacts ) ) {
                break;
            }

            foreach ( $contacts as $contact ) {
                fputcsv( $output, $contact );
            }

            $offset += $limit;
        }

        fclose( $output );
        exit;
    }

    /**
     * Sets the number of items per page for the list table.
     *
     * @param int    $status
     * @param string $option
     * @param int    $value
     * @return int
     */
    public static function set_screen_option( $status, $option, $value ) {
        if ( 'email_campaign_contacts_per_page' == $option ) {
            return $value;
        }
        return $status;
    }
}

// Ensure WP_List_Table is loaded.
if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class Email_Campaign_Contacts_List_Table extends WP_List_Table to display contacts.
 */
class Email_Campaign_Contacts_List_Table extends \WP_List_Table {

    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct( array(
            'singular' => __( 'Contact', 'email-campaign' ),
            'plural'   => __( 'Contacts', 'email-campaign' ),
            'ajax'     => false,
        ) );
    }

    /**
     * Get list of columns.
     *
     * @return array
     */
    public function get_columns() {
        $columns = array(
            'cb'               => '<input type="checkbox" />',
            'email'            => __( 'Email', 'email-campaign' ),
            'name'             => __( 'Name', 'email-campaign' ),
            'status'           => __( 'Status', 'email-campaign' ),
            'created_at'       => __( 'Created At', 'email-campaign' ),
            'last_campaign_id' => __( 'Last Campaign ID', 'email-campaign' ),
        );
        return $columns;
    }

    /**
     * Get sortable columns.
     *
     * @return array
     */
    public function get_sortable_columns() {
        $sortable_columns = array(
            'email'      => array( 'email', false ),
            'name'       => array( 'name', false ),
            'status'     => array( 'status', false ),
            'created_at' => array( 'created_at', false ),
        );
        return $sortable_columns;
    }

    /**
     * Get bulk actions.
     *
     * @return array
     */
    public function get_bulk_actions() {
        $actions = array(
            'delete' => __( 'Delete', 'email-campaign' ),
        );
        return $actions;
    }

    /**
     * Handles the 'cb' column.
     *
     * @param object $item
     * @return string
     */
    public function column_cb( $item ) {
        return sprintf(
            '<input type="checkbox" name="contact[]" value="%s" />',
            $item->id
        );
    }

    /**
     * Default column renderer.
     *
     * @param object $item
     * @param string $column_name
     * @return string
     */
    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'email':
                $actions = array(
                    'edit'   => sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'admin.php?page=email_campaign_contacts&action=edit&contact=' . $item->id ) ), __( 'Edit', 'email-campaign' ) ),
                    'delete' => sprintf( '<a href="%s" class="submitdelete" onclick="return confirm(\'%s\')">%s</a>',
                        wp_nonce_url( admin_url( 'admin.php?page=email_campaign_contacts&action=delete&contact[]=' . $item->id ), 'bulk-contacts' ),
                        esc_js( __( 'Are you sure you want to delete this contact?', 'email-campaign' ) ),
                        __( 'Delete', 'email-campaign' )
                    ),
                );
                return sprintf( '<strong>%1$s</strong>%2$s',
                    esc_html( $item->email ),
                    $this->row_actions( $actions )
                );
            case 'name':
                return esc_html( $item->name );
            case 'status':
                return esc_html( \EmailCampaign\ec_pro_get_log_status_label( $item->status ) );
            case 'created_at':
                return esc_html( $item->created_at );
            case 'last_campaign_id':
                $campaign_title = get_the_title( $item->last_campaign_id );
                if ( $campaign_title ) {
                    return sprintf( '<a href="%s">%s (#%d)</a>',
                        esc_url( get_edit_post_link( $item->last_campaign_id ) ),
                        esc_html( $campaign_title ),
                        intval( $item->last_campaign_id )
                    );
                }
                return intval( $item->last_campaign_id );
            default:
                return print_r( $item, true ); // For debugging.
        }
    }

    /**
     * Prepares the list of items for displaying.
     */
    public function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'email_contacts';

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array( $columns, $hidden, $sortable );

        $per_page = $this->get_items_per_page( 'email_campaign_contacts_per_page', 20 );
        $current_page = $this->get_pagenum();
        $offset = ( $current_page - 1 ) * $per_page;

        $search_query = '';
        if ( isset( $_REQUEST['s'] ) && ! empty( $_REQUEST['s'] ) ) {
            $search_query = esc_sql( $wpdb->esc_like( trim( $_REQUEST['s'] ) ) );
            $where_clause = $wpdb->prepare( " WHERE email LIKE %s OR name LIKE %s", '%' . $search_query . '%', '%' . $search_query . '%' );
        } else {
            $where_clause = '';
        }

        $order_by = ( isset( $_REQUEST['orderby'] ) && in_array( $_REQUEST['orderby'], array_keys( $this->get_sortable_columns() ) ) ) ? esc_sql( $_REQUEST['orderby'] ) : 'id';
        $order = ( isset( $_REQUEST['order'] ) && in_array( $_REQUEST['order'], array( 'asc', 'desc' ) ) ) ? esc_sql( $_REQUEST['order'] ) : 'desc';

        $total_items = $wpdb->get_var( "SELECT COUNT(id) FROM {$table_name} {$where_clause}" );
        $this->items = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table_name} {$where_clause} ORDER BY {$order_by} {$order} LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ) );

        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil( $total_items / $per_page ),
        ) );
    }
}