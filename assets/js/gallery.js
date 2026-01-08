/**
 * Trailblaze Gallery JavaScript
 * Carousel navigation and lightbox functionality
 */

(function($) {
    'use strict';

    // Gallery state storage
    const galleryStates = {};
    let lightboxInstance = null;
    let slideshowInterval = null;

    /**
     * Initialize a gallery
     */
    function initGallery($wrapper) {
        const galleryId = $wrapper.data('gallery-id');
        const perPage = parseInt($wrapper.data('per-page')) || 12;
        const $items = $wrapper.find('.tbg-gallery-item');
        const totalImages = $items.length;
        const totalPages = Math.ceil(totalImages / perPage);

        // Store gallery state
        galleryStates[galleryId] = {
            currentPage: 1,
            totalPages: totalPages,
            perPage: perPage,
            totalImages: totalImages,
            $wrapper: $wrapper,
            images: []
        };

        // Parse image data
        const $dataScript = $wrapper.find('.tbg-gallery-data');
        if ($dataScript.length) {
            try {
                galleryStates[galleryId].images = JSON.parse($dataScript.text());
            } catch (e) {
                console.error('Failed to parse gallery data:', e);
            }
        }

        // Initialize pagination
        updatePagination(galleryId);

        // Bind pagination events
        $wrapper.find('.tbg-nav-btn').on('click', function(e) {
            e.preventDefault();
            const action = $(this).data('action');
            handlePaginationAction(galleryId, action);
        });

        // Bind image click events for lightbox
        $wrapper.find('.tbg-gallery-link').on('click', function(e) {
            e.preventDefault();
            const index = $(this).closest('.tbg-gallery-item').data('index');
            openLightbox(galleryId, index);
        });
    }

    /**
     * Handle pagination action
     */
    function handlePaginationAction(galleryId, action) {
        const state = galleryStates[galleryId];
        if (!state) return;

        let newPage = state.currentPage;

        switch (action) {
            case 'first':
                newPage = 1;
                break;
            case 'prev':
                newPage = Math.max(1, state.currentPage - 1);
                break;
            case 'next':
                newPage = Math.min(state.totalPages, state.currentPage + 1);
                break;
            case 'last':
                newPage = state.totalPages;
                break;
        }

        if (newPage !== state.currentPage) {
            goToPage(galleryId, newPage);
        }
    }

    /**
     * Go to a specific page
     */
    function goToPage(galleryId, page) {
        const state = galleryStates[galleryId];
        if (!state) return;

        state.currentPage = page;

        // Show/hide items
        const $items = state.$wrapper.find('.tbg-gallery-item');
        $items.removeClass('visible');
        $items.filter('[data-page="' + page + '"]').addClass('visible');

        // Update pagination
        updatePagination(galleryId);
    }

    /**
     * Update pagination UI
     */
    function updatePagination(galleryId) {
        const state = galleryStates[galleryId];
        if (!state) return;

        const $wrapper = state.$wrapper;
        const current = state.currentPage;
        const total = state.totalPages;

        // Update page number
        $wrapper.find('.tbg-current-page').text(current);
        $wrapper.find('.tbg-total-pages').text(total);

        // Update button states
        $wrapper.find('.tbg-nav-first, .tbg-nav-prev').prop('disabled', current <= 1);
        $wrapper.find('.tbg-nav-next, .tbg-nav-last').prop('disabled', current >= total);
    }

    /**
     * Create lightbox HTML
     */
    function createLightbox() {
        if ($('#tbg-lightbox').length) {
            return $('#tbg-lightbox');
        }

        const html = `
            <div id="tbg-lightbox" class="tbg-lightbox-overlay">
                <button class="tbg-lightbox-close" title="Close">&times;</button>

                <div class="tbg-lightbox-content">
                    <button class="tbg-lightbox-nav tbg-lightbox-prev" title="Previous">&lsaquo;</button>
                    <div class="tbg-lightbox-image-container">
                        <div class="tbg-lightbox-loading"></div>
                        <img class="tbg-lightbox-image" src="" alt="">
                    </div>
                    <button class="tbg-lightbox-nav tbg-lightbox-next" title="Next">&rsaquo;</button>
                </div>

                <div class="tbg-lightbox-footer">
                    <button class="tbg-toggle-thumbs" title="Toggle thumbnails">&#9660;</button>
                    <div class="tbg-lightbox-controls">
                        <button class="tbg-lightbox-control-btn" data-action="play" title="Slideshow">
                            <svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                        </button>
                        <button class="tbg-lightbox-control-btn" data-action="fullscreen" title="Fullscreen">
                            <svg viewBox="0 0 24 24"><path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/></svg>
                        </button>
                        <button class="tbg-lightbox-control-btn" data-action="info" title="Show info">
                            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 16v-4m0-4h.01"/></svg>
                        </button>
                        <a class="tbg-lightbox-share-btn" data-action="facebook" title="Share on Facebook" target="_blank">
                            <svg viewBox="0 0 24 24"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3V2z"/></svg>
                        </a>
                        <a class="tbg-lightbox-share-btn" data-action="twitter" title="Share on Twitter" target="_blank">
                            <svg viewBox="0 0 24 24"><path d="M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z"/></svg>
                        </a>
                    </div>
                    <div class="tbg-lightbox-thumbs-container">
                        <button class="tbg-thumbs-scroll tbg-thumbs-scroll-left">&lsaquo;</button>
                        <div class="tbg-lightbox-thumbs"></div>
                        <button class="tbg-thumbs-scroll tbg-thumbs-scroll-right">&rsaquo;</button>
                    </div>
                </div>
            </div>
        `;

        $('body').append(html);
        return $('#tbg-lightbox');
    }

    /**
     * Open lightbox
     */
    function openLightbox(galleryId, index) {
        const state = galleryStates[galleryId];
        if (!state || !state.images.length) return;

        const $lightbox = createLightbox();

        lightboxInstance = {
            galleryId: galleryId,
            currentIndex: index,
            images: state.images,
            isFullscreen: false,
            isPlaying: false
        };

        // Build thumbnails
        buildThumbnails();

        // Show the image
        showImage(index);

        // Show lightbox
        $lightbox.addClass('active');
        $('body').css('overflow', 'hidden');

        // Bind events
        bindLightboxEvents();
    }

    /**
     * Build thumbnails strip
     */
    function buildThumbnails() {
        if (!lightboxInstance) return;

        const $thumbs = $('#tbg-lightbox .tbg-lightbox-thumbs');
        $thumbs.empty();

        lightboxInstance.images.forEach((img, idx) => {
            const $thumb = $('<div class="tbg-lightbox-thumb" data-index="' + idx + '">' +
                '<img src="' + img.thumb + '" alt="' + (img.alt || '') + '">' +
                '</div>');
            $thumbs.append($thumb);
        });
    }

    /**
     * Show image at index
     */
    function showImage(index) {
        if (!lightboxInstance) return;

        const images = lightboxInstance.images;
        if (index < 0 || index >= images.length) return;

        lightboxInstance.currentIndex = index;
        const img = images[index];

        const $lightbox = $('#tbg-lightbox');
        const $image = $lightbox.find('.tbg-lightbox-image');
        const $loading = $lightbox.find('.tbg-lightbox-loading');

        // Show loading
        $loading.show();
        $image.removeClass('loaded');

        // Load image
        const newImg = new Image();
        newImg.onload = function() {
            $image.attr('src', img.url).attr('alt', img.alt || '');
            $loading.hide();
            $image.addClass('loaded');
        };
        newImg.onerror = function() {
            $loading.hide();
            $image.attr('src', img.medium || img.url).addClass('loaded');
        };
        newImg.src = img.url;

        // Update thumbnails
        $lightbox.find('.tbg-lightbox-thumb').removeClass('active');
        $lightbox.find('.tbg-lightbox-thumb[data-index="' + index + '"]').addClass('active');

        // Scroll thumbnail into view
        scrollThumbIntoView(index);

        // Update share links
        updateShareLinks();

        // Update nav buttons
        $lightbox.find('.tbg-lightbox-prev').toggle(index > 0);
        $lightbox.find('.tbg-lightbox-next').toggle(index < images.length - 1);
    }

    /**
     * Scroll thumbnail into view
     */
    function scrollThumbIntoView(index) {
        const $thumbs = $('#tbg-lightbox .tbg-lightbox-thumbs');
        const $thumb = $thumbs.find('.tbg-lightbox-thumb[data-index="' + index + '"]');

        if ($thumb.length) {
            const thumbLeft = $thumb.position().left;
            const thumbWidth = $thumb.outerWidth();
            const containerWidth = $thumbs.width();
            const scrollLeft = $thumbs.scrollLeft();

            if (thumbLeft < 0) {
                $thumbs.scrollLeft(scrollLeft + thumbLeft - 10);
            } else if (thumbLeft + thumbWidth > containerWidth) {
                $thumbs.scrollLeft(scrollLeft + thumbLeft + thumbWidth - containerWidth + 10);
            }
        }
    }

    /**
     * Update share links
     */
    function updateShareLinks() {
        if (!lightboxInstance) return;

        const img = lightboxInstance.images[lightboxInstance.currentIndex];
        const url = encodeURIComponent(window.location.href);

        $('#tbg-lightbox [data-action="facebook"]').attr('href',
            'https://www.facebook.com/sharer.php?u=' + url);
        $('#tbg-lightbox [data-action="twitter"]').attr('href',
            'https://twitter.com/intent/tweet?url=' + url);
    }

    /**
     * Close lightbox
     */
    function closeLightbox() {
        stopSlideshow();

        if (lightboxInstance && lightboxInstance.isFullscreen) {
            exitFullscreen();
        }

        $('#tbg-lightbox').removeClass('active');
        $('body').css('overflow', '');
        lightboxInstance = null;

        // Unbind events
        $(document).off('.tbgLightbox');
    }

    /**
     * Navigate to previous/next image
     */
    function navigate(direction) {
        if (!lightboxInstance) return;

        const newIndex = lightboxInstance.currentIndex + direction;
        if (newIndex >= 0 && newIndex < lightboxInstance.images.length) {
            showImage(newIndex);
        }
    }

    /**
     * Toggle slideshow
     */
    function toggleSlideshow() {
        if (!lightboxInstance) return;

        if (lightboxInstance.isPlaying) {
            stopSlideshow();
        } else {
            startSlideshow();
        }
    }

    /**
     * Start slideshow
     */
    function startSlideshow() {
        if (!lightboxInstance) return;

        lightboxInstance.isPlaying = true;
        $('#tbg-lightbox').addClass('slideshow-playing');

        slideshowInterval = setInterval(function() {
            if (lightboxInstance.currentIndex < lightboxInstance.images.length - 1) {
                navigate(1);
            } else {
                showImage(0);
            }
        }, 3000);
    }

    /**
     * Stop slideshow
     */
    function stopSlideshow() {
        if (slideshowInterval) {
            clearInterval(slideshowInterval);
            slideshowInterval = null;
        }

        if (lightboxInstance) {
            lightboxInstance.isPlaying = false;
        }

        $('#tbg-lightbox').removeClass('slideshow-playing');
    }

    /**
     * Toggle fullscreen
     */
    function toggleFullscreen() {
        if (!lightboxInstance) return;

        if (lightboxInstance.isFullscreen) {
            exitFullscreen();
        } else {
            enterFullscreen();
        }
    }

    /**
     * Enter fullscreen
     */
    function enterFullscreen() {
        const elem = document.getElementById('tbg-lightbox');

        if (elem.requestFullscreen) {
            elem.requestFullscreen();
        } else if (elem.webkitRequestFullscreen) {
            elem.webkitRequestFullscreen();
        } else if (elem.msRequestFullscreen) {
            elem.msRequestFullscreen();
        }

        lightboxInstance.isFullscreen = true;
        $('#tbg-lightbox').addClass('fullscreen');
    }

    /**
     * Exit fullscreen
     */
    function exitFullscreen() {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        } else if (document.webkitExitFullscreen) {
            document.webkitExitFullscreen();
        } else if (document.msExitFullscreen) {
            document.msExitFullscreen();
        }

        if (lightboxInstance) {
            lightboxInstance.isFullscreen = false;
        }
        $('#tbg-lightbox').removeClass('fullscreen');
    }

    /**
     * Toggle thumbnails visibility
     */
    function toggleThumbnails() {
        $('#tbg-lightbox .tbg-lightbox-footer').toggleClass('hidden-thumbs');
    }

    /**
     * Bind lightbox events
     */
    function bindLightboxEvents() {
        const $lightbox = $('#tbg-lightbox');

        // Close button
        $lightbox.find('.tbg-lightbox-close').off('click').on('click', closeLightbox);

        // Click on overlay to close
        $lightbox.off('click.close').on('click.close', function(e) {
            if ($(e.target).is('.tbg-lightbox-overlay, .tbg-lightbox-content')) {
                closeLightbox();
            }
        });

        // Navigation arrows
        $lightbox.find('.tbg-lightbox-prev').off('click').on('click', function(e) {
            e.stopPropagation();
            navigate(-1);
        });
        $lightbox.find('.tbg-lightbox-next').off('click').on('click', function(e) {
            e.stopPropagation();
            navigate(1);
        });

        // Thumbnail clicks
        $lightbox.find('.tbg-lightbox-thumbs').off('click').on('click', '.tbg-lightbox-thumb', function(e) {
            e.stopPropagation();
            const index = $(this).data('index');
            showImage(index);
        });

        // Thumbnail scroll buttons
        $lightbox.find('.tbg-thumbs-scroll-left').off('click').on('click', function(e) {
            e.stopPropagation();
            const $thumbs = $lightbox.find('.tbg-lightbox-thumbs');
            $thumbs.scrollLeft($thumbs.scrollLeft() - 200);
        });
        $lightbox.find('.tbg-thumbs-scroll-right').off('click').on('click', function(e) {
            e.stopPropagation();
            const $thumbs = $lightbox.find('.tbg-lightbox-thumbs');
            $thumbs.scrollLeft($thumbs.scrollLeft() + 200);
        });

        // Control buttons
        $lightbox.find('[data-action="play"]').off('click').on('click', function(e) {
            e.stopPropagation();
            toggleSlideshow();
        });
        $lightbox.find('[data-action="fullscreen"]').off('click').on('click', function(e) {
            e.stopPropagation();
            toggleFullscreen();
        });

        // Toggle thumbnails
        $lightbox.find('.tbg-toggle-thumbs').off('click').on('click', function(e) {
            e.stopPropagation();
            toggleThumbnails();
        });

        // Keyboard navigation
        $(document).off('keydown.tbgLightbox').on('keydown.tbgLightbox', function(e) {
            if (!lightboxInstance) return;

            switch (e.key) {
                case 'Escape':
                    closeLightbox();
                    break;
                case 'ArrowLeft':
                    navigate(-1);
                    break;
                case 'ArrowRight':
                    navigate(1);
                    break;
                case ' ':
                    e.preventDefault();
                    toggleSlideshow();
                    break;
                case 'f':
                case 'F':
                    toggleFullscreen();
                    break;
            }
        });

        // Fullscreen change event
        $(document).off('fullscreenchange.tbgLightbox webkitfullscreenchange.tbgLightbox')
            .on('fullscreenchange.tbgLightbox webkitfullscreenchange.tbgLightbox', function() {
                if (!document.fullscreenElement && !document.webkitFullscreenElement) {
                    if (lightboxInstance) {
                        lightboxInstance.isFullscreen = false;
                    }
                    $lightbox.removeClass('fullscreen');
                }
            });

        // Prevent image container clicks from closing
        $lightbox.find('.tbg-lightbox-image-container').off('click').on('click', function(e) {
            e.stopPropagation();
        });
    }

    /**
     * Document ready
     */
    $(document).ready(function() {
        // Initialize all galleries
        $('.tbg-gallery-wrapper').each(function() {
            initGallery($(this));
        });
    });

})(jQuery);
