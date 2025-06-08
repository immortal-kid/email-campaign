<?php
/**
 * Custom Post Type for Email Campaigns.
 *
 * @package Email_Campaign_Pro
 * @subpackage Includes
 */

namespace EmailCampaign;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CPT_Email_Campaign {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'init', array( $this, 'register_cpt' ) );
        add_action( 'add_meta_boxes', array( $this, 'add_campaign_meta_boxes' ) );
        add_action( 'save_post_email_campaign', array( $this, 'save_campaign_meta' ) );
        add_filter( 'post_updated_messages', array( $this, 'updated_messages' ) );
        add_filter( 'manage_email_campaign_posts_columns', array( $this, 'set_custom_columns' ) );
        add_action( 'manage_email_campaign_posts_custom_column', array( $this, 'render_custom_columns' ), 10, 2 );
        add_filter( 'display_post_states', array( $this, 'add_campaign_state' ), 10, 2 );
        add_action( 'admin_footer-post.php', array( $this, 'add_publish_confirmation_script' ) );
        add_action( 'admin_footer-post-new.php', array( $this, 'add_publish_confirmation_script' ) );

        // Add pause/resume/cancel actions
        add_action( 'admin_init', array( $this, 'handle_campaign_actions' ) );
    }

    /**
     * Register the Email Campaign CPT.
     */
    public function register_cpt() {
        $labels = array(
            'name'                  => _x( 'Email Campaigns', 'Post Type General Name', 'email-campaign' ),
            'singular_name'         => _x( 'Email Campaign', 'Post Type Singular Name', 'email-campaign' ),
            'menu_name'             => __( 'Email Campaigns', 'email-campaign' ),
            'name_admin_bar'        => __( 'Email Campaign', 'email-campaign' ),
            'archives'              => __( 'Email Campaign Archives', 'email-campaign' ),
            'attributes'            => __( 'Email Campaign Attributes', 'email-campaign' ),
            'parent_item_colon'     => __( 'Parent Email Campaign:', 'email-campaign' ),
            'all_items'             => __( 'All Campaigns', 'email-campaign' ),
            'add_new_item'          => __( 'Add New Campaign', 'email-campaign' ),
            'add_new'               => __( 'Add New', 'email-campaign' ),
            'new_item'              => __( 'New Campaign', 'email-campaign' ),
            'edit_item'             => __( 'Edit Campaign', 'email-campaign' ),
            'update_item'           => __( 'Update Campaign', 'email-campaign' ),
            'view_item'             => __( 'View Campaign', 'email-campaign' ),
            'view_items'            => __( 'View Campaigns', 'email-campaign' ),
            'search_items'          => __( 'Search Campaign', 'email-campaign' ),
            'not_found'             => __( 'Not found', 'email-campaign' ),
            'not_found_in_trash'    => __( 'Not found in Trash', 'email-campaign' ),
            'featured_image'        => __( 'Campaign Cover Image', 'email-campaign' ),
            'set_featured_image'    => __( 'Set cover image', 'email-campaign' ),
            'remove_featured_image' => __( 'Remove cover image', 'email-campaign' ),
            'use_featured_image'    => __( 'Use as cover image', 'email-campaign' ),
            'insert_into_item'      => __( 'Insert into campaign', 'email-campaign' ),
            'uploaded_to_this_item' => __( 'Uploaded to this campaign', 'email-campaign' ),
            'items_list'            => __( 'Campaigns list', 'email-campaign' ),
            'items_list_navigation' => __( 'Campaigns list navigation', 'email-campaign' ),
            'filter_items_list'     => __( 'Filter campaigns list', 'email-campaign' ),
        );
        $args = array(
            'label'                 => __( 'Email Campaign', 'email-campaign' ),
            'description'           => __( 'Post Type for managing email campaigns', 'email-campaign' ),
            'labels'                => $labels,
            'supports'              => array( 'title', 'editor' ),
            'hierarchical'          => false,
            'public'                => false, // Set to false, as it's an admin-only CPT.
            'show_ui'               => true,
            'show_in_menu'          => true,
            'menu_position'         => 20, // After Posts
            'menu_icon'             => 'dashicons-email-alt',
            'show_in_admin_bar'     => true,
            'show_in_nav_menus'     => true,
            'can_export'            => true,
            'has_archive'           => false,
            'exclude_from_search'   => true,
            'publicly_queryable'    => false,
            'capability_type'       => 'post',
            'map_meta_cap'          => true,
            'show_in_rest'          => true, // Enable for Gutenberg editor.
            'rest_base'             => 'email_campaigns',
        );
        register_post_type( 'email_campaign', $args );
    }

    /**
     * Add custom meta boxes for Email Campaign CPT.
     */
    public function add_campaign_meta_boxes() {
        add_meta_box(
            'ec_pro_campaign_settings',
            __( 'Campaign Settings', 'email-campaign' ),
            array( $this, 'render_campaign_settings_meta_box' ),
            'email_campaign',
            'normal',
            'high'
        );
        add_meta_box(
            'ec_pro_campaign_list_upload',
            __( 'Recipient List', 'email-campaign' ),
            array( $this, 'render_campaign_list_upload_meta_box' ),
            'email_campaign',
            'normal',
            'high'
        );
        add_meta_box(
            'ec_pro_campaign_status',
            __( 'Campaign Status', 'email-campaign' ),
            array( $this, 'render_campaign_status_meta_box' ),
            'email_campaign',
            'side',
            'high'
        );
    }

    /**
     * Render the Campaign Settings meta box.
     *
     * @param WP_Post $post The post object.
     */
    public function render_campaign_settings_meta_box( $post ) {
        wp_nonce_field( 'ec_pro_save_campaign_meta', 'ec_pro_campaign_meta_nonce' );

        $email_subject = get_post_meta( $post->ID, '_ec_pro_email_subject', true );
        $email_preheader = get_post_meta( $post->ID, '_ec_pro_email_preheader', true );
        ?>
        <p>
            <label for="ec_pro_email_subject"><?php esc_html_e( 'Email Subject:', 'email-campaign' ); ?></label>
            <br>
            <input type="text" id="ec_pro_email_subject" name="ec_pro_email_subject" value="<?php echo esc_attr( $email_subject ); ?>" class="large-text" />
        </p>
        <p>
            <label for="ec_pro_email_preheader"><?php esc_html_e( 'Email Pre-Header:', 'email-campaign' ); ?></label>
            <br>
            <input type="text" id="ec_pro_email_preheader" name="ec_pro_email_preheader" value="<?php echo esc_attr( $email_preheader ); ?>" class="large-text" />
            <br>
            <small><?php esc_html_e( 'A short summary text that follows the subject line when an email is opened. Max 100 characters.', 'email-campaign' ); ?></small>
        </p>
        <?php
    }

    /**
     * Render the Recipient List Upload meta box.
     *
     * @param WP_Post $post The post object.
     */
    public function render_campaign_list_upload_meta_box( $post ) {
        $last_upload_info = get_post_meta( $post->ID, '_ec_pro_last_upload_info', true );
        $total_subscribers = get_post_meta( $post->ID, '_ec_pro_total_subscribers', true );
        ?>
        <p>
            <label for="ec_pro_excel_csv_file"><?php esc_html_e( 'Upload Excel/CSV File:', 'email-campaign' ); ?></label>
            <br>
            <input type="file" id="ec_pro_excel_csv_file" name="ec_pro_excel_csv_file" accept=".xlsx,.csv" />
            <p class="description"><?php esc_html_e( 'Upload an Excel (.xlsx) or CSV (.csv) file. Column A for Email (required), Column B for First Name (optional).', 'email-campaign' ); ?></p>
        </p>

        <?php if ( $last_upload_info ) : ?>
            <div class="ec-pro-upload-feedback">
                <h4><?php esc_html_e( 'Last Upload Summary:', 'email-campaign' ); ?></h4>
                <p><strong><?php esc_html_e( 'Valid Emails:', 'email-campaign' ); ?></strong> <?php echo esc_html( $last_upload_info['valid'] ); ?></p>
                <p><strong><?php esc_html_e( 'Invalid Emails:', 'email-campaign' ); ?></strong> <?php echo esc_html( $last_upload_info['invalid'] ); ?></p>
                <p><strong><?php esc_html_e( 'Duplicates Skipped:', 'email-campaign' ); ?></strong> <?php echo esc_html( $last_upload_info['duplicates'] ); ?></p>
                <p><strong><?php esc_html_e( 'Total Subscribers in Campaign:', 'email-campaign' ); ?></strong> <?php echo esc_html( $total_subscribers ); ?></p>
            </div>
        <?php endif; ?>

        <p>
            <label for="ec_pro_select_contacts"><?php esc_html_e( 'Or select existing contacts:', 'email-campaign' ); ?></label>
            <br>
            <a href="#" class="button" id="ec_pro_select_existing_contacts"><?php esc_html_e( 'Select Contacts from Database', 'email-campaign' ); ?></a>
            <p class="description"><?php esc_html_e( 'Note: Selecting contacts will replace any previously uploaded or selected lists for this campaign.', 'email-campaign' ); ?></p>
        </p>
        <div id="ec-pro-existing-contacts-selector" style="display:none;">
            <p><?php esc_html_e( 'To select contacts, please go to the Contacts page and export/import, or manually add.', 'email-campaign' ); ?></p>
            <p class="description"><?php esc_html_e( 'Direct selection of contacts within the meta box is a more complex feature requiring a custom modal/picker. For now, please manage via the "Contacts" menu.', 'email-campaign' ); ?></p>
        </div>
        <?php
    }

    /**
     * Render the Campaign Status meta box.
     *
     * @param WP_Post $post The post object.
     */
    public function render_campaign_status_meta_box( $post ) {
        $campaign_status = get_post_meta( $post->ID, '_ec_pro_campaign_status', true );
        if ( empty( $campaign_status ) ) {
            $campaign_status = 'draft';
        }

        $total_subscribers = get_post_meta( $post->ID, '_ec_pro_total_subscribers', true );
        if ( ! $total_subscribers ) {
            $total_subscribers = 0;
        }

        global $wpdb;
        $table_subscribers = $wpdb->prefix . 'email_campaigns_subscribers';
        $sent_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_subscribers} WHERE campaign_id = %d AND status = 'sent'", $post->ID ) );
        if ( ! $sent_count ) {
            $sent_count = 0;
        }

        $in_progress_message = '';
        if ( 'in_progress' === $campaign_status ) {
            $in_progress_message = sprintf( esc_html__( 'Currently sending: %d/%d emails sent.', 'email-campaign' ), $sent_count, $total_subscribers );
        }

        ?>
        <div id="ec-pro-campaign-status-display">
            <p><strong><?php esc_html_e( 'Current Status:', 'email-campaign' ); ?></strong> <span id="ec-pro-status-label"><?php echo esc_html( ec_pro_get_log_status_label( $campaign_status ) ); ?></span></p>
            <?php if ( 'in_progress' === $campaign_status ) : ?>
                <p id="ec-pro-progress-message"><?php echo esc_html( $in_progress_message ); ?></p>
            <?php else : ?>
                <p id="ec-pro-progress-message"><?php printf( esc_html__( '%d total emails in list.', 'email-campaign' ), $total_subscribers ); ?></p>
            <?php endif; ?>

            <?php if ( 'in_progress' === $campaign_status ) : ?>
                <p><button type="button" class="button ec-pro-campaign-action-btn" data-action="pause" data-post-id="<?php echo intval( $post->ID ); ?>"><?php esc_html_e( 'Pause Campaign', 'email-campaign' ); ?></button></p>
            <?php elseif ( 'paused' === $campaign_status ) : ?>
                <p><button type="button" class="button ec-pro-campaign-action-btn" data-action="resume" data-post-id="<?php echo intval( $post->ID ); ?>"><?php esc_html_e( 'Resume Campaign', 'email-campaign' ); ?></button></p>
            <?php endif; ?>
            <?php if ( 'draft' !== $campaign_status && 'completed' !== $campaign_status && 'cancelled' !== $campaign_status ) : ?>
                <p><button type="button" class="button button-secondary ec-pro-campaign-action-btn" data-action="cancel" data-post-id="<?php echo intval( $post->ID ); ?>"><?php esc_html_e( 'Cancel Campaign', 'email-campaign' ); ?></button></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Save campaign meta data.
     *
     * @param int $post_id The post ID.
     */
    public function save_campaign_meta( $post_id ) {
        // Check if our nonce is set.
        if ( ! isset( $_POST['ec_pro_campaign_meta_nonce'] ) ) {
            return $post_id;
        }

        // Verify that the nonce is valid.
        if ( ! wp_verify_nonce( $_POST['ec_pro_campaign_meta_nonce'], 'ec_pro_save_campaign_meta' ) ) {
            return $post_id;
        }

        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return $post_id;
        }

        // Check the user's permissions.
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return $post_id;
        }

        // Save Email Subject.
        if ( isset( $_POST['ec_pro_email_subject'] ) ) {
            update_post_meta( $post_id, '_ec_pro_email_subject', ec_pro_sanitize_text( $_POST['ec_pro_email_subject'] ) );
        }

        // Save Email Pre-Header.
        if ( isset( $_POST['ec_pro_email_preheader'] ) ) {
            update_post_meta( $post_id, '_ec_pro_email_preheader', ec_pro_sanitize_text( $_POST['ec_pro_email_preheader'] ) );
        }

        // Handle file upload.
        if ( isset( $_FILES['ec_pro_excel_csv_file'] ) && ! empty( $_FILES['ec_pro_excel_csv_file']['name'] ) ) {
            $file = $_FILES['ec_pro_excel_csv_file'];

            // Check for upload errors.
            if ( $file['error'] !== UPLOAD_ERR_OK ) {
                error_log( 'Email Campaign Pro: File upload error - ' . $file['error'] );
                // You might want to display an admin notice here.
                return $post_id;
            }

            $file_type = wp_check_filetype( $file['name'], array(
                'csv'  => 'text/csv',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ) );

            if ( ! in_array( $file_type['type'], array( 'text/csv', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' ) ) ) {
                error_log( 'Email Campaign Pro: Invalid file type uploaded.' );
                // Admin notice for invalid file type.
                return $post_id;
            }

            // Move the uploaded file to a temporary location.
            $upload_dir   = wp_upload_dir();
            $temp_dir     = $upload_dir['basedir'] . '/ec-pro-temp/';
            if ( ! is_dir( $temp_dir ) ) {
                wp_mkdir_p( $temp_dir );
            }
            $temp_file_path = $temp_dir . wp_unique_filename( $temp_dir, $file['name'] );

            if ( move_uploaded_file( $file['tmp_name'], $temp_file_path ) ) {
                // Parse the file and store subscribers.
                $subscriber_handler = new Subscriber_Handler();
                $upload_result = $subscriber_handler->parse_and_store_subscribers( $post_id, $temp_file_path );

                if ( is_array( $upload_result ) ) {
                    update_post_meta( $post_id, '_ec_pro_last_upload_info', $upload_result );
                    update_post_meta( $post_id, '_ec_pro_total_subscribers', $upload_result['valid'] );
                } else {
                    error_log( 'Email Campaign Pro: Error parsing or storing subscribers from file.' );
                    // Admin notice for parsing error.
                }

                // Delete the temporary file.
                unlink( $temp_file_path );
            } else {
                error_log( 'Email Campaign Pro: Failed to move uploaded file.' );
                // Admin notice for file move error.
            }
        } // End file upload handling

        // If the post is published for the first time, schedule emails.
        $old_status = get_post_meta( $post_id, '_ec_pro_campaign_status', true );
        $new_status = get_post_status( $post_id );

        // Only act if going from draft/pending to publish/future and no prior status or not 'in_progress'
        if ( ( 'publish' === $new_status || 'future' === $new_status ) && ( empty( $old_status ) || 'draft' === $old_status || 'paused' === $old_status ) ) {
            if ( ! wp_is_post_revision( $post_id ) ) {
                 // Update campaign status meta immediately to 'in_progress'
                update_post_meta( $post_id, '_ec_pro_campaign_status', 'in_progress' );

                // Schedule the campaign start with Action Scheduler
                // We add a slight delay to allow meta to save fully.
                if ( class_exists( '\ActionScheduler_Store' ) ) {
                    $scheduler = new Scheduler();
                    $scheduler->schedule_campaign_start( $post_id, time() + 5 ); // Start 5 seconds from now
                    // The actual email scheduling is done within the scheduled action.
                } else {
                    error_log( 'Email Campaign Pro: Action Scheduler not found. Cannot schedule campaign.' );
                    // Admin notice.
                }
            }
        }
    }

    /**
     * Customize CPT updated messages.
     *
     * @param array $messages Array of messages.
     * @return array Modified array of messages.
     */
    public function updated_messages( $messages ) {
        global $post;
        $post_ID = $post->ID;

        $messages['email_campaign'] = array(
            0  => '', // Unused. Messages start at index 1.
            1  => sprintf( __( 'Email campaign updated. <a href="%s">View campaign</a>', 'email-campaign' ), esc_url( get_permalink( $post_ID ) ) ),
            2  => __( 'Custom field updated.', 'email-campaign' ),
            3  => __( 'Custom field deleted.', 'email-campaign' ),
            4  => __( 'Email campaign updated.', 'email-campaign' ),
            /* translators: %s: date and time of the revision */
            5  => isset( $_GET['revision'] ) ? sprintf( __( 'Email campaign restored to revision from %s', 'email-campaign' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
            6  => sprintf( __( 'Email campaign published. <a href="%s">View campaign</a>', 'email-campaign' ), esc_url( get_permalink( $post_ID ) ) ),
            7  => __( 'Email campaign saved.', 'email-campaign' ),
            8  => sprintf( __( 'Email campaign submitted. <a target="_blank" href="%s">Preview campaign</a>', 'email-campaign' ), esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_ID ) ) ) ),
            9  => sprintf( __( 'Email campaign scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview campaign</a>', 'email-campaign' ),
                date_i18n( __( 'M j, Y @ H:i', 'email-campaign' ), strtotime( $post->post_date ) ), esc_url( get_permalink( $post_ID ) ) ),
            10 => sprintf( __( 'Email campaign draft updated. <a target="_blank" href="%s">Preview campaign</a>', 'email-campaign' ), esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_ID ) ) ) ),
        );

        return $messages;
    }

    /**
     * Set custom columns for the CPT list table.
     *
     * @param array $columns Existing columns.
     * @return array Modified columns.
     */
    public function set_custom_columns( $columns ) {
        $new_columns = array();
        foreach ( $columns as $key => $value ) {
            $new_columns[ $key ] = $value;
            if ( 'title' === $key ) {
                $new_columns['ec_pro_status'] = __( 'Status', 'email-campaign' );
                $new_columns['ec_pro_progress'] = __( 'Progress', 'email-campaign' );
            }
        }
        $new_columns['ec_pro_report'] = __( 'Report', 'email-campaign' );
        $new_columns['ec_pro_actions'] = __( 'Actions', 'email-campaign' );
        return $new_columns;
    }

    /**
     * Render custom columns content.
     *
     * @param string $column_name The name of the column.
     * @param int    $post_id     The post ID.
     */
    public function render_custom_columns( $column_name, $post_id ) {
        global $wpdb;
        $table_subscribers = $wpdb->prefix . 'email_campaigns_subscribers';
        $campaign_status = get_post_meta( $post_id, '_ec_pro_campaign_status', true );
        $total_subscribers = get_post_meta( $post_id, '_ec_pro_total_subscribers', true );

        if ( empty( $campaign_status ) ) {
            $campaign_status = 'draft';
        }
        if ( ! $total_subscribers ) {
            $total_subscribers = 0;
        }

        switch ( $column_name ) {
            case 'ec_pro_status':
                echo esc_html( ec_pro_get_log_status_label( $campaign_status ) );
                break;
            case 'ec_pro_progress':
                $sent_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_subscribers} WHERE campaign_id = %d AND status = 'sent'", $post_id ) );
                if ( ! $sent_count ) {
                    $sent_count = 0;
                }
                echo sprintf( '%d/%d', $sent_count, $total_subscribers );
                break;
            case 'ec_pro_report':
                if ( $total_subscribers > 0 ) {
                    echo '<a href="' . esc_url( admin_url( 'admin.php?page=email_campaign_report&post_id=' . $post_id ) ) . '">' . esc_html__( 'View Report', 'email-campaign' ) . '</a>';
                } else {
                    echo esc_html__( 'N/A', 'email-campaign' );
                }
                break;
            case 'ec_pro_actions':
                if ( 'in_progress' === $campaign_status ) {
                    echo '<button type="button" class="button button-small ec-pro-campaign-action-btn" data-action="pause" data-post-id="' . intval( $post_id ) . '">' . esc_html__( 'Pause', 'email-campaign' ) . '</button>';
                } elseif ( 'paused' === $campaign_status ) {
                    echo '<button type="button" class="button button-small ec-pro-campaign-action-btn" data-action="resume" data-post-id="' . intval( $post_id ) . '">' . esc_html__( 'Resume', 'email-campaign' ) . '</button>';
                }
                if ( 'draft' !== $campaign_status && 'completed' !== $campaign_status && 'cancelled' !== $campaign_status ) {
                     echo ' <button type="button" class="button button-small button-secondary ec-pro-campaign-action-btn" data-action="cancel" data-post-id="' . intval( $post_id ) . '">' . esc_html__( 'Cancel', 'email-campaign' ) . '</button>';
                }
                break;
        }
    }

    /**
     * Add "In Progress", "Paused", "Completed" states to the CPT list table.
     *
     * @param array   $post_states An array of post states to display.
     * @param WP_Post $post The post object.
     * @return array Modified array of post states.
     */
    public function add_campaign_state( $post_states, $post ) {
        if ( 'email_campaign' === $post->post_type ) {
            $campaign_status = get_post_meta( $post->ID, '_ec_pro_campaign_status', true );
            if ( $campaign_status && in_array( $campaign_status, array( 'in_progress', 'paused', 'completed', 'cancelled' ) ) ) {
                $post_states[ 'ec_pro_status_' . $campaign_status ] = ec_pro_get_log_status_label( $campaign_status );
            }
        }
        return $post_states;
    }

    /**
     * Add JavaScript for publish button confirmation.
     */
    public function add_publish_confirmation_script() {
        global $post_type;

        if ( 'email_campaign' === $post_type ) :
            ?>
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    var publishButton = $('#publish');
                    var originalText = publishButton.val();
                    var postId = $('#post_ID').val();
                    var currentPostStatus = $('[name="original_post_status"]').val(); // 'draft', 'publish' etc.

                    // Check if this is a new post or an existing draft being published for the first time
                    // Or if a 'paused' campaign is being republished/resumed (post status might still be 'publish')
                    var campaignStatus = $('[name="_ec_pro_campaign_status"]').val(); // Custom meta field for internal status

                    if (publishButton.length) {
                        publishButton.on('click', function(e) {
                            // Check if current post status is 'draft' or 'auto-draft' AND target status is 'publish'
                            // Or if internal campaign status is 'paused' and we're trying to publish/update
                            var targetStatus = $('[name="post_status"]').val(); // 'publish', 'draft' etc.
                            var isNewPublish = (currentPostStatus === 'draft' || currentPostStatus === 'auto-draft') && targetStatus === 'publish';
                            var isResumingPaused = (campaignStatus === 'paused' && targetStatus === 'publish'); // Assuming 'publish' is the post status for active campaigns too

                            if ( isNewPublish || isResumingPaused ) {
                                if ( ! confirm(ecProAdminVars.confirm_publish) ) {
                                    e.preventDefault();
                                    return false;
                                }
                            }
                        });
                    }
                });
            </script>
            <?php
        endif;
    }

    /**
     * Handle campaign actions (pause, resume, cancel) via AJAX.
     */
    public function handle_campaign_actions() {
        if ( ! wp_doing_ajax() ) {
            return;
        }

        $action = isset( $_POST['action'] ) ? sanitize_text_field( $_POST['action'] ) : '';
        if ( ! in_array( $action, array( 'ec_pro_pause_campaign', 'ec_pro_resume_campaign', 'ec_pro_cancel_campaign' ) ) ) {
            return;
        }

        check_ajax_referer( 'ec_pro_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'email-campaign' ) ) );
        }

        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        if ( ! $post_id || get_post_type( $post_id ) !== 'email_campaign' ) {
            wp_send_json_error( array( 'message' => __( 'Invalid campaign ID.', 'email-campaign' ) ) );
        }

        $scheduler = new Scheduler();
        $success = false;
        $new_status = '';

        switch ( $action ) {
            case 'ec_pro_pause_campaign':
                $success = $scheduler->pause_campaign( $post_id );
                $new_status = 'paused';
                break;
            case 'ec_pro_resume_campaign':
                $success = $scheduler->resume_campaign( $post_id );
                $new_status = 'in_progress';
                break;
            case 'ec_pro_cancel_campaign':
                $success = $scheduler->cancel_campaign( $post_id );
                $new_status = 'cancelled';
                break;
        }

        if ( $success ) {
            wp_send_json_success( array(
                'message'   => __( 'Campaign action successful.', 'email-campaign' ),
                'new_status' => $new_status,
                'status_label' => ec_pro_get_log_status_label( $new_status )
            ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Campaign action failed.', 'email-campaign' ) ) );
        }
    }
}