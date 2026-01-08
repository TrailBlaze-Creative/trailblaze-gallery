<?php
/**
 * Plugin Name: Trailblaze Gallery
 * Plugin URI: https://trailblazecreative.com
 * Description: Custom photo gallery plugin with ACF integration, carousel navigation, and lightbox functionality.
 * Version: 1.1.1
 * Author: Trailblaze Creative
 * Author URI: https://trailblazecreative.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: trailblaze-gallery
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('TBG_VERSION', '1.1.1');
define('TBG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TBG_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include migration tool
require_once TBG_PLUGIN_DIR . 'includes/migration.php';

// Include Plugin Update Checker
require_once TBG_PLUGIN_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$tbgUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/TrailBlaze-Creative/trailblaze-gallery/',
    __FILE__,
    'trailblaze-gallery'
);

// Set the branch that contains the stable release
$tbgUpdateChecker->setBranch('main');

/**
 * Main plugin class
 */
class Trailblaze_Gallery {

    /**
     * Instance of this class
     */
    private static $instance = null;

    /**
     * Gallery counter for unique IDs
     */
    private static $gallery_counter = 0;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action('init', array($this, 'register_acf_fields'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_shortcode('tbg_gallery', array($this, 'render_gallery_shortcode'));
        add_shortcode('trailblaze_gallery', array($this, 'render_gallery_shortcode'));
    }

    /**
     * Register ACF fields
     */
    public function register_acf_fields() {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        acf_add_local_field_group(array(
            'key' => 'group_tbg_galleries',
            'title' => 'Photo Galleries',
            'fields' => array(
                array(
                    'key' => 'field_tbg_galleries_repeater',
                    'label' => 'Galleries',
                    'name' => 'tbg_galleries',
                    'type' => 'repeater',
                    'instructions' => '',
                    'required' => 0,
                    'min' => 0,
                    'max' => 0,
                    'layout' => 'block',
                    'button_label' => 'Add Gallery',
                    'sub_fields' => array(
                        array(
                            'key' => 'field_tbg_gallery_title',
                            'label' => 'Gallery Title',
                            'name' => 'gallery_title',
                            'type' => 'text',
                            'instructions' => 'Display title shown above the gallery (e.g., "Day 1 - Ballroom")',
                            'required' => 1,
                        ),
                        array(
                            'key' => 'field_tbg_gallery_id',
                            'label' => 'Gallery ID',
                            'name' => 'gallery_id',
                            'type' => 'text',
                            'instructions' => 'Enter a unique ID using only letters, numbers, and hyphens (e.g., "ballroom1" or "day-2-breakout").<br>Your shortcode will be: <code>[tbg_gallery id="<em>your-id-here</em>"]</code>',
                            'required' => 1,
                        ),
                        array(
                            'key' => 'field_tbg_gallery_images',
                            'label' => 'Gallery Images',
                            'name' => 'gallery_images',
                            'type' => 'gallery',
                            'instructions' => 'Select images for this gallery.',
                            'required' => 1,
                            'return_format' => 'array',
                            'preview_size' => 'medium',
                            'library' => 'all',
                            'min' => 1,
                            'max' => 0,
                            'insert' => 'append',
                        ),
                        array(
                            'key' => 'field_tbg_images_per_page',
                            'label' => 'Images Per Page',
                            'name' => 'images_per_page',
                            'type' => 'number',
                            'instructions' => 'Number of images to show per page (default: 12 for 3x4 grid)',
                            'required' => 0,
                            'default_value' => 12,
                            'min' => 1,
                            'max' => 50,
                        ),
                        array(
                            'key' => 'field_tbg_columns',
                            'label' => 'Columns',
                            'name' => 'columns',
                            'type' => 'select',
                            'instructions' => 'Number of columns in the grid',
                            'required' => 0,
                            'default_value' => '3',
                            'choices' => array(
                                '2' => '2 Columns',
                                '3' => '3 Columns',
                                '4' => '4 Columns',
                            ),
                        ),
                    ),
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'page',
                    ),
                ),
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'post',
                    ),
                ),
            ),
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'active' => true,
        ));
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_assets() {
        // Only load on pages with our shortcode or galleries
        global $post;
        if (!is_singular() || !$post) {
            return;
        }

        $has_shortcode = has_shortcode($post->post_content, 'tbg_gallery') ||
                         has_shortcode($post->post_content, 'trailblaze_gallery');
        $has_galleries = function_exists('get_field') && get_field('tbg_galleries', $post->ID);

        if (!$has_shortcode && !$has_galleries) {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style(
            'trailblaze-gallery',
            TBG_PLUGIN_URL . 'assets/css/gallery.css',
            array(),
            TBG_VERSION
        );

        // Enqueue JS
        wp_enqueue_script(
            'trailblaze-gallery',
            TBG_PLUGIN_URL . 'assets/js/gallery.js',
            array('jquery'),
            TBG_VERSION,
            true
        );

        // Localize script
        wp_localize_script('trailblaze-gallery', 'tbgGallery', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tbg_gallery_nonce'),
        ));
    }

    /**
     * Render gallery shortcode
     *
     * Usage: [tbg_gallery id="gallery_id"] or [tbg_gallery post_id="123" gallery_index="0"]
     */
    public function render_gallery_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => '',
            'post_id' => get_the_ID(),
            'gallery_index' => 0,
            'columns' => 3,
            'per_page' => 12,
            'show_title' => 'true',
        ), $atts, 'tbg_gallery');

        if (!function_exists('get_field')) {
            return '<p class="tbg-error">ACF Pro is required for this gallery.</p>';
        }

        $galleries = get_field('tbg_galleries', $atts['post_id']);

        if (empty($galleries)) {
            return '';
        }

        // Find the gallery by ID or index
        $gallery = null;
        $gallery_index = intval($atts['gallery_index']);

        if (!empty($atts['id'])) {
            foreach ($galleries as $idx => $g) {
                if ($g['gallery_id'] === $atts['id']) {
                    $gallery = $g;
                    $gallery_index = $idx;
                    break;
                }
            }
        } else {
            $gallery = isset($galleries[$gallery_index]) ? $galleries[$gallery_index] : null;
        }

        if (!$gallery || empty($gallery['gallery_images'])) {
            return '';
        }

        self::$gallery_counter++;
        $unique_id = 'tbg-gallery-' . self::$gallery_counter;

        $images = $gallery['gallery_images'];
        $columns = isset($gallery['columns']) ? intval($gallery['columns']) : intval($atts['columns']);
        $per_page = isset($gallery['images_per_page']) ? intval($gallery['images_per_page']) : intval($atts['per_page']);
        $total_images = count($images);
        $total_pages = ceil($total_images / $per_page);
        $anchor_id = sanitize_html_class($gallery['gallery_id']);

        ob_start();
        ?>
        <div id="<?php echo esc_attr($anchor_id); ?>" class="tbg-gallery-wrapper" data-gallery-id="<?php echo esc_attr($unique_id); ?>" data-per-page="<?php echo esc_attr($per_page); ?>" data-columns="<?php echo esc_attr($columns); ?>">

            <?php if ($atts['show_title'] === 'true' && !empty($gallery['gallery_title'])) : ?>
                <h4 class="tbg-gallery-title"><?php echo esc_html($gallery['gallery_title']); ?></h4>
            <?php endif; ?>

            <div class="tbg-gallery-container">
                <div class="tbg-gallery-grid columns-<?php echo esc_attr($columns); ?>">
                    <?php foreach ($images as $idx => $image) :
                        $page_num = (int) floor($idx / $per_page) + 1;
                        $is_visible = ($page_num === 1);
                    ?>
                        <div class="tbg-gallery-item<?php echo $is_visible ? ' visible' : ''; ?>" data-page="<?php echo esc_attr($page_num); ?>" data-index="<?php echo esc_attr($idx); ?>">
                            <a href="<?php echo esc_url($image['url']); ?>"
                               class="tbg-gallery-link"
                               data-full="<?php echo esc_url($image['url']); ?>"
                               data-thumb="<?php echo esc_url($image['sizes']['thumbnail'] ?? $image['url']); ?>"
                               data-alt="<?php echo esc_attr($image['alt']); ?>"
                               data-caption="<?php echo esc_attr($image['caption']); ?>"
                               data-gallery="<?php echo esc_attr($unique_id); ?>">
                                <img src="<?php echo esc_url($image['sizes']['medium'] ?? $image['url']); ?>"
                                     alt="<?php echo esc_attr($image['alt']); ?>"
                                     loading="lazy">
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($total_pages > 1) : ?>
                    <div class="tbg-gallery-pagination">
                        <div class="tbg-pagination-nav">
                            <button class="tbg-nav-btn tbg-nav-first" data-action="first" disabled title="First Page">&laquo;</button>
                            <button class="tbg-nav-btn tbg-nav-prev" data-action="prev" disabled title="Previous Page">&lsaquo;</button>
                        </div>
                        <div class="tbg-pagination-info">
                            <span class="tbg-current-page">1</span>
                            <span class="tbg-page-separator">of</span>
                            <span class="tbg-total-pages"><?php echo esc_html($total_pages); ?></span>
                        </div>
                        <div class="tbg-pagination-nav">
                            <button class="tbg-nav-btn tbg-nav-next" data-action="next" title="Next Page">&rsaquo;</button>
                            <button class="tbg-nav-btn tbg-nav-last" data-action="last" title="Last Page">&raquo;</button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Store all image data for lightbox -->
            <script type="application/json" class="tbg-gallery-data">
            <?php echo json_encode(array_map(function($img) {
                return array(
                    'url' => $img['url'],
                    'thumb' => $img['sizes']['thumbnail'] ?? $img['url'],
                    'medium' => $img['sizes']['medium'] ?? $img['url'],
                    'alt' => $img['alt'],
                    'caption' => $img['caption'],
                );
            }, $images)); ?>
            </script>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get all galleries for a page and render them
     */
    public static function render_all_galleries($post_id = null) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }

        if (!function_exists('get_field')) {
            return '';
        }

        $galleries = get_field('tbg_galleries', $post_id);

        if (empty($galleries)) {
            return '';
        }

        $output = '';
        foreach ($galleries as $index => $gallery) {
            $output .= do_shortcode('[tbg_gallery post_id="' . $post_id . '" gallery_index="' . $index . '"]');
        }

        return $output;
    }
}

// Initialize the plugin
Trailblaze_Gallery::get_instance();

/**
 * Template tag function to render all galleries
 */
function tbg_render_galleries($post_id = null) {
    echo Trailblaze_Gallery::render_all_galleries($post_id);
}

/**
 * Add shortcode info to admin
 */
add_action('admin_notices', function() {
    $screen = get_current_screen();
    if ($screen && ($screen->base === 'post' || $screen->base === 'page')) {
        ?>
        <div class="notice notice-info is-dismissible" style="display:none;" id="tbg-shortcode-info">
            <p><strong>Trailblaze Gallery:</strong> Use <code>[tbg_gallery id="your_gallery_id"]</code> to display a specific gallery, or use <code>[tbg_gallery gallery_index="0"]</code> to display by index.</p>
        </div>
        <?php
    }
});
