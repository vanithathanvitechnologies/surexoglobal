<?php
/**
 * Local File Upload Helper
 *
 * Saves images safely into local project folder `uploads/<subdir>/`
 * and returns a portable local relative path (`./uploads/<subdir>/<file>`)
 * that works identically on local servers and https://ttbilling.in/surexoglobal/.
 */

require_once dirname(__DIR__) . '/config/config.php';

/**
 * Save a single uploaded image from $_FILES into uploads/<subdir>/.
 * Returns local relative path like "./uploads/products/img_xxxx.jpg".
 */
function saveUploadedImage(array $file, string $subdir = 'products'): string
{
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('File upload failed with error code: ' . ($file['error'] ?? 'unknown'));
    }

    if (($file['size'] ?? 0) > MAX_UPLOAD_SIZE) {
        throw new RuntimeException('File exceeds maximum allowed size of 10MB.');
    }

    $tmpPath = $file['tmp_name'] ?? '';
    if (!is_uploaded_file($tmpPath)) {
        throw new RuntimeException('Invalid uploaded file.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmpPath);
    if (!isset(ALLOWED_IMAGE_MIMES[$mime])) {
        throw new RuntimeException('Unsupported image format. Allowed: JPG, PNG, WEBP, GIF.');
    }

    $ext = ALLOWED_IMAGE_MIMES[$mime];
    $safeSubdir = preg_replace('/[^a-z0-9_\-]/i', '', $subdir) ?: 'general';
    $targetDir = UPLOADS_DIR . DIRECTORY_SEPARATOR . $safeSubdir;

    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Unable to create upload directory.');
    }

    $fileName = sprintf('%s_%s_%s.%s', $safeSubdir, date('Ymd_His'), bin2hex(random_bytes(4)), $ext);
    $destPath = $targetDir . DIRECTORY_SEPARATOR . $fileName;

    if (!move_uploaded_file($tmpPath, $destPath)) {
        throw new RuntimeException('Failed to move uploaded file.');
    }

    return UPLOADS_REL_PATH . '/' . $safeSubdir . '/' . $fileName;
}

/**
 * Save a base64 data-URI image ("data:image/png;base64,...") into uploads/<subdir>/.
 * Returns local relative path like "./uploads/requests/requests_xxxx.png".
 */
function saveBase64Image(string $dataUri, string $subdir = 'requests'): ?string
{
    if (!preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,(.+)$/s', $dataUri, $matches)) {
        return null;
    }

    $mime = strtolower($matches[1]);
    if ($mime === 'image/jpg') {
        $mime = 'image/jpeg';
    }
    if (!isset(ALLOWED_IMAGE_MIMES[$mime])) {
        return null;
    }

    $binary = base64_decode($matches[2], true);
    if ($binary === false || strlen($binary) > MAX_UPLOAD_SIZE) {
        return null;
    }

    $ext = ALLOWED_IMAGE_MIMES[$mime];
    $safeSubdir = preg_replace('/[^a-z0-9_\-]/i', '', $subdir) ?: 'general';
    $targetDir = UPLOADS_DIR . DIRECTORY_SEPARATOR . $safeSubdir;

    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        return null;
    }

    $fileName = sprintf('%s_%s_%s.%s', $safeSubdir, date('Ymd_His'), bin2hex(random_bytes(4)), $ext);
    $destPath = $targetDir . DIRECTORY_SEPARATOR . $fileName;

    if (file_put_contents($destPath, $binary) === false) {
        return null;
    }

    return UPLOADS_REL_PATH . '/' . $safeSubdir . '/' . $fileName;
}
