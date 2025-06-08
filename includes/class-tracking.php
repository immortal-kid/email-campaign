<?php
/**
 * Handles reporting for email campaigns.
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
 * Class Reports
 */
class Reports {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_report_admin_page' ) );
        add_filter( 'set-screen-option', array( __CLASS__, 'set_screen_option' ), 10, 3 );
    }

    /**
     * Add the campaign report admin page.
     */
    public function add_report_admin_page() {
        add_submenu_page(
            null, // This makes it a hidden submenu, only accessible via direct link.
            __( 'Email Campaign Report', 'email-campaign' ),
            __( 'Email Campaign Report', 'email-campaign' ),
            'manage_options', // Capability required.
            'email_campaign_report',
            array( $this, 'render_report_page' )
        );
    }

    /**
     * Render the report admin page.
     */
    public function render_report_page() {
        if ( ! isset( $_GET['post_id'] ) || ! is_numeric( $_GET['post_id'] ) ) {
            wp_die( esc_html__( 'Campaign ID not specified.', 'email-campaign' ) );
        }

        $campaign_id = intval( $_GET['post_id'] );
        $campaign_title = get_the_title( $campaign_id );

        if ( ! $campaign_title ) {
            wp_die( esc_html__( 'Campaign not found.', 'email-campaign' ) );
        }

        $report_list_table = new Email_Campaign_Report_List_Table( $campaign_id );
        $report_list_table->prepare_items();
        ?>
        <div class="wrap">
            <h1><?php printf( esc_html__( 'Report for Campaign: %s', 'email-campaign' ), esc_html( $campaign_title ) ); ?></h1>
            <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=email_campaign' ) ); ?>" class="button button-secondary"><?php esc_html_e( 'Back to Campaigns', 'email-campaign' ); ?></a>
            <p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=email_campaign_report&action=export_report&post_id=' . $campaign_id ) ); ?>" class="button button-primary"><?php esc_html_e( 'Export Report to CSV', 'email-campaign' ); ?></a>
            </p>
            <form id="campaign-report-filter" method="get">
                <input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>" />
                <input type="hidden" name="post_id" value="<?php echo intval( $campaign_id ); ?>" />
                <?php $report_list_table->display(); ?>
            </form>
        </div>
        <?php
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
        if ( 'email_campaign_report_per_page' == $option ) {
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
 * Class Email_Campaign_Report_List_Table extends WP_List_Table to display campaign reports.
 */
class Email_Campaign_Report_List_Table extends \WP_List_Table {

    private $campaign_id;

    /**
     * Constructor.
     *
     * @param int $campaign_id The ID of the campaign to report on.
     */
    public function __construct( $campaign_id ) {
        parent::__construct( array(
            'singular' => __( 'Email Log', 'email-campaign' ),
            'plural'   => __( 'Email Logs', 'email-campaign' ),
            'ajax'     => false,
        ) );
        $this->campaign_id = $campaign_id;
        add_action( 'admin_init', array( $this, 'handle_report_actions' ) );
    }

    /**
     * Handles report actions (e.g., export).
     */
    public function handle_report_actions() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( isset( $_GET['page'] ) && $_GET['page'] === 'email_campaign_report' && isset( $_GET['action'] ) ) {
            if ( 'export_report' === $_GET['action'] && isset( $_GET['post_id'] ) ) {
                $campaign_id = intval( $_GET['post_id'] );
                $this->export_campaign_report_to_csv( $campaign_id );
            }
        }
    }

    /**
     * Export campaign report to CSV.
     *
     * @param int $campaign_id
     */
    private function export_campaign_report_to_csv( $campaign_id ) {
        global $wpdb;
        $table_logs = $wpdb->prefix . 'email_campaigns_logs';

        $campaign_title = get_the_title( $campaign_id );
        $filename = 'campaign_report_' . sanitize_title( $campaign_title ) . '_' . date( 'Y-m-d' ) . '.csv';

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

        $output = fopen( 'php://output', 'w' );

        // Add CSV header.
        fputcsv( $output, array( 'Email', 'Delivery Status', 'Open Status', 'Bounce Status', 'Sent At', 'Opened At', 'Bounced At', 'Attempts' ) );

        $offset = 0;
        $limit = 1000;

        while ( true ) {
            $logs = $wpdb->get_results( $wpdb->prepare(
                "SELECT email, status, sent_at, opened_at, bounced_at, attempt_count FROM {$table_logs} WHERE campaign_id = %d ORDER BY sent_at DESC LIMIT %d OFFSET %d",
                $campaign_id,
                $limit,
                $offset
            ), ARRAY_A );

            if ( empty( $logs ) ) {
                break;
            }

            foreach ( $logs as $log ) {
                $delivery_status = 'N/A';
                $open_status     = ( ! empty( $log['opened_at'] ) ) ? 'Yes' : 'No';
                $bounce_status   = ( ! empty( $log['bounced_at'] ) ) ? 'Yes' : 'No';

                switch ( $log['status'] ) {
                    case 'sent':
                    case 'opened':
                    case 'bounced':
                        $delivery_status = 'Sent';
                        break;
                    case 'failed':
                        $delivery_status = 'Failed';
                        break;
                    case 'pending':
                    case 'retrying':
                        $delivery_status = 'Pending';
                        break;
                    case 'unsubscribed':
                    case 'skipped_due_to_unsub':
                        $delivery_status = 'Skipped (Unsubscribed)';
                        break;
                    default:
                        $delivery_status = ucfirst( $log['status'] );
                        break;
                }

                fputcsv( $output, array(
                    $log['email'],
                    $delivery_status,
                    $open_status,
                    $bounce_status,
                    $log['sent_at'],
                    $log['opened_at'],
                    $log['bounced_at'],
                    $log['attempt_count']
                ) );
            }

            $offset += $limit;
        }

        fclose( $output );
        exit;
    }

    /**
     * Get list of columns for the report.
     *
     * @return array
     */
    public function get_columns() {
        $columns = array(
            'email'           => __( 'Email Address', 'email-campaign' ),
            'delivery_status' => __( 'Delivery Status', 'email-campaign' ),
            'open_status'     => __( 'Open Status', 'email-campaign' ),
            'bounce_status'   => __( 'Bounce Status', 'email-campaign' ),
            'sent_at'         => __( 'Sent At', 'email-campaign' ),
            'opened_at'       => __( 'Opened At', 'email-campaign' ),
            'bounced_at'      => __( 'Bounced At', 'email-campaign' ),
            'attempt_count'   => __( 'Attempts', 'email-campaign' ),
        );
        return $columns;
    }

    /**
     * Get sortable columns for the report.
     *
     * @return array
     */
    public function get_sortable_columns() {
        $sortable_columns = array(
            'email'           => array( 'email', false ),
            'delivery_status' => array( 'status', false ), // Maps to 'status' in DB
            'sent_at'         => array( 'sent_at', false ),
            'opened_at'       => array( 'opened_at', false ),
            'bounced_at'      => array( 'bounced_at', false ),
            'attempt_count'   => array( 'attempt_count', false ),
        );
        return $sortable_columns;
    }

    /**
     * Default column renderer for the report.
     *
     * @param object $item
     * @param string $column_name
     * @return string
     */
    public function column_default( $item, $column_name ) {
        global $wpdb;
        $table_contacts = $wpdb->prefix . 'email_contacts';

        switch ( $column_name ) {
            case 'email':
                $contact_status = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$table_contacts} WHERE email = %s", $item->email ) );
                $email_html = esc_html( $item->email );
                if ( $contact_status ) {
                    $email_html .= sprintf( '<br><small>(Contact Status: %s)</small>', esc_html( ec_pro_get_log_status_label( $contact_status ) ) );
                }
                return $email_html;
            case 'delivery_status':
                switch ( $item->status ) {
                    case 'sent':
                    case 'opened':
                    case 'bounced':
                        return esc_html__( 'Sent', 'email-campaign' );
                    case 'failed':
                        return esc_html__( 'Failed', 'email-campaign' );
                    case 'pending':
                    case 'retrying':
                        return esc_html__( 'Pending', 'email-campaign' );
                    case 'unsubscribed':
                    case 'skipped_due_to_unsub':
                        return esc_html__( 'Skipped (Unsubscribed)', 'email-campaign' );
                    default:
                        return ucfirst( esc_html( $item->status ) );
                }
            case 'open_status':
                return ! empty( $item->opened_at ) ? esc_html__( 'Yes', 'email-campaign' ) : esc_html__( 'No', 'email-campaign' );
            case 'bounce_status':
                return ! empty( $item->bounced_at ) ? esc_html__( 'Yes', 'email-campaign' ) : esc_html__( 'No', 'email-campaign' );
            case 'sent_at':
            case 'opened_at':
            case 'bounced_at':
                return ! empty( $item->$column_name ) ? esc_html( $item->$column_name ) : esc_html__( 'N/A', 'email-campaign' );
            case 'attempt_count':
                return intval( $item->attempt_count );
            default:
                return print_r( $item, true ); // For debugging.
        }
    }

    /**
     * Prepares the list of items for displaying in the report.
     */
    public function prepare_items() {
        global $wpdb;
        $table_logs = $wpdb->prefix . 'email_campaigns_logs';

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
        $this->_column_headers = array( $columns, $hidden, $sortable );

        $per_page = $this->get_items_per_page( 'email_campaign_report_per_page', 20 );
        $current_page = $this->get_pagenum();
        $offset = ( $current_page - 1 ) * $per_page;

        $search_query = '';
        $where_clause = $wpdb->prepare( " WHERE campaign_id = %d", $this->campaign_id );
        if ( isset( $_REQUEST['s'] ) && ! empty( $_REQUEST['s'] ) ) {
            $search_query = esc_sql( $wpdb->esc_like( trim( $_REQUEST['s'] ) ) );
            $where_clause .= $wpdb->prepare( " AND email LIKE %s", '%' . $search_query . '%' );
        }

        $order_by = ( isset( $_REQUEST['orderby'] ) && in_array( $_REQUEST['orderby'], array_keys( $this->get_sortable_columns() ) ) ) ? esc_sql( $_REQUEST['orderby'] ) : 'sent_at';
        $order = ( isset( $_REQUEST['order'] ) && in_array( $_REQUEST['order'], array( 'asc', 'desc' ) ) ) ? esc_sql( $_REQUEST['order'] ) : 'desc';

        $total_items = $wpdb->get_var( "SELECT COUNT(id) FROM {$table_logs} {$where_clause}" );
        $this->items = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table_logs} {$where_clause} ORDER BY {$order_by} {$order} LIMIT %d OFFSET %d",
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