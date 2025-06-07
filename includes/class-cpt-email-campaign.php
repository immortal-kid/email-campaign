<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class EC_CPT_Email_Campaign {

    const POST_TYPE = 'email_campaign';

    public function __construct() {
        add_action( 'init', [ $this, 'register_cpt' ] );
        add_action( 'add_meta_boxes', [ $this, 'add_meta_boxes' ] );
        add_action( 'save_post_' . self::POST_TYPE, [ $this, 'save_meta' ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
    }

    public function register_cpt() {
        $labels = [
            'name'               => __( 'Email Campaigns', 'email-campaign' ),
            'singular_name'      => __( 'Email Campaign',  'email-campaign' ),
            'add_new_item'       => __( 'Add New Campaign', 'email-campaign' ),
            'edit_item'          => __( 'Edit Campaign',    'email-campaign' ),
            'all_items'          => __( 'All Campaigns',    'email-campaign' ),
        ];
        $args = [
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'menu_icon'          => 'dashicons-email',
            'supports'           => [ 'title', 'editor', 'custom-fields' ],
            'capability_type'    => 'post',
            'capabilities'       => [ 'create_posts' => 'manage_options' ],
            'map_meta_cap'       => true,
        ];
        register_post_type( self::POST_TYPE, $args );
    }

    public function add_meta_boxes() {
        add_meta_box(
            'ec_campaign_settings',
            __( 'Campaign Settings', 'email-campaign' ),
            [ $this, 'render_meta_box' ],
            self::POST_TYPE,
            'side',
            'high'
        );
    }

    public function render_meta_box( $post ) {
        wp_nonce_field( 'ec_save_campaign_meta', 'ec_campaign_meta_nonce' );
        $subject    = get_post_meta( $post->ID, '_ec_subject', true );
        $preheader  = get_post_meta( $post->ID, '_ec_preheader', true );
        $upload_id  = get_post_meta( $post->ID, '_ec_upload_id', true );
        ?>
        <p>
            <label for="ec_subject"><?php esc_html_e( 'Email Subject', 'email-campaign' ); ?></label>
            <input type="text" id="ec_subject" name="ec_subject" class="widefat" value="<?php echo esc_attr( $subject ); ?>">
        </p>
        <p>
            <label for="ec_preheader"><?php esc_html_e( 'Email Pre-Header', 'email-campaign' ); ?></label>
            <input type="text" id="ec_preheader" name="ec_preheader" class="widefat" value="<?php echo esc_attr( $preheader ); ?>">
        </p>
        <p>
            <label for="ec_upload"><?php esc_html_e( 'Upload .csv/.xlsx', 'email-campaign' ); ?></label><br>
            <input type="file" id="ec_upload" name="ec_upload" accept=".csv, .xlsx">
            <?php if ( $upload_id ) : ?>
                <br><a href="<?php echo esc_url( wp_get_attachment_url( $upload_id ) ); ?>" target="_blank"><?php esc_html_e( 'View current file', 'email-campaign' ); ?></a>
            <?php endif; ?>
        </p>
        <?php
        // Optionally: Select existing contacts (loaded via AJAX in admin.js)
        ?>
        <p>
            <label for="ec_select_contacts"><?php esc_html_e( 'Or select existing contacts', 'email-campaign' ); ?></label>
            <select id="ec_select_contacts" name="ec_select_contacts[]" multiple class="widefat">
                <!-- Populated dynamically -->
            </select>
        </p>
        <?php
    }

    public function save_meta( $post_id, $post ) {
        if ( ! isset( $_POST['ec_campaign_meta_nonce'] ) ||
             ! wp_verify_nonce( $_POST['ec_campaign_meta_nonce'], 'ec_save_campaign_meta' ) ) {
            return;
        }

        // Subject
        if ( isset( $_POST['ec_subject'] ) ) {
            update_post_meta( $post_id, '_ec_subject', sanitize_text_field( wp_unslash( $_POST['ec_subject'] ) ) );
        }

        // Preheader
        if ( isset( $_POST['ec_preheader'] ) ) {
            update_post_meta( $post_id, '_ec_preheader', sanitize_text_field( wp_unslash( $_POST['ec_preheader'] ) ) );
        }

        // File upload
        if ( ! empty( $_FILES['ec_upload']['name'] ) ) {
            $uploaded = media_handle_upload( 'ec_upload', $post_id );
            if ( is_wp_error( $uploaded ) ) {
                error_log( 'EC Upload error: ' . $uploaded->get_error_message() );
            } else {
                update_post_meta( $post_id, '_ec_upload_id', intval( $uploaded ) );
            }
        }

        // Selected contacts
        if ( isset( $_POST['ec_select_contacts'] ) && is_array( $_POST['ec_select_contacts'] ) ) {
            $contact_ids = array_map( 'intval', $_POST['ec_select_contacts'] );
            update_post_meta( $post_id, '_ec_selected_contacts', $contact_ids );
        }
    }

    public function enqueue_admin_assets( $hook ) {
        if ( in_array( $hook, [ 'post-new.php', 'post.php' ], true ) &&
             get_post_type() === self::POST_TYPE ) {
            wp_enqueue_script( 'ec-admin-js', plugin_dir_url( __DIR__ ) . '../assets/js/admin.js', [ 'jquery' ], '1.0', true );
            wp_enqueue_style( 'ec-admin-css', plugin_dir_url( __DIR__ ) . '../assets/css/admin.css', [], '1.0' );
        }
    }
}

new EC_CPT_Email_Campaign();
