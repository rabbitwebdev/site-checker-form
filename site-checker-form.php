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

function sitecheck_reg_acf_blocks() {
    /**
     * We register our block's with WordPress's handy
     * register_block_type();
     *
     * @link https://developer.wordpress.org/reference/functions/register_block_type/
     */
    register_block_type( plugin_dir_path(__FILE__) . 'blocks/sitechecker-form' );
}
// Here we call our tt3child_register_acf_block() function on init.
add_action( 'init', 'sitecheck_reg_acf_blocks' );

// Shortcode form
function scf_render_form() {
    ob_start(); ?>
    <form method="post">
        <label class="scf-label">Name: <input type="text" name="scf_name" required></label>
        <label class="scf-label">Email: <input type="email" name="scf_email" required></label>
        <label class="scf-label">Website: <input type="url" name="scf_website" required></label>
        <input type="hidden" name="scf_form_submitted" value="1">
        <?php wp_nonce_field('scf_form_action', 'scf_form_nonce'); ?>
        <input type="submit" class="btn button" value="Check Website">
    </form>
    <style>
        .scf-label {
  display: flex;
  flex-direction: column;
  margin-bottom: 25px;
  text-transform: uppercase;
  font-family: inherit;
  letter-spacing: 1px;
}
.scf-label input {
  margin-top: 10px;
  padding: 10px 5px;
  border: 1px solid;
  border-radius: 10px;
}
.btn.button {
  width: 100%;
  padding: 15px;
  text-transform: uppercase;
  background-color: #000;
  color: #fff;
  font-size: 19px;
  font-weight: 700;
  letter-spacing: 2px;
  border: 0;
}
</style>
    <?php return ob_get_clean();
}

// Handle submission
function scf_handle_form_submission() {
    if (!isset($_POST['scf_form_submitted'])) return;
    if (!isset($_POST['scf_form_nonce']) || !wp_verify_nonce($_POST['scf_form_nonce'], 'scf_form_action')) return;

    $name = sanitize_text_field($_POST['scf_name']);
    $email = sanitize_email($_POST['scf_email']);
    $website = esc_url_raw($_POST['scf_website']);

    $diagnostics = [
        'status_code' => '',
        'ssl_valid' => '',
        'page_title' => '',
        'final_url' => '',
        'html_has_doctype' => '',
        'html_has_description' => '',
        'html_has_h1' => '',
        'html_has_charset' => '',
        'html_has_favicon' => '',
    ];

    $result = "";

    if (!filter_var($website, FILTER_VALIDATE_URL)) {
        $result = "❌ Invalid URL format.";
    } else {
        $response = wp_remote_get($website, [
            'timeout' => 10,
            'redirection' => 5,
            'user-agent' => 'WP Site Checker Bot/1.0',
        ]);

        if (is_wp_error($response)) {
            $result = "❌ Error reaching the website: " . $response->get_error_message();
        } else {
            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $headers = wp_remote_retrieve_headers($response);

            $ssl_valid = (strpos($website, 'https://') === 0);
            $diagnostics['ssl_valid'] = $ssl_valid ? '✅ Yes' : '❌ No';
            $diagnostics['status_code'] = $code;
            $diagnostics['final_url'] = $headers['location'] ?? $website;

            // Title
            preg_match("/<title>(.*?)<\/title>/is", $body, $title_matches);
            $diagnostics['page_title'] = isset($title_matches[1]) ? trim($title_matches[1]) : 'N/A';

            // HTML Checks
            $diagnostics['html_has_doctype'] = (stripos($body, '<!doctype') !== false) ? '✅ Yes' : '❌ No';
            $diagnostics['html_has_description'] = (preg_match('/<meta[^>]+name=["\']description["\']/i', $body)) ? '✅ Yes' : '❌ No';
            $diagnostics['html_has_h1'] = (stripos($body, '<h1') !== false) ? '✅ Yes' : '❌ No';
            $diagnostics['html_has_charset'] = (preg_match('/<meta[^>]+charset=/i', $body)) ? '✅ Yes' : '❌ No';
            $diagnostics['html_has_favicon'] = (preg_match('/<link[^>]+rel=["\']icon["\']/i', $body)) ? '✅ Yes' : '❌ No';

            // Build result string
            $result = "✅ Website responded.\n";
            $result .= "- HTTP Code: {$diagnostics['status_code']}\n";
            $result .= "- SSL: {$diagnostics['ssl_valid']}\n";
            $result .= "- Title: {$diagnostics['page_title']}\n";
            $result .= "- Final URL: {$diagnostics['final_url']}\n\n";
            $result .= "🧪 HTML Diagnostics:\n";
            $result .= "- DOCTYPE: {$diagnostics['html_has_doctype']}\n";
            $result .= "- Meta Description: {$diagnostics['html_has_description']}\n";
            $result .= "- H1 Tag: {$diagnostics['html_has_h1']}\n";
            $result .= "- Charset Meta: {$diagnostics['html_has_charset']}\n";
            $result .= "- Favicon Link: {$diagnostics['html_has_favicon']}\n";
        }
    }

    // Email
    $report = "Hi $name,\n\nYour website diagnostic for $website:\n\n$result";
    wp_mail($email, "Website HTML Diagnostic Report", $report);

    // Save Post
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
        foreach ($diagnostics as $key => $val) {
            update_post_meta($post_id, "_scf_$key", $val);
        }
    }
}

// Meta box in admin
function scf_add_meta_boxes() {
    add_meta_box('scf_details', 'Website Check Details', 'scf_meta_box_callback', 'website_check');
}

function scf_meta_box_callback($post) {
    $fields = [
    '_scf_name' => 'Name',
    '_scf_email' => 'Email',
    '_scf_website' => 'Website',
    '_scf_result' => 'Result',
    '_scf_ssl_valid' => 'SSL Valid',
    '_scf_status_code' => 'HTTP Status',
    '_scf_page_title' => 'Page Title',
    '_scf_final_url' => 'Final URL',
    '_scf_html_has_doctype' => 'Has DOCTYPE',
    '_scf_html_has_description' => 'Has Meta Description',
    '_scf_html_has_h1' => 'Has <h1> Tag',
    '_scf_html_has_charset' => 'Has Charset Meta',
    '_scf_html_has_favicon' => 'Has Favicon Link',
];
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
