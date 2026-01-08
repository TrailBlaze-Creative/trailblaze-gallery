<?php
/**
 * Migration script for importing data from Photo Gallery by 10Web (BWG)
 * Uses AJAX batch processing to avoid timeouts
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Migration class for BWG to Trailblaze Gallery
 */
class TBG_Migration {

    const BATCH_SIZE = 20; // Images per batch

    /**
     * Initialize migration hooks
     */
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'add_migration_page'));
        add_action('wp_ajax_tbg_migrate_batch', array(__CLASS__, 'ajax_migrate_batch'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_migration_scripts'));
    }

    /**
     * Enqueue scripts for migration page
     */
    public static function enqueue_migration_scripts($hook) {
        if ($hook !== 'tools_page_tbg-migration') {
            return;
        }

        wp_enqueue_script(
            'tbg-migration',
            TBG_PLUGIN_URL . 'assets/js/migration.js',
            array('jquery'),
            TBG_VERSION,
            true
        );

        wp_localize_script('tbg-migration', 'tbgMigration', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tbg_migrate_nonce'),
            'batchSize' => self::BATCH_SIZE,
        ));
    }

    /**
     * Add migration page to admin menu
     */
    public static function add_migration_page() {
        add_submenu_page(
            'tools.php',
            'Migrate BWG Gallery',
            'Migrate BWG Gallery',
            'manage_options',
            'tbg-migration',
            array(__CLASS__, 'render_migration_page')
        );
    }

    /**
     * Render migration page
     */
    public static function render_migration_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access');
        }

        $galleries = self::get_bwg_galleries();

        ?>
        <div class="wrap">
            <h1>Migrate Photo Gallery by 10Web to Trailblaze Gallery</h1>

            <div id="tbg-migration-notices"></div>

            <?php if (empty($galleries)) : ?>
                <div class="notice notice-warning">
                    <p>No Photo Gallery (BWG) galleries found in the database.</p>
                </div>
            <?php else : ?>

                <h2>Found <?php echo count($galleries); ?> BWG Galleries</h2>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Slug</th>
                            <th>Images</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($galleries as $gallery) : ?>
                            <tr>
                                <td><?php echo esc_html($gallery->id); ?></td>
                                <td><?php echo esc_html($gallery->name); ?></td>
                                <td><?php echo esc_html($gallery->slug); ?></td>
                                <td><?php echo esc_html($gallery->image_count); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <h2 style="margin-top: 30px;">Migrate to Page</h2>
                <form id="tbg-migration-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row">Target Page</th>
                            <td>
                                <?php
                                wp_dropdown_pages(array(
                                    'name' => 'target_page_id',
                                    'id' => 'tbg-target-page',
                                    'show_option_none' => '-- Select a page --',
                                    'option_none_value' => '0',
                                ));
                                ?>
                                <p class="description">Select the page where galleries will be added.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Galleries to Import</th>
                            <td>
                                <?php foreach ($galleries as $gallery) : ?>
                                    <label style="display: block; margin-bottom: 8px;">
                                        <input type="checkbox" name="galleries[]" value="<?php echo esc_attr($gallery->id); ?>"
                                               data-name="<?php echo esc_attr($gallery->name); ?>"
                                               data-count="<?php echo esc_attr($gallery->image_count); ?>" checked>
                                        <?php echo esc_html($gallery->name); ?> (<?php echo esc_html($gallery->image_count); ?> images)
                                    </label>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" id="tbg-start-migration" class="button button-primary">Start Migration</button>
                        <span id="tbg-migration-status" style="margin-left: 15px;"></span>
                    </p>
                </form>

                <!-- Progress UI -->
                <div id="tbg-migration-progress" style="display: none; margin-top: 20px;">
                    <h3>Migration Progress</h3>
                    <div style="background: #f0f0f0; border-radius: 4px; padding: 3px; margin-bottom: 10px;">
                        <div id="tbg-progress-bar" style="background: #2271b1; height: 24px; border-radius: 3px; width: 0%; transition: width 0.3s;"></div>
                    </div>
                    <p id="tbg-progress-text">Preparing...</p>
                    <div id="tbg-progress-log" style="background: #f9f9f9; border: 1px solid #ddd; padding: 10px; max-height: 200px; overflow-y: auto; font-family: monospace; font-size: 12px;"></div>
                </div>

            <?php endif; ?>

            <hr style="margin-top: 30px;">

            <h2>Shortcode Reference</h2>
            <p>After migration, use these shortcodes in your page content:</p>
            <ul>
                <li><code>[tbg_gallery id="gallery-slug"]</code> - Display a specific gallery by its ID</li>
                <li><code>[tbg_gallery gallery_index="0"]</code> - Display gallery by index (0 = first)</li>
            </ul>

        </div>
        <?php
    }

    /**
     * AJAX handler for batch migration
     */
    public static function ajax_migrate_batch() {
        check_ajax_referer('tbg_migrate_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $action_type = isset($_POST['action_type']) ? sanitize_text_field($_POST['action_type']) : '';
        $target_page_id = isset($_POST['target_page_id']) ? intval($_POST['target_page_id']) : 0;

        switch ($action_type) {
            case 'prepare':
                // Get list of all images to process
                $gallery_ids = isset($_POST['gallery_ids']) ? array_map('intval', $_POST['gallery_ids']) : array();
                $tasks = self::prepare_migration_tasks($gallery_ids);
                wp_send_json_success(array(
                    'tasks' => $tasks,
                    'total' => count($tasks),
                ));
                break;

            case 'process_batch':
                // Process a batch of images for a gallery
                $gallery_id = isset($_POST['gallery_id']) ? intval($_POST['gallery_id']) : 0;
                $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
                $result = self::process_image_batch($gallery_id, $offset, self::BATCH_SIZE);
                wp_send_json_success($result);
                break;

            case 'finalize':
                // Save all gallery data to ACF
                $galleries_data = isset($_POST['galleries_data']) ? $_POST['galleries_data'] : array();
                $result = self::finalize_migration($target_page_id, $galleries_data);
                wp_send_json_success(array('success' => $result));
                break;

            default:
                wp_send_json_error('Invalid action type');
        }
    }

    /**
     * Prepare list of migration tasks
     */
    public static function prepare_migration_tasks($gallery_ids) {
        $tasks = array();

        foreach ($gallery_ids as $gallery_id) {
            $gallery = self::get_bwg_gallery_by_id($gallery_id);
            if (!$gallery) continue;

            $image_count = self::get_bwg_image_count($gallery_id);
            $batches = ceil($image_count / self::BATCH_SIZE);

            for ($i = 0; $i < $batches; $i++) {
                $tasks[] = array(
                    'gallery_id' => $gallery_id,
                    'gallery_name' => $gallery->name,
                    'gallery_slug' => $gallery->slug ?: sanitize_title($gallery->name),
                    'offset' => $i * self::BATCH_SIZE,
                    'batch' => $i + 1,
                    'total_batches' => $batches,
                );
            }
        }

        return $tasks;
    }

    /**
     * Process a batch of images
     */
    public static function process_image_batch($gallery_id, $offset, $limit) {
        global $wpdb;

        $images_table = $wpdb->prefix . 'bwg_image';
        $upload_dir = wp_upload_dir();
        $bwg_base_url = $upload_dir['baseurl'] . '/photo-gallery';

        $images = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $images_table
            WHERE gallery_id = %d AND published = 1
            ORDER BY `order` ASC, id ASC
            LIMIT %d OFFSET %d
        ", $gallery_id, $limit, $offset));

        $attachment_ids = array();
        $processed = 0;
        $errors = array();

        foreach ($images as $image) {
            $image_path = ltrim($image->image_url, '\\/');
            $full_url = $bwg_base_url . '/' . str_replace('\\', '/', $image_path);

            // Try to find existing attachment
            $attachment_id = self::get_attachment_id_by_url($full_url);

            if (!$attachment_id) {
                // Try to import the image
                $attachment_id = self::import_image_to_media_library($full_url, $image->alt);
            }

            if ($attachment_id) {
                $attachment_ids[] = $attachment_id;
            } else {
                $errors[] = basename($image->image_url);
            }

            $processed++;
        }

        return array(
            'attachment_ids' => $attachment_ids,
            'processed' => $processed,
            'errors' => $errors,
        );
    }

    /**
     * Finalize migration - save to ACF
     */
    public static function finalize_migration($target_page_id, $galleries_data) {
        if (!function_exists('update_field')) {
            return false;
        }

        // Clean up the data structure
        $clean_data = array();
        foreach ($galleries_data as $gallery) {
            if (!empty($gallery['image_ids'])) {
                $clean_data[] = array(
                    'gallery_title' => sanitize_text_field($gallery['name']),
                    'gallery_id' => sanitize_title($gallery['slug']),
                    'gallery_images' => array_map('intval', $gallery['image_ids']),
                    'images_per_page' => 12,
                    'columns' => '3',
                );
            }
        }

        if (!empty($clean_data)) {
            update_field('tbg_galleries', $clean_data, $target_page_id);
            return true;
        }

        return false;
    }

    /**
     * Get BWG galleries from database
     */
    public static function get_bwg_galleries() {
        global $wpdb;

        $galleries_table = $wpdb->prefix . 'bwg_gallery';
        $images_table = $wpdb->prefix . 'bwg_image';

        if ($wpdb->get_var("SHOW TABLES LIKE '$galleries_table'") !== $galleries_table) {
            return array();
        }

        return $wpdb->get_results("
            SELECT g.*, COUNT(i.id) as image_count
            FROM $galleries_table g
            LEFT JOIN $images_table i ON g.id = i.gallery_id AND i.published = 1
            WHERE g.published = 1
            GROUP BY g.id
            ORDER BY g.order ASC, g.id ASC
        ");
    }

    /**
     * Get image count for a gallery
     */
    public static function get_bwg_image_count($gallery_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'bwg_image';
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE gallery_id = %d AND published = 1",
            $gallery_id
        ));
    }

    /**
     * Get a single BWG gallery by ID
     */
    public static function get_bwg_gallery_by_id($gallery_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'bwg_gallery';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $gallery_id));
    }

    /**
     * Get attachment ID by URL
     */
    public static function get_attachment_id_by_url($url) {
        global $wpdb;

        $url = preg_replace('/\?.*/', '', $url);

        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM $wpdb->posts WHERE guid = %s",
            $url
        ));

        if ($attachment_id) {
            return intval($attachment_id);
        }

        $filename = basename($url);
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s",
            '%' . $wpdb->esc_like($filename)
        ));

        return $attachment_id ? intval($attachment_id) : 0;
    }

    /**
     * Import image to media library
     */
    public static function import_image_to_media_library($url, $alt = '') {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $upload_dir = wp_upload_dir();
        $local_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $url);

        if (file_exists($local_path)) {
            $filename = basename($local_path);
            $filetype = wp_check_filetype($filename);

            $attachment = array(
                'guid' => $url,
                'post_mime_type' => $filetype['type'],
                'post_title' => sanitize_file_name(pathinfo($filename, PATHINFO_FILENAME)),
                'post_content' => '',
                'post_status' => 'inherit'
            );

            $new_file = $upload_dir['path'] . '/' . $filename;
            if (!file_exists($new_file)) {
                copy($local_path, $new_file);
            }

            $attachment_id = wp_insert_attachment($attachment, $new_file);

            if (!is_wp_error($attachment_id)) {
                $attach_data = wp_generate_attachment_metadata($attachment_id, $new_file);
                wp_update_attachment_metadata($attachment_id, $attach_data);

                if ($alt) {
                    update_post_meta($attachment_id, '_wp_attachment_image_alt', $alt);
                }

                return $attachment_id;
            }
        }

        return 0;
    }
}

// Initialize migration
TBG_Migration::init();
