/**
 * Trailblaze Gallery Migration Script
 * Handles AJAX batch processing to avoid timeouts
 */

(function($) {
    'use strict';

    let isRunning = false;
    let tasks = [];
    let currentTaskIndex = 0;
    let galleriesData = {};
    let totalProcessed = 0;
    let totalErrors = 0;

    /**
     * Initialize migration form
     */
    function init() {
        $('#tbg-migration-form').on('submit', function(e) {
            e.preventDefault();
            if (!isRunning) {
                startMigration();
            }
        });
    }

    /**
     * Start the migration process
     */
    function startMigration() {
        const targetPage = $('#tbg-target-page').val();
        const selectedGalleries = [];

        $('input[name="galleries[]"]:checked').each(function() {
            selectedGalleries.push($(this).val());
        });

        if (!targetPage || targetPage === '0') {
            showNotice('Please select a target page.', 'error');
            return;
        }

        if (selectedGalleries.length === 0) {
            showNotice('Please select at least one gallery to migrate.', 'error');
            return;
        }

        isRunning = true;
        tasks = [];
        currentTaskIndex = 0;
        galleriesData = {};
        totalProcessed = 0;
        totalErrors = 0;

        $('#tbg-start-migration').prop('disabled', true).text('Migrating...');
        $('#tbg-migration-progress').show();
        $('#tbg-progress-log').empty();

        log('Starting migration...');
        log('Target page ID: ' + targetPage);
        log('Selected galleries: ' + selectedGalleries.length);

        // Step 1: Prepare tasks
        $.ajax({
            url: tbgMigration.ajaxUrl,
            type: 'POST',
            data: {
                action: 'tbg_migrate_batch',
                nonce: tbgMigration.nonce,
                action_type: 'prepare',
                gallery_ids: selectedGalleries
            },
            success: function(response) {
                if (response.success) {
                    tasks = response.data.tasks;
                    log('Prepared ' + tasks.length + ' batches to process');
                    processNextBatch();
                } else {
                    log('Error preparing tasks: ' + (response.data || 'Unknown error'), 'error');
                    finishWithError();
                }
            },
            error: function(xhr, status, error) {
                log('AJAX error: ' + error, 'error');
                finishWithError();
            }
        });
    }

    /**
     * Process the next batch
     */
    function processNextBatch() {
        if (currentTaskIndex >= tasks.length) {
            finalizeMigration();
            return;
        }

        const task = tasks[currentTaskIndex];
        const progress = Math.round((currentTaskIndex / tasks.length) * 100);

        updateProgress(progress);
        log('Processing: ' + task.gallery_name + ' (batch ' + task.batch + '/' + task.total_batches + ')');

        $.ajax({
            url: tbgMigration.ajaxUrl,
            type: 'POST',
            data: {
                action: 'tbg_migrate_batch',
                nonce: tbgMigration.nonce,
                action_type: 'process_batch',
                gallery_id: task.gallery_id,
                offset: task.offset
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;

                    // Store image IDs for this gallery
                    if (!galleriesData[task.gallery_id]) {
                        galleriesData[task.gallery_id] = {
                            id: task.gallery_id,
                            name: task.gallery_name,
                            slug: task.gallery_slug,
                            image_ids: []
                        };
                    }

                    galleriesData[task.gallery_id].image_ids =
                        galleriesData[task.gallery_id].image_ids.concat(data.attachment_ids);

                    totalProcessed += data.processed;

                    if (data.errors && data.errors.length > 0) {
                        totalErrors += data.errors.length;
                        data.errors.forEach(function(err) {
                            log('  Warning: Could not import ' + err, 'warning');
                        });
                    }

                    log('  Processed ' + data.processed + ' images, ' + data.attachment_ids.length + ' imported');

                    currentTaskIndex++;

                    // Small delay to prevent overwhelming the server
                    setTimeout(processNextBatch, 100);
                } else {
                    log('Error processing batch: ' + (response.data || 'Unknown error'), 'error');
                    currentTaskIndex++;
                    setTimeout(processNextBatch, 100);
                }
            },
            error: function(xhr, status, error) {
                log('AJAX error on batch: ' + error, 'error');
                currentTaskIndex++;
                setTimeout(processNextBatch, 500);
            }
        });
    }

    /**
     * Finalize the migration
     */
    function finalizeMigration() {
        const targetPage = $('#tbg-target-page').val();

        log('Finalizing migration...');
        updateProgress(95);

        // Convert galleriesData object to array
        const galleriesArray = Object.values(galleriesData);

        $.ajax({
            url: tbgMigration.ajaxUrl,
            type: 'POST',
            data: {
                action: 'tbg_migrate_batch',
                nonce: tbgMigration.nonce,
                action_type: 'finalize',
                target_page_id: targetPage,
                galleries_data: galleriesArray
            },
            success: function(response) {
                if (response.success && response.data.success) {
                    updateProgress(100);
                    log('Migration completed successfully!');
                    log('Total images processed: ' + totalProcessed);
                    if (totalErrors > 0) {
                        log('Images with warnings: ' + totalErrors, 'warning');
                    }

                    showNotice('Migration completed! ' + Object.keys(galleriesData).length + ' galleries imported with ' + totalProcessed + ' images.', 'success');

                    // Show shortcodes
                    log('');
                    log('=== Your Shortcodes ===');
                    galleriesArray.forEach(function(gallery) {
                        log('[tbg_gallery id="' + gallery.slug + '"] - ' + gallery.name);
                    });
                } else {
                    log('Error finalizing migration', 'error');
                    showNotice('Error saving gallery data. Please try again.', 'error');
                }
                finishMigration();
            },
            error: function(xhr, status, error) {
                log('AJAX error finalizing: ' + error, 'error');
                showNotice('Error finalizing migration. Please try again.', 'error');
                finishMigration();
            }
        });
    }

    /**
     * Finish migration (success or error)
     */
    function finishMigration() {
        isRunning = false;
        $('#tbg-start-migration').prop('disabled', false).text('Start Migration');
    }

    /**
     * Finish with error
     */
    function finishWithError() {
        isRunning = false;
        $('#tbg-start-migration').prop('disabled', false).text('Start Migration');
        showNotice('Migration failed. Check the log for details.', 'error');
    }

    /**
     * Update progress bar
     */
    function updateProgress(percent) {
        $('#tbg-progress-bar').css('width', percent + '%');
        $('#tbg-progress-text').text('Progress: ' + percent + '%');
    }

    /**
     * Log message to progress log
     */
    function log(message, type) {
        const $log = $('#tbg-progress-log');
        let color = '#333';

        if (type === 'error') color = '#dc3232';
        if (type === 'warning') color = '#dba617';
        if (type === 'success') color = '#46b450';

        const time = new Date().toLocaleTimeString();
        $log.append('<div style="color: ' + color + ';">[' + time + '] ' + message + '</div>');
        $log.scrollTop($log[0].scrollHeight);
    }

    /**
     * Show admin notice
     */
    function showNotice(message, type) {
        const noticeClass = type === 'error' ? 'notice-error' :
                           type === 'success' ? 'notice-success' : 'notice-info';

        $('#tbg-migration-notices').html(
            '<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>'
        );
    }

    // Initialize on document ready
    $(document).ready(init);

})(jQuery);
