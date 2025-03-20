if (!function_exists('generate_unique_id')) {
    function generate_unique_id() {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $length = 10;
        $random_string = '';
        for ($i = 0; $i < $length; $i++) {
            $random_string .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $random_string;
    }
}

if (!function_exists('sanitize_filename_enhanced')) {
    function sanitize_filename_enhanced($filename) {
        $filename_without_ext = pathinfo($filename, PATHINFO_FILENAME);
        $sanitized_filename = preg_replace('/[^a-z0-9_-]/i', '_', $filename_without_ext);
        $sanitized_filename = preg_replace('/[\x00-\x1F\x7F<>\\|:"?*]/i', '_', $sanitized_filename);
        $sanitized_filename = strtolower($sanitized_filename);

        if (!$sanitized_filename) {
            throw new Exception("Filename cannot be empty after sanitization");
        } elseif (!preg_match('/^[a-z0-9_-]+$/i', $sanitized_filename)) {
            throw new Exception("Sanitized filename contains invalid characters");
        }

        $unique_id = generate_unique_id();
        $sanitized_filename .= '_' . $unique_id . '.' . pathinfo($filename, PATHINFO_EXTENSION);

        return $sanitized_filename;
    }
}

if (!function_exists('generateSuccessMessage')) {
    function generateSuccessMessage($url) {
        $escaped_url = esc_url($url);
        $success_message = esc_html__('Status: File upload completed.', 'your-text-domain'); // Replace 'your-text-domain'
        $htmlContent = <<<HTML
        <div class="url-container">
            <div class="result-container">
                <span class="success-message">$success_message</span>
                <div class="result-message-container">
                    <i class="far fa-badge-check success-message-icon"></i>
                    <h4 class="result-header">Success</h4>
                    <p>Your file has been uploaded successfully.</p>
                </div>
                <img src="$escaped_url" alt="Uploaded File" id="uploadedImage">
                <input type="text" class="url-string" value="$escaped_url" id="urlField" readonly>
                <button class="copyBtn" onclick="copyToClipboard()">Copy File URL</button>
            </div>
        </div>
HTML;
        return $htmlContent;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_FILES['custom_image_upload']['name'])) {
        echo '<div class="error-message">Please select a file to upload.</div>';
        return;
    }
    $file = $_FILES['custom_image_upload'];

    // Commenting out image-specific validation
    /*
    $image_info = getimagesize($file['tmp_name']);
    if ($image_info === FALSE) {
        $error = error_get_last();
        echo '<div class="error-message">Unable to determine the image type of the uploaded file. Error: ' . htmlspecialchars($error['message']) . '</div>';
        return;
    }

    $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($image_info['mime'], $allowed_mime_types)) {
        echo '<div class="error-message">Invalid file type. Please upload only JPEG, PNG, or GIF images.</div>';
        return;
    }
    */

    if (!function_exists('wp_handle_upload')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }

    if ($_FILES['custom_image_upload']['size'] > (2 * 1024 * 1024)) {
        echo '<div class="error-message">File size exceeds limit. Please upload files under 2 MB.</div>';
        return;
    }

    if ($_FILES['custom_image_upload']['error'] !== UPLOAD_ERR_OK) {
        echo '<div class="error-message">Error during file upload: ' . $_FILES['custom_image_upload']['error'] . '</div>';
        return;
    }

    if (is_uploaded_file($_FILES['custom_image_upload']['tmp_name'])) {
        if (!file_exists($_FILES['custom_image_upload']['tmp_name'])) {
            echo '<div class="error-message">The file does not exist in the temporary location.</div>';
            return;
        }
    } else {
        echo '<div class="error-message">The file specified is not a valid upload.</div>';
        return;
    }

    if (!function_exists('wp_handle_upload')) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
    }

    $nonce_action = 'custom_image_upload';
    $nonce_field = 'custom_image_upload_nonce';

    if (!isset($_POST[$nonce_field]) || !wp_verify_nonce($_POST[$nonce_field], $nonce_action)) {
        echo '<div class="error-message">Error: Nonce verification failed.</div>';
        return;
    }

    $sanitized_filename = sanitize_filename_enhanced($file['name']);
    $file['name'] = $sanitized_filename;
    $uploaded_file = wp_handle_upload($file, ['test_form' => false]);

    if (isset($uploaded_file['error'])) {
        echo '<div class="error-message">Upload error: ' . esc_html($uploaded_file['error']) . '</div>';
        return;
    }

    $filetype = wp_check_filetype(basename($uploaded_file['file']), null);

    //Add html mime type validation
    $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif', 'text/html'];
    if($filetype['ext'] === 'html'){
        if($filetype['type'] !== 'text/html'){
            echo '<div class="error-message">Invalid file type. Please upload only JPEG, PNG, GIF, or HTML files.</div>';
            return;
        }

    } else if(!in_array($filetype['type'], $allowed_mime_types)){
        echo '<div class="error-message">Invalid file type. Please upload only JPEG, PNG, GIF, or HTML files.</div>';
        return;
    }

    $attachment = [
        'guid'           => $uploaded_file['url'],
        'post_mime_type' => $filetype['type'],
        'post_title'     => preg_replace('/\.[^.]+$/', '', basename($uploaded_file['file'])),
        'post_content'   => '',
        'post_status'    => 'inherit',
    ];

    $attachment_id = wp_insert_attachment($attachment, $uploaded_file['file']);
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attachment_data = wp_generate_attachment_metadata($attachment_id, $uploaded_file['file']);
    wp_update_attachment_metadata($attachment_id, $attachment_data);

    $url_message = esc_url($uploaded_file['url']);
    echo generateSuccessMessage($url_message);
} else {
    // Handle GET requests or other types of requests that are not POST
}

echo <<<JS
<script type='text/javascript'>
function copyToClipboard() {
    var copyText = document.getElementById('urlField');
    copyText.select();
    document.execCommand('copy');
    alert('URL copied to clipboard!');
}
</script>
JS;
