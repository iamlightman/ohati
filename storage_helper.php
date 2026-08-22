<?php
// storage_helper.php — Ohati Media Storage & Automated WebP Compression Adapter
date_default_timezone_set('Africa/Accra');

if (file_exists(__DIR__ . '/ohati_config.php')) {
    require_once __DIR__ . '/ohati_config.php';
}

/**
 * Compress image to WebP format using PHP GD library
 */
function compress_to_webp($source_file, $destination_webp, $quality = 82, $max_width = 1920, $max_height = 1080) {
    if (!file_exists($source_file)) return false;
    if (!extension_loaded('gd') || !function_exists('imagecreatefrompng') || !function_exists('imagewebp')) return false;

    $info = @getimagesize($source_file);
    if (!$info) return false;

    $mime = $info['mime'] ?? '';
    $width = $info[0] ?? 0;
    $height = $info[1] ?? 0;

    if ($width <= 0 || $height <= 0) return false;

    // Load GD Image Resource safely
    $image = null;
    switch ($mime) {
        case 'image/jpeg':
            $image = function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($source_file) : false;
            break;
        case 'image/png':
            $image = function_exists('imagecreatefrompng') ? @imagecreatefrompng($source_file) : false;
            break;
        case 'image/webp':
            $image = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source_file) : false;
            break;
        case 'image/gif':
            $image = function_exists('imagecreatefromgif') ? @imagecreatefromgif($source_file) : false;
            break;
        case 'image/bmp':
            $image = function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($source_file) : false;
            break;
        default:
            return false;
    }

    if (!$image) return false;

    // Calculate dimensions with aspect ratio
    $new_width = $width;
    $new_height = $height;

    if ($width > $max_width || $height > $max_height) {
        $ratio = min($max_width / $width, $max_height / $height);
        $new_width = (int)($width * $ratio);
        $new_height = (int)($height * $ratio);
    }

    // Resample Image
    $resized = imagecreatetruecolor($new_width, $new_height);
    if ($mime === 'image/png' || $mime === 'image/webp') {
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
        imagefilledrectangle($resized, 0, 0, $new_width, $new_height, $transparent);
    }

    imagecopyresampled($resized, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

    // Save as WebP
    $success = @imagewebp($resized, $destination_webp, $quality);

    imagedestroy($image);
    imagedestroy($resized);

    return $success ? $destination_webp : false;
}

/**
 * Universal Media File Upload Helper
 * Handles WebP compression, local storage, and Cloud Object Storage dispatch.
 * 
 * @param array|string $file_input $_FILES item array OR Base64 data string
 * @param string $folder Upload target folder (e.g. 'avatars', 'kyc', 'portfolio', 'job_attachments')
 * @param int $max_width Maximum width constraint
 * @return array ['success' => bool, 'url' => string, 'error' => string]
 */
function upload_media_file($file_input, $folder = 'general', $max_width = 1920) {
    $upload_base_dir = __DIR__ . '/uploads/' . trim($folder, '/') . '/';
    if (!file_exists($upload_base_dir)) {
        @mkdir($upload_base_dir, 0777, true);
    }

    $unique_name = time() . '_' . uniqid();
    $tmp_file = '';
    $original_name = 'upload';
    $is_base64 = false;

    // Handle Base64 Data String (images, PDFs, documents)
    if (is_string($file_input) && (strpos($file_input, 'data:') === 0 || strpos($file_input, 'base64,') !== false)) {
        $is_base64 = true;
        $base64_str = preg_replace('#^data:[^;]+;base64,#i', '', $file_input);
        $file_data = base64_decode($base64_str);
        if (empty($file_data)) {
            return ['success' => false, 'error' => 'Invalid base64 file data encoding'];
        }
        $ext = 'jpg';
        if (preg_match('#^data:application/pdf#i', $file_input)) $ext = 'pdf';
        else if (preg_match('#^data:image/png#i', $file_input)) $ext = 'png';
        else if (preg_match('#^data:image/webp#i', $file_input)) $ext = 'webp';
        else if (preg_match('#^data:image/gif#i', $file_input)) $ext = 'gif';

        $tmp_file = sys_get_temp_dir() . '/' . $unique_name . '.' . $ext;
        file_put_contents($tmp_file, $file_data);
    }
    // Handle Standard $_FILES Upload Item
    elseif (is_array($file_input) && !empty($file_input['tmp_name']) && is_uploaded_file($file_input['tmp_name'])) {
        $tmp_file = $file_input['tmp_name'];
        $original_name = pathinfo($file_input['name'] ?? 'upload', PATHINFO_FILENAME);
    } else {
        return ['success' => false, 'error' => 'No valid upload file provided'];
    }

    // Target local webp file path
    $target_filename = preg_replace('/[^a-zA-Z0-9_-]/', '', $original_name) . '_' . $unique_name . '.webp';
    $target_file_path = $upload_base_dir . $target_filename;

    // Perform WebP compression if image
    $compressed = compress_to_webp($tmp_file, $target_file_path, 82, $max_width);
    
    if (!$compressed) {
        // Fallback: Copy raw file if WebP conversion is unsupported for non-image formats (PDFs, docs)
        $ext = is_array($file_input) ? strtolower(pathinfo($file_input['name'] ?? '', PATHINFO_EXTENSION)) : (isset($ext) ? $ext : 'jpg');
        $target_filename = preg_replace('/[^a-zA-Z0-9_-]/', '', $original_name) . '_' . $unique_name . '.' . ($ext ?: 'jpg');
        $target_file_path = $upload_base_dir . $target_filename;
        @copy($tmp_file, $target_file_path);
    }

    if ($is_base64 && file_exists($tmp_file)) {
        @unlink($tmp_file);
    }

    $relative_url = 'uploads/' . trim($folder, '/') . '/' . $target_filename;

    // Check for Cloudinary Cloud Storage Integration
    $cloudinary_url = getenv('CLOUDINARY_URL') ?: (defined('CLOUDINARY_URL') ? CLOUDINARY_URL : '');
    if (!empty($cloudinary_url) && file_exists($target_file_path)) {
        // Perform Cloudinary REST API upload if configured
        $cloud_res = upload_to_cloudinary($target_file_path, $cloudinary_url, $folder);
        if (!empty($cloud_res['secure_url'])) {
            return ['success' => true, 'url' => $cloud_res['secure_url'], 'local_path' => $relative_url];
        }
    }

    return ['success' => true, 'url' => $relative_url, 'local_path' => $relative_url];
}

/**
 * Cloudinary REST API Uploader
 */
function upload_to_cloudinary($file_path, $cloudinary_url, $folder = 'ohati') {
    $parsed = parse_url($cloudinary_url);
    if (!$parsed) return false;

    $api_key = $parsed['user'] ?? '';
    $api_secret = $parsed['pass'] ?? '';
    $cloud_name = $parsed['host'] ?? '';

    if (empty($api_key) || empty($api_secret) || empty($cloud_name)) return false;

    $timestamp = time();
    $params = [
        'folder' => $folder,
        'timestamp' => $timestamp
    ];
    ksort($params);

    $sig_string = '';
    foreach ($params as $k => $v) {
        $sig_string .= "$k=$v&";
    }
    $sig_string = rtrim($sig_string, '&') . $api_secret;
    $signature = sha1($sig_string);

    $post_fields = [
        'file' => new CURLFile($file_path),
        'api_key' => $api_key,
        'timestamp' => $timestamp,
        'folder' => $folder,
        'signature' => $signature
    ];

    $ch = curl_init("https://api.cloudinary.com/v1_1/$cloud_name/image/upload");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

/**
 * Helper to convert relative media URL into absolute HTTPS URL for cross-platform WebViews (iOS / Android)
 */
function format_full_image_url($url) {
    if (empty($url)) return '';
    if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0 || strpos($url, 'data:') === 0) {
        return $url;
    }
    
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    
    if (empty($host)) {
        return $url;
    }
    
    $clean_path = ltrim($url, '/');
    return "$scheme://$host/$clean_path";
}
?>
