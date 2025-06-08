<?php
namespace EC;

defined( 'ABSPATH' ) || exit;

class CPT_Email_Campaign {
    public static function init() {
        add_action( 'init', [ __CLASS__, 'register_cpt' ] );
        add_action( 'add_meta_boxes', [ __CLASS__, 'add_meta_boxes' ] );
        add_action( 'save_post', [ __CLASS__, 'save_meta' ], 10, 2 );
        add_filter( 'manage_email_campaign_posts_columns', [ __CLASS__, 'columns' ] );
        add_action( 'manage_email_campaign_posts_custom_column', [ __CLASS__, 'column_content' ], 10, 2 );

        add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( $hook !== 'post.php' && $hook !== 'post-new.php' ) {
        return;
    }
    if ( get_current_screen()->post_type !== 'email_campaign' ) {
        return;
    }
    wp_enqueue_script(
        'ec-admin',
        EC_PLUGIN_URL . 'assets/js/admin.js',
        [ 'jquery' ],
        EC_PLUGIN_VERSION,
        true
    );
    wp_localize_script( 'ec-admin', 'EC_Ajax', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'ec_upload' ),
    ] );
} );

    }

    public static function register_cpt() {
        register_post_type( 'email_campaign', [
            'labels' => [
                'name'          => 'Email Campaigns',
                'singular_name' => 'Email Campaign',
            ],
            'public'       => false,
            'show_ui'      => true,
            'menu_icon'    => 'dashicons-email',
            'supports'     => [ 'title', 'editor' ],
            'capability_type' => 'post',
        ] );
    }

    public static function add_meta_boxes() {
        add_meta_box(
            'ec_campaign_details',
            'Campaign Details',
            [ __CLASS__, 'render_meta_box' ],
            'email_campaign',
            'normal',
            'high'
        );
    }

    public static function render_meta_box( $post ) {
        wp_nonce_field( 'ec_campaign_meta', 'ec_campaign_meta_nonce' );
        $subject = get_post_meta( $post->ID, '_ec_subject', true );
        $preheader = get_post_meta( $post->ID, '_ec_preheader', true );
        ?>
        <p>
            <label>Subject:</label><br/>
            <input type="text" name="ec_subject" value="<?php echo esc_attr( $subject ); ?>" class="widefat"/>
        </p>
        <p>
            <label>Pre‑header:</label><br/>
            <input type="text" name="ec_preheader" value="<?php echo esc_attr( $preheader ); ?>" class="widefat"/>
        </p>
       <p>
    <label>Upload CSV/XLSX (Email, First Name):</label><br/>
    <input type="file" id="ec_contacts_file" accept=".csv, .xlsx"/>
    <button type="button" class="button" id="ec_upload_btn">Upload &amp; Import</button>
</p>
<div id="ec_upload_result"></div>

        <?php
    }

    public static function save_meta( $post_id, $post ) {
        if ( $post->post_type !== 'email_campaign' ) {
            return;
        }
        if ( ! isset( $_POST['ec_campaign_meta_nonce'] ) || ! wp_verify_nonce( $_POST['ec_campaign_meta_nonce'], 'ec_campaign_meta' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        update_post_meta( $post_id, '_ec_subject', sanitize_text_field( $_POST['ec_subject'] ?? '' ) );
        update_post_meta( $post_id, '_ec_preheader', sanitize_text_field( $_POST['ec_preheader'] ?? '' ) );

        // handle file upload
        if ( ! empty( $_FILES['ec_contacts_file']['tmp_name'] ) ) {
            $handler = new Subscriber_Handler();
            $handler->import_file( $post_id, $_FILES['ec_contacts_file'] );
        }

        // When post is published the first time schedule emails
        if ( $post->post_status === 'publish' && $post->post_date_gmt === $post->post_modified_gmt ) {
            Scheduler::queue_campaign( $post_id );
        }
    }

    public static function columns( $cols ) {
        $cols['ec_status'] = 'Status';
        $cols['ec_report'] = 'Report';
        return $cols;
    }

    public static function column_content( $col, $post_id ) {
        if ( $col === 'ec_status' ) {
            echo esc_html( Scheduler::campaign_progress_text( $post_id ) );
        }
        if ( $col === 'ec_report' ) {
            echo '<a href="' . esc_url( admin_url( 'admin.php?page=ec_campaign_report&post_id=' . $post_id ) ) . '">View</a>';
        }
    }
}
