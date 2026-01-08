# Trailblaze Gallery

Custom WordPress photo gallery plugin with ACF integration, carousel navigation, and lightbox functionality.

## Features

- **ACF Pro Integration**: Uses repeater fields with nested gallery fields for easy management
- **Multiple Galleries**: Support for multiple galleries per page with unique shortcodes
- **3x4 Grid Layout**: Configurable grid with 2, 3, or 4 columns
- **Carousel Navigation**: Page-based navigation through gallery images
- **Lightbox**: Full-featured lightbox with:
  - Thumbnail strip navigation
  - Keyboard navigation (arrow keys, Escape, Space for slideshow, F for fullscreen)
  - Slideshow mode
  - Fullscreen support
  - Social sharing buttons
- **Responsive Design**: Mobile-friendly layout
- **Lazy Loading**: Images load as needed for better performance

## Requirements

- WordPress 5.0+
- PHP 7.4+
- ACF Pro (Advanced Custom Fields Pro)

## Installation

1. Upload the `trailblaze-gallery` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Ensure ACF Pro is installed and activated

## Usage

### Adding Galleries

1. Edit any page or post
2. Scroll to the "Photo Galleries" meta box
3. Click "Add Gallery" to create a new gallery
4. Fill in:
   - **Gallery Title**: Display title for the gallery section
   - **Gallery ID/Anchor**: Unique ID for shortcode and anchor links (e.g., "ballroom1")
   - **Gallery Images**: Select images from the media library
   - **Images Per Page**: Number of images per carousel page (default: 12)
   - **Columns**: Grid columns (2, 3, or 4)

### Shortcodes

Display a specific gallery by ID:
```
[tbg_gallery id="ballroom1"]
```

Display by index (0-based):
```
[tbg_gallery gallery_index="0"]
```

Display from a different post/page:
```
[tbg_gallery id="ballroom1" post_id="123"]
```

### Template Tag

In theme templates, use:
```php
<?php tbg_render_galleries(); ?>
```

Or for a specific post:
```php
<?php tbg_render_galleries($post_id); ?>
```

## Migration from 10Web Photo Gallery

If migrating from "Photo Gallery by 10Web":

1. Go to **Tools → Migrate BWG Gallery**
2. Review detected galleries
3. Select a target page for each gallery
4. Click "Migrate Selected Galleries"
5. Update page content with new shortcodes
6. Deactivate the old plugin

## Changelog

### 1.0.0
- Initial release
- ACF Pro integration with repeater fields
- Grid layout with configurable columns
- Carousel pagination
- Lightbox with thumbnails, slideshow, fullscreen
- Migration tool from 10Web Photo Gallery

## License

GPL-2.0+

## Author

[Trailblaze Creative](https://trailblazecreative.com)
