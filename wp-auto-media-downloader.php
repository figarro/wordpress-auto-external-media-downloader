<?php

/**
 * Plugin Name: WordPress Auto External Media Downloader
 * Plugin URI: https://binaryfork.com
 * Description: Automatically downloads external media from whitelisted domains and replaces URLs with local copies
 * Version: 1.0.0
 * Author: Ionuț-Alexandru Popa
 * Author URI: https://www.linkedin.com/in/popaionut/
 * License: GPL-3.0
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: wp-auto-external-media-downloader
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

define('WP_AUTO_EXTERNAL_MEDIA_DOWNLOADER_VERSION', '1.0.0');
define('WP_AUTO_EXTERNAL_MEDIA_DOWNLOADER_PATH', plugin_dir_path(__FILE__));

/**
 * Register settings on admin_init
 */
add_action('admin_init', 'wp_aid_register_settings');

function wp_aid_register_settings()
{
    register_setting(
        'wp_aid_settings',
        'wp_aid_allowed_domains',
        array(
            'type' => 'string',
            'sanitize_callback' => 'wp_aid_sanitize_domains',
            'show_in_rest' => false,
        )
    );

    add_settings_section(
        'wp_aid_main_section',
        __('External Media Downloader Settings', 'wp-auto-media-downloader'),
        'wp_aid_section_callback',
        'wp_aid_settings_page'
    );

    add_settings_field(
        'wp_aid_allowed_domains',
        __('Allowed Domains', 'wp-auto-media-downloader'),
        'wp_aid_domains_field_callback',
        'wp_aid_settings_page',
        'wp_aid_main_section'
    );
}

/**
 * Section callback
 */
function wp_aid_section_callback()
{
    echo '<p>' . esc_html__('Configure which external domains are allowed for automatic media downloading.', 'wp-auto-media-downloader') . '</p>';
}

/**
 * Textarea field callback
 */
function wp_aid_domains_field_callback()
{
    $domains = get_option('wp_aid_allowed_domains', '');
?>
    <textarea
        name="wp_aid_allowed_domains"
        id="wp_aid_allowed_domains"
        rows="8"
        cols="50"
        class="large-text code"
        placeholder="unsplash.com&#10;pexels.com&#10;pixabay.com"><?php echo esc_textarea($domains); ?></textarea>

    <p class="description">
        <?php esc_html_e('Enter one domain per line. Media from these domains will be automatically downloaded and hosted locally when you save/publish posts.', 'wp-auto-media-downloader'); ?>
        <br>
        <strong><?php esc_html_e('Examples:', 'wp-auto-media-downloader'); ?></strong><br>
        <code>unsplash.com</code><br>
        <code>pexels.com</code><br>
        <code>cdn.example.com</code><br>
        <?php esc_html_e('Subdomains and www variants are handled automatically.', 'wp-auto-media-downloader'); ?>
    </p>
<?php
}

/**
 * Sanitize and format domains input
 */
function wp_aid_sanitize_domains($input)
{
    if (empty($input)) {
        return '';
    }

    // Split by newline
    $lines = array_map('trim', explode("\n", $input));

    // Filter empty lines and sanitize
    $domains = array_filter(array_map(function ($domain) {
        // Remove http/https and trailing slashes
        $domain = preg_replace('~^https?://~', '', $domain);
        $domain = rtrim($domain, '/');

        // Keep only valid domain characters
        $domain = preg_replace('/[^a-z0-9.-]/i', '', $domain);

        return !empty($domain) ? strtolower($domain) : '';
    }, $lines));

    return implode("\n", $domains);
}

/**
 * Add plugin menu to admin
 */
add_action('admin_menu', 'wp_aid_add_admin_menu');

function wp_aid_add_admin_menu()
{
    add_options_page(
        __('Auto External Media Downloader', 'wp-auto-external-media-downloader'),
        __('Auto Media Downloader', 'wp-auto-external-media-downloader'),
        'manage_options',
        'wp_aid_settings',
        'wp_aid_options_page'
    );
}

/**
 * Render options page
 */
function wp_aid_options_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    if (isset($_GET['settings-updated'])) {
        add_settings_error('wp_aid_messages', 'wp_aid_message', __('Settings Saved', 'wp-auto-media-downloader'), 'updated');
    }

    settings_errors('wp_aid_messages');
?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('wp_aid_settings');
            do_settings_sections('wp_aid_settings_page');
            submit_button();
            ?>
        </form>

        <div style="margin-top: 30px; padding: 20px; background: #f9f9f9; border-left: 4px solid #0073aa;">
            <h3><?php esc_html_e('How It Works', 'wp-auto-media-downloader'); ?></h3>
            <ol>
                <li><?php esc_html_e('Configure allowed domains above and save.', 'wp-auto-media-downloader'); ?></li>
                <li><?php esc_html_e('When you save or publish a post, the plugin scans for external media.', 'wp-auto-media-downloader'); ?></li>
                <li><?php esc_html_e('Media from whitelisted domains are automatically downloaded to your server.', 'wp-auto-media-downloader'); ?></li>
                <li><?php esc_html_e('Media URLs in the post are replaced with local copies.', 'wp-auto-media-downloader'); ?></li>
                <li><?php esc_html_e('All media is added to your Media Library and linked to the post.', 'wp-auto-media-downloader'); ?></li>
            </ol>
        </div>
    </div>
<?php
}

/**
 * Hook into post save to download external media
 */
add_action('save_post', 'wp_aid_download_external_media', 99, 2);

function wp_aid_download_external_media($post_id, $post)
{
    // Prevent infinite loops
    if (defined('WP_AID_DOWNLOADING')) {
        return;
    }

    // Only process published and draft posts
    if (!in_array($post->post_status, array('publish', 'draft'))) {
        return;
    }

    // Skip autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Skip if user doesn't have permission
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    define('WP_AID_DOWNLOADING', true);

    // Get allowed domains from options
    $domains_option = get_option('wp_aid_allowed_domains', '');

    if (empty($domains_option)) {
        return;
    }

    // Parse domains
    $allowed_domains = array_filter(array_map('trim', explode("\n", $domains_option)));

    if (empty($allowed_domains)) {
        return;
    }

    $content = $post->post_content;
    $modified = false;

    // Find all image URLs (both <img src=""> and variations) and media URLs (<audio>, <video>, <source>, and direct links)
    $urls_to_check = array();

    if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches)) {
        $urls_to_check = array_merge($urls_to_check, $matches[1]);
    }

    if (preg_match_all('/<audio[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches)) {
        $urls_to_check = array_merge($urls_to_check, $matches[1]);
    }

    if (preg_match_all('/<video[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches)) {
        $urls_to_check = array_merge($urls_to_check, $matches[1]);
    }

    // <source src="..."> inside audio/video
    if (preg_match_all('/<source[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches)) {
        $urls_to_check = array_merge($urls_to_check, $matches[1]);
    }

    // Direct links that look like media files
    if (preg_match_all('/<a[^>]+href=["\']([^"\']+\.(?:mp3|wav|ogg|m4a|mp4|webm|mov|mkv))["\'][^>]*>/i', $content, $matches)) {
        $urls_to_check = array_merge($urls_to_check, $matches[1]);
    }

    // Deduplicate
    $urls_to_check = array_unique($urls_to_check);

    foreach ($urls_to_check as $url) {
        // Only process external URLs and whitelisted domains
        if (wp_aid_is_external_url($url) && wp_aid_is_domain_whitelisted($url, $allowed_domains) && wp_aid_is_media_url($url)) {
            $local_url = wp_aid_download_and_import_media($url, $post_id);

            if ($local_url) {
                // Replace the old URL with the new local URL
                $content = str_replace($url, $local_url, $content);
                $modified = true;
            }
        }
    }

    // If content was modified, update the post without triggering save_post again
    if ($modified) {
        $updated = wp_update_post(array(
            'ID' => $post_id,
            'post_content' => $content,
        ), false);
    }
}

/**
 * Check if a URL is external (not from current domain)
 */
function wp_aid_is_external_url($url)
{
    $home_url = home_url();
    $home_domain = parse_url($home_url, PHP_URL_HOST);
    $url_domain = parse_url($url, PHP_URL_HOST);

    return $url_domain && strtolower($url_domain) !== strtolower($home_domain);
}

/**
 * Check if URL domain is in whitelist
 */
function wp_aid_is_domain_whitelisted($url, $allowed_domains)
{
    $url_domain = parse_url($url, PHP_URL_HOST);

    if (!$url_domain) {
        return false;
    }

    // Remove www. for comparison and normalize
    $url_domain = preg_replace('/^www\./', '', strtolower($url_domain));

    foreach ($allowed_domains as $allowed) {
        $allowed = trim($allowed);
        $allowed = preg_replace('/^www\./', '', strtolower($allowed));

        // Exact match or subdomain match
        if (
            $url_domain === $allowed ||
            preg_match('/' . preg_quote($allowed) . '$/i', $url_domain)
        ) {
            return true;
        }
    }

    return false;
}

/**
 * Check if URL looks like a media URL (image/audio/video) by extension
 */
function wp_aid_is_media_url($url)
{
    $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

    if (empty($ext)) {
        return false;
    }

    $allowed_exts = array('jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'mp3', 'wav', 'ogg', 'm4a', 'mp4', 'webm', 'mov', 'mkv');

    return in_array($ext, $allowed_exts, true);
}

/**
 * Download image and add to media library
 */
function wp_aid_download_and_import_media($url, $post_id)
{
    // Fetch the remote file
    $response = wp_remote_get($url, array(
        'timeout' => 20,
        'user-agent' => 'WordPress/' . get_bloginfo('version'),
        'sslverify' => apply_filters('https_local_ssl_verify', false),
    ));

    if (is_wp_error($response)) {
        return false;
    }

    $file_data = wp_remote_retrieve_body($response);

    if (empty($file_data)) {
        return false;
    }

    // Get filename from URL
    $filename = basename(parse_url($url, PHP_URL_PATH));

    // Determine content type header for fallback
    $content_type = wp_remote_retrieve_header($response, 'content-type');
    $content_type = $content_type ? explode(';', $content_type)[0] : '';

    // Mapping common mime -> extension
    $mime_ext_map = array(
        'audio/mpeg' => 'mp3',
        'audio/mp3' => 'mp3',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'audio/ogg' => 'ogg',
        'audio/mp4' => 'm4a',
        'audio/x-m4a' => 'm4a',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/quicktime' => 'mov',
        'video/x-matroska' => 'mkv',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    );

    // Clean filename and ensure extension
    if (empty($filename) || strpos($filename, '.') === false) {
        $ext = '';
        if (!empty($content_type) && isset($mime_ext_map[$content_type])) {
            $ext = $mime_ext_map[$content_type];
        }

        if (empty($ext)) {
            $ext = 'bin';
        }

        $filename = 'media-' . time() . '.' . $ext;
    } else {
        $filename = sanitize_file_name($filename);
    }

    // Get upload directory
    $upload_dir = wp_upload_dir();

    if (!$upload_dir['error']) {
        $file_path = $upload_dir['path'] . '/' . $filename;

        // Avoid filename conflicts
        $counter = 1;
        $original_filename = $filename;
        while (file_exists($file_path)) {
            $name = pathinfo($original_filename, PATHINFO_FILENAME);
            $ext = pathinfo($original_filename, PATHINFO_EXTENSION);
            $filename = $name . '-' . $counter . '.' . $ext;
            $file_path = $upload_dir['path'] . '/' . $filename;
            $counter++;
        }

        // Write file to disk
        $bytes_written = file_put_contents($file_path, $file_data);

        if ($bytes_written === false) {
            return false;
        }

        // Get MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file_path);
        finfo_close($finfo);

        if (!$mime_type) {
            $mime_type = !empty($content_type) ? $content_type : 'application/octet-stream';
        }

        // Prepare attachment
        $attachment = array(
            'post_title' => sanitize_file_name($original_filename),
            'post_content' => '',
            'post_status' => 'inherit',
            'post_mime_type' => $mime_type,
            'post_parent' => $post_id,
        );

        // Insert attachment
        $attach_id = wp_insert_attachment($attachment, $file_path, $post_id);

        if (is_wp_error($attach_id)) {
            return false;
        }

        // Generate attachment metadata (load image and media helpers for audio/video metadata too)
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
        if (!is_wp_error($attach_data) && !empty($attach_data)) {
            wp_update_attachment_metadata($attach_id, $attach_data);
        }

        // Return the local attachment URL
        return wp_get_attachment_url($attach_id);
    }

    return false;
}
