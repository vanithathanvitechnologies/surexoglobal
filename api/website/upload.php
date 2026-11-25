<?php
/**
 * Website Image Upload API (for Custom Product Request references & customer avatars)
 *
 * POST api/website/upload.php (multipart/form-data with `file` or `files[]`, optional `folder=requests|avatars`)
 */

require_once dirname(__DIR__) . '/helpers/response.php';
require_once dirname(__DIR__) . '/helpers/upload.php';

setupApiHeaders();

if (getRequestMethod() !== 'POST') {
    jsonError('Method not allowed. Use POST.', 405);
}

try {
    $folder = trim((string)($_POST['folder'] ?? $_GET['folder'] ?? 'requests'));
    if (!in_array($folder, ['requests', 'avatars'], true)) {
        $folder = 'requests';
    }

    $uploaded = [];

    if (!empty($_FILES['file'])) {
        $uploaded[] = saveUploadedImage($_FILES['file'], $folder);
    } elseif (!empty($_FILES['files'])) {
        $files = $_FILES['files'];
        if (is_array($files['name'])) {
            $limit = min(5, count($files['name']));
            for ($i = 0; $i < $limit; $i++) {
                if ($files['error'][$i] === UPLOAD_ERR_OK) {
                    $uploaded[] = saveUploadedImage([
                        'name'     => $files['name'][$i],
                        'type'     => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error'    => $files['error'][$i],
                        'size'     => $files['size'][$i],
                    ], $folder);
                }
            }
        } else {
            $uploaded[] = saveUploadedImage($files, $folder);
        }
    } else {
        jsonError('No image file uploaded. Send multipart form field "file" or "files[]".', 422);
    }

    jsonSuccess([
        'path'  => $uploaded[0] ?? null,
        'paths' => $uploaded,
    ], 'Image uploaded successfully', 201);
} catch (Throwable $e) {
    jsonError($e->getMessage(), 400);
}
