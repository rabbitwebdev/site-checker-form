<?php
/**
 * Plugin Name: Site Checker Form
 * Description: Collects user info, checks a web address, emails a report, and stores data in a custom post type.
 * Version: 1.1
 * Author: Your Name
 */

add_action('init', 'scf_register_cpt');
add_shortcode('site_check_form', 'scf_render_form');
add_action('init', 'scf_handle_form_submission');
add_action('add_meta_boxes', 'scf_add_meta_boxes');
add_action('save_post', 'scf_save_meta_boxes');

// Register Custom Post Type
function scf_register_cpt() {
    register_post_type('website_check', [
        'label' => 'Website Checks',
        'public' => false,
        'show_ui' => true,
        'supports' => ['title'],
        'menu_icon' => 'dashicons-search',
    ]);
}

// Shortcode form
function scf_render_form() {
    ob_start(); ?>
    <form method="post">
        <p><label>Name: <input type="text" name="scf_name" required></label></p>
        <p><label>Email: <input type="email" name="scf_email" required></label></p>
        <p><label>Website: <input type="url" name="scf_website" required></label></p>
        <input type="hidden" name="scf_form_submitted" value="1">
        <?php wp_nonce_field('scf_form_action', 'scf_form_nonce'); ?>
        <p><input type="submit" value="Check Website"></p>
    </form>
    <?php return ob_get_clean();
}

// Handle submission
function scf_handle_form_submission() {
    if (!isset($_POST['scf_form_submitted'])) return;
    if (!isset($_POST['scf_form_nonce']) || !wp_verify_nonce($_POST['scf_form_nonce'], 'scf_form_action')) return;

    $name = sanitize_text_field($_POST['scf_name']);
    $email = sanitize_email($_POST['scf_email']);
    $website = esc_url_raw($_POST['scf_website']);
    $result = "";

    if (!filter_var($website, FILTER_VALIDATE_URL)) {
        $result = "Invalid URL format.";
    } else {
        $response = wp_remote_get($website, ['timeout' => 10]);
        if (is_wp_error($response)) {
            $result = "Error: " . $response->get_error_message();
        } else {
            $code = wp_remote_retrieve_response_code($response);
            $result = "Website responded with HTTP code: $code";
        }
    }

    // Send email
    $report = "Hi $name,\n\nHere's the result of your website check for $website:\n\n$result\n";
    wp_mail($email, "Website Check Report", $report);

    // Store as CPT
    $post_id = wp_insert_post([
        'post_type' => 'website_check',
        'post_title' => "$name - " . current_time('Y-m-d H:i'),
        'post_status' => 'publish',
    ]);

    if ($post_id) {
        update_post_meta($post_id, '_scf_name', $name);
        update_post_meta($post_id, '_scf_email', $email);
        update_post_meta($post_id, '_scf_website', $website);
        update_post_meta($post_id, '_scf_result', $result);
    }
}

// Meta box in admin
function scf_add_meta_boxes() {
    add_meta_box('scf_details', 'Website Check Details', 'scf_meta_box_callback', 'website_check');
}

function scf_meta_box_callback($post) {
    $fields = ['_scf_name' => 'Name', '_scf_email' => 'Email', '_scf_website' => 'Website', '_scf_result' => 'Result'];
    foreach ($fields as $key => $label) {
        $value = esc_html(get_post_meta($post->ID, $key, true));
        echo "<p><strong>$label:</strong><br><input type='text' name='$key' value='$value' style='width:100%;'></p>";
    }
}

function scf_save_meta_boxes($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    foreach (['_scf_name', '_scf_email', '_scf_website', '_scf_result'] as $field) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
        }
    }
}
