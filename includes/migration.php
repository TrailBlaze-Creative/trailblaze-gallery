<?php
/**
 * Migration script for importing data from Photo Gallery by 10Web (BWG)
 *
 * Usage: Add ?tbg_migrate=1 to any admin page URL while logged in as admin
 * Example: /wp-admin/?tbg_migrate=1
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Migration class for BWG to Trailblaze Gallery
 */
class TBG_Migration {

    /**
     * Initialize migration hooks
     */
    public static function init() {
        add_action('admin_init', array(__CLASS__, 'maybe_run_migration'));
        add_action('admin_menu', array(__CLASS__, 'add_migration_page'));
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
        $selected_page = isset($_GET['target_page']) ? intval($_GET['target_page']) : 0;
        $migration_done = isset($_GET['migrated']) && $_GET['migrated'] === 'success';

        ?>
        <div class="wrap">
            <h1>Migrate Photo Gallery by 10Web to Trailblaze Gallery</h1>

            <?php if ($migration_done) : ?>
                <div class="notice notice-success">
                    <p><strong>Migration completed successfully!</strong> The galleries have been imported to the selected page.</p>
                </div>
            <?php endif; ?>

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
                <form method="post" action="">
                    <?php wp_nonce_field('tbg_migrate_action', 'tbg_migrate_nonce'); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row">Target Page</th>
                            <td>
                                <?php
                                wp_dropdown_pages(array(
                                    'name' => 'target_page_id',
                                    'show_option_none' => '-- Select a page --',
                                    'option_none_value' => '0',
                                    'selected' => $selected_page,
                                ));
                                ?>
                                <p class="description">Select the page where galleries will be added. The ACF field group will be populated with the imported data.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Galleries to Import</th>
                            <td>
                                <?php foreach ($galleries as $gallery) : ?>
                                    <label style="display: block; margin-bottom: 8px;">
                                        <input type="checkbox" name="galleries[]" value="<?php echo esc_attr($gallery->id); ?>" checked>
                                        <?php echo esc_html($gallery->name); ?> (<?php echo esc_html($gallery->image_count); ?> images)
                                    </label>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <input type="submit" name="tbg_run_migration" class="button button-primary" value="Run Migration">
                    </p>
                </form>

            <?php endif; ?>

            <hr style="margin-top: 30px;">

            <h2>Manual Shortcode Reference</h2>
            <p>After migration, you can use these shortcodes:</p>
            <ul>
                <li><code>[tbg_gallery id="gallery_id"]</code> - Display a specific gallery by its ID/anchor</li>
                <li><code>[tbg_gallery gallery_index="0"]</code> - Display gallery by index (0 = first gallery)</li>
                <li><code>[tbg_gallery post_id="123" gallery_index="0"]</code> - Display gallery from a specific page</li>
            </ul>

        </div>
        <?php
    }

    /**
     * Check if migration should run
     */
    public static function maybe_run_migration() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (!isset($_POST['tbg_run_migration'])) {
            return;
        }

        if (!isset($_POST['tbg_migrate_nonce']) || !wp_verify_nonce($_POST['tbg_migrate_nonce'], 'tbg_migrate_action')) {
            wp_die('Security check failed');
        }

        $target_page_id = isset($_POST['target_page_id']) ? intval($_POST['target_page_id']) : 0;
        $gallery_ids = isset($_POST['galleries']) ? array_map('intval', $_POST['galleries']) : array();

        if (!$target_page_id || empty($gallery_ids)) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>Please select a target page and at least one gallery.</p></div>';
            });
            return;
        }

        $result = self::run_migration($target_page_id, $gallery_ids);

        if ($result) {
            wp_redirect(admin_url('tools.php?page=tbg-migration&migrated=success&target_page=' . $target_page_id));
            exit;
        }
    }

    /**
     * Get BWG galleries from database
     */
    public static function get_bwg_galleries() {
        global $wpdb;

        $galleries_table = $wpdb->prefix . 'bwg_gallery';
        $images_table = $wpdb->prefix . 'bwg_image';

        // Check if tables exist
        if ($wpdb->get_var("SHOW TABLES LIKE '$galleries_table'") !== $galleries_table) {
            return array();
        }

        $query = "
            SELECT g.*, COUNT(i.id) as image_count
            FROM $galleries_table g
            LEFT JOIN $images_table i ON g.id = i.gallery_id AND i.published = 1
            WHERE g.published = 1
            GROUP BY g.id
            ORDER BY g.order ASC, g.id ASC
        ";

        return $wpdb->get_results($query);
    }

    /**
     * Get images for a BWG gallery
     */
    public static function get_bwg_images($gallery_id) {
        global $wpdb;

        $images_table = $wpdb->prefix . 'bwg_image';

        $query = $wpdb->prepare("
            SELECT * FROM $images_table
            WHERE gallery_id = %d AND published = 1
            ORDER BY `order` ASC, id ASC
        ", $gallery_id);

        return $wpdb->get_results($query);
    }

    /**
     * Run the migration
     */
    public static function run_migration($target_page_id, $gallery_ids) {
        if (!function_exists('update_field')) {
            error_log('ACF not available for migration');
            return false;
        }

        $galleries_data = array();
        $upload_dir = wp_upload_dir();
        $bwg_base_url = $upload_dir['baseurl'] . '/photo-gallery';

        foreach ($gallery_ids as $gallery_id) {
            $bwg_gallery = self::get_bwg_gallery_by_id($gallery_id);
            if (!$bwg_gallery) continue;

            $bwg_images = self::get_bwg_images($gallery_id);
            if (empty($bwg_images)) continue;

            // Find or import images to media library
            $wp_image_ids = array();
            foreach ($bwg_images as $bwg_image) {
                $image_path = ltrim($bwg_image->image_url, '\\/');
                $full_url = $bwg_base_url . '/' . str_replace('\\', '/', $image_path);

                // Try to find existing attachment
                $attachment_id = self::get_attachment_id_by_url($full_url);

                if (!$attachment_id) {
                    // Try to import the image
                    $attachment_id = self::import_image_to_media_library($full_url, $bwg_image->alt);
                }

                if ($attachment_id) {
                    $wp_image_ids[] = $attachment_id;
                }
            }

            if (!empty($wp_image_ids)) {
                // Generate a clean slug for the gallery ID
                $slug = sanitize_title($bwg_gallery->slug ?: $bwg_gallery->name);

                $galleries_data[] = array(
                    'gallery_title' => $bwg_gallery->name,
                    'gallery_id' => $slug,
                    'gallery_images' => $wp_image_ids,
                    'images_per_page' => 12,
                    'columns' => '3',
                );
            }
        }

        if (!empty($galleries_data)) {
            update_field('tbg_galleries', $galleries_data, $target_page_id);
            return true;
        }

        return false;
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

        // Normalize URL
        $url = preg_replace('/\?.*/', '', $url); // Remove query strings

        // Try direct lookup
        $attachment_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM $wpdb->posts WHERE guid = %s",
            $url
        ));

        if ($attachment_id) {
            return intval($attachment_id);
        }

        // Try by filename
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

        // Convert URL to local path if possible
        $upload_dir = wp_upload_dir();
        $local_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $url);

        if (file_exists($local_path)) {
            // File exists locally, create attachment from it
            $filename = basename($local_path);
            $filetype = wp_check_filetype($filename);

            $attachment = array(
                'guid' => $url,
                'post_mime_type' => $filetype['type'],
                'post_title' => sanitize_file_name(pathinfo($filename, PATHINFO_FILENAME)),
                'post_content' => '',
                'post_status' => 'inherit'
            );

            // Copy to standard uploads location
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
