<?php
namespace EC;

defined( 'ABSPATH' ) || exit;
if ( ! class_exists( '\\WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}
class Contact_Manager {
    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'menu' ] );
    }

    public static function menu() {
        add_submenu_page(
            'edit.php?post_type=email_campaign',
            'Contacts',
            'Contacts',
            'manage_options',
            'ec_contacts',
            [ __CLASS__, 'render' ]
        );
    }

    public static function render() {
        if ( ! class_exists( '\WP_List_Table' ) ) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
        }

        $table = new Contact_List_Table();
        echo '<div class="wrap"><h1 class="wp-heading-inline">Contacts</h1>';
        $table->prepare_items();
        $table->display();
        echo '</div>';
    }
}

class Contact_List_Table extends \WP_List_Table {

    public function get_columns() {
        return [
            'email'  => 'Email',
            'name'   => 'Name',
            'status' => 'Status',
            'created_at' => 'Created',
        ];
    }

    public function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'email_contacts';
        $per_page = 20;
        $paged = $this->get_pagenum();
        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );

        $this->set_pagination_args( [
            'total_items' => $total,
            'per_page'    => $per_page,
        ] );

        $this->items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $per_page,
                ( $paged - 1 ) * $per_page
            ),
            ARRAY_A
        );
    }

    public function column_default( $item, $column_name ) {
        return esc_html( $item[ $column_name ] );
    }
}
