// Configuration - Update these values
const WP_API_URL = 'https://content.tst-34.aws.agilent.com/wp-json/wp/v2';
const WP_USERNAME = 'user@company.com'; // WordPress username
const WP_APP_PASSWORD = 'password'; // Application password

// Generate random string for filename suffix
const generateUniqueId = () => {
    const chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    return Array.from(crypto.getRandomValues(new Uint8Array(10)))
        .map(byte => chars[byte % chars.length])
        .join('');
};

// Enhanced filename sanitization
const sanitizeFilename = (filename) => {
    const ext = filename.split('.').pop();
    const name = filename.replace(/\.[^/.]+$/, "");
    
    // Replace special characters
    let sanitized = name.replace(/[^a-z0-9_-]/gi, '_')
                        .replace(/[\x00-\x1F\x7F<>\\|:"?*]/g, '_')
                        .toLowerCase();

    // Add unique ID
    return `${sanitized}_${generateUniqueId()}.${ext}`;
};

// Display messages to user
const showMessage = (message, isError = false) => {
    const container = document.getElementById('message-container');
    container.innerHTML = `
        <div class="${isError ? 'error-message' : 'success-message'}">
            ${message}
        </div>
    `;
};

// Handle form submission
document.getElementById('custom-image-upload-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const fileInput = document.getElementById('custom_image_upload');
    const file = fileInput.files[0];
    
    // Client-side validation
    if (!file) {
        showMessage('Please select a file to upload.', true);
        return;
    }

    if (file.size > 2 * 1024 * 1024) {
        showMessage('File size exceeds 2MB limit.', true);
        return;
    }

    try {
        // Prepare form data with sanitized filename
        const formData = new FormData();
        const sanitizedFilename = sanitizeFilename(file.name);
        formData.append('file', file, sanitizedFilename);

        // Create headers with authentication
        const headers = new Headers({
            'Authorization': 'Basic ' + btoa(`${WP_USERNAME}:${WP_APP_PASSWORD}`)
        });

        // Upload to WordPress Media Library
        const response = await fetch(`${WP_API_URL}/media`, {
            method: 'POST',
            headers: headers,
            body: formData
        });

        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.message || 'Upload failed');
        }

        const data = await response.json();
        
        // Display success message
        document.getElementById('message-container').innerHTML = `
            <div class="result-container">
                <div class="success-message">
                    <i class="far fa-badge-check"></i>
                    <h4>Success</h4>
                    <p>File uploaded successfully!</p>
                    <img src="${data.source_url}" alt="Uploaded file">
                    <input type="text" value="${data.source_url}" readonly>
                    <button onclick="navigator.clipboard.writeText('${data.source_url}')">
                        Copy URL
                    </button>
                </div>
            </div>
        `;

    } catch (error) {
        console.error('Upload error:', error);
        showMessage(`Upload failed: ${error.message}`, true);
    }
});
