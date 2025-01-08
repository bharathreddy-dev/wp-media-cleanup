<?php
/**
 * OM MAHA GANAPATHAY NAMAHA
 * Plugin Name: WP Media Cleanup
 * Description: Deletes old media files attached to specific post types based on a selected date range.
 * Version: 1.0
 * Author: BHARATH KUMAR REDDY
 */

 if( !defined('ABSPATH') ){
    exit;
 }
// Enqueue admin scripts and styles
function dev_onl_wp_mc_enqueue_admin_scripts($hook) {
    if ($hook !== 'settings_page_media-cleanup') return;
    wp_enqueue_script('jquery-ui-datepicker');
    wp_enqueue_style('jquery-ui-css', '//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css');
    wp_enqueue_script('dev_onl_wp_mc-admin-js', plugins_url('/assets/js/admin.js', __FILE__), ['jquery'], '1.0', true);
    wp_localize_script('dev_onl_wp_mc-admin-js', 'dev_onl_wp_ajax', ['ajax_url' => admin_url('admin-ajax.php')]);
}
add_action('admin_enqueue_scripts', 'dev_onl_wp_mc_enqueue_admin_scripts');

// Add admin menu
function dev_onl_wp_mc_add_admin_menu() {
    add_options_page('Media Cleanup', 'Media Cleanup', 'manage_options', 'media-cleanup', 'dev_onl_wp_mc_admin_page');
}
add_action('admin_menu', 'dev_onl_wp_mc_add_admin_menu');

// Admin page HTML
function dev_onl_wp_mc_admin_page() {
    ?>
    <div class="wrap">
        <h1>Media Cleanup</h1>
        <form id="dev_onl_wp_mc-cleanup-form">
            <p>
                <label for="dev_onl_wp_mc-date-range">Delete media files older than:</label>
                <input type="text" id="dev_onl_wp_mc-date-range" name="date_range" readonly>
                <small>(Minimum: 3 months ago)</small>
            </p>
            <p>
                <label for="dev_onl_wp_mc-post-types">Select post types:</label>
                <select id="dev_onl_wp_mc-post-types" name="post_types[]" multiple>
                    <?php
                    $post_types = get_post_types(['public' => true], 'objects');
                    foreach ($post_types as $post_type) {
                        echo "<option value='{$post_type->name}'>{$post_type->label}</option>";
                    }
                    ?>
                </select>
            </p>
            <button type="button" id="dev_onl_wp_mc-start-cleanup" class="button button-primary">Start Cleanup</button>
        </form>
        <div id="dev_onl_wp_mc-logs" style="margin-top: 20px; background: #f1f1f1; padding: 10px; border: 1px solid #ccc;">
            <h3>Logs</h3>
            <ul id="dev_onl_wp_mc-log-list"></ul>
        </div>
    </div>
    <?php
}

// AJAX handler for cleanup
function dev_onl_wp_mc_cleanup_media() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }

    $date_range = sanitize_text_field($_POST['date_range']);
    $post_types = array_map('sanitize_text_field', $_POST['post_types']);

    // Parse date range
    $date_limit = date('Y-m-d', strtotime($date_range));

    // Fetch posts with the specified criteria
    $args = [
        'post_type' => $post_types,
        'date_query' => [
            ['before' => $date_limit],
        ],
        'posts_per_page' => -1,
        'fields' => 'ids',
    ];
    $posts = get_posts($args);

    $deleted_files = 0;
    foreach ($posts as $post_id) {
        $attachments = get_attached_media('', $post_id);
        foreach ($attachments as $attachment) {
            $file_path = get_attached_file($attachment->ID);
            if (unlink($file_path)) {
                wp_delete_attachment($attachment->ID, true);
                $deleted_files++;
                echo "<li>Deleted: {$file_path}</li>";
            }
        }
    }

    wp_send_json_success([
        'message' => "Cleanup completed. Total deleted files: {$deleted_files}",
    ]);
}
add_action('wp_ajax_dev_onl_wp_mc_cleanup_media', 'dev_onl_wp_mc_cleanup_media');

// JavaScript for admin page (admin.js)
add_action('admin_footer', function () {
    ?>
    <script>
        jQuery(document).ready(function ($) {
            $('#dev_onl_wp_mc-date-range').datepicker({
                dateFormat: 'yy-mm-dd',
                minDate: new Date(new Date().setMonth(new Date().getMonth() - 3))
            });

            $('#dev_onl_wp_mc-start-cleanup').on('click', function () {
                const data = {
                    action: 'dev_onl_wp_mc_cleanup_media',
                    date_range: $('#dev_onl_wp_mc-date-range').val(),
                    post_types: $('#dev_onl_wp_mc-post-types').val(),
                };

                if (!data.date_range || !data.post_types.length) {
                    alert('Please select a date range and post types.');
                    return;
                }

                $('#dev_onl_wp_mc-log-list').empty().append('<li>Starting cleanup...</li>');
                $.post(dev_onl_wp_ajax.ajax_url, data, function (response) {
                    if (response.success) {
                        $('#dev_onl_wp_mc-log-list').append('<li>' + response.data.message + '</li>');
                    } else {
                        $('#dev_onl_wp_mc-log-list').append('<li>Error: ' + response.data.message + '</li>');
                    }
                });
            });
        });
    </script>
    <?php
});
