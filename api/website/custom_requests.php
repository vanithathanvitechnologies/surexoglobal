<?php
/**
 * Website Custom Product Requests API
 *
 * GET  api/website/custom_requests.php   (List requests or fetch single by ?id=PRQ-1001)
 * POST api/website/custom_requests.php   (Submit a custom sourcing request with optional images)
 */

require_once dirname(__DIR__) . '/helpers/auth.php';
require_once dirname(__DIR__) . '/helpers/upload.php';

setupApiHeaders();

$method = getRequestMethod();

try {
    $pdo = Database::getConnection();
    $customer = getAuthenticatedCustomer($pdo);

    if ($method === 'GET') {
        if (!empty($_GET['id'])) {
            $stmt = $pdo->prepare('SELECT * FROM custom_requests WHERE id = ? LIMIT 1');
            $stmt->execute([trim((string)$_GET['id'])]);
            $row = $stmt->fetch();
            if (!$row) {
                jsonError('Custom request not found.', 404);
            }
            jsonSuccess(formatCustomRequest($row), 'Custom request fetched');
        }

        $email = trim((string)($_GET['email'] ?? ($customer['email'] ?? '')));
        if ($email !== '') {
            $stmt = $pdo->prepare("SELECT * FROM custom_requests WHERE email = ? OR email = '' ORDER BY created_at DESC");
            $stmt->execute([$email]);
        } else {
            $stmt = $pdo->query('SELECT * FROM custom_requests ORDER BY created_at DESC LIMIT 50');
        }

        $requests = array_map('formatCustomRequest', $stmt->fetchAll());
        jsonSuccess($requests, 'Custom requests fetched', 200, ['total' => count($requests)]);
    }

    if ($method === 'POST') {
        $input = getJsonInput();

        $productName = trim((string)($input['productName'] ?? ''));
        if ($productName === '') {
            jsonError('Product name is required.', 422);
        }

        $fullName          = trim((string)($input['fullName'] ?? ($customer['name'] ?? '')));
        $email             = trim((string)($input['email'] ?? ($customer['email'] ?? '')));
        $mobile            = trim((string)($input['mobile'] ?? ($customer['mobile'] ?? '')));
        $accountId         = trim((string)($input['accountId'] ?? ($customer['id'] ?? '')));
        $category          = trim((string)($input['category'] ?? 'General')) ?: 'General';
        $subcategory       = trim((string)($input['subcategory'] ?? ''));
        $brand             = trim((string)($input['brand'] ?? ''));
        $budget            = (float)($input['budget'] ?? 0);
        $quantity          = max(1, (int)($input['quantity'] ?? 1));
        $requirements      = trim((string)($input['requirements'] ?? ''));
        $location          = trim((string)($input['location'] ?? ''));
        $deliveryDate      = trim((string)($input['deliveryDate'] ?? ''));
        $contactPreference = trim((string)($input['contactPreference'] ?? ''));
        $notes             = trim((string)($input['notes'] ?? ($input['description'] ?? $requirements)));

        // Handle images (either uploaded $_FILES, base64 data URIs, or existing relative/http URLs)
        $savedImages = [];
        if (!empty($_FILES['images'])) {
            $files = $_FILES['images'];
            if (is_array($files['name'])) {
                $count = min(5, count($files['name']));
                for ($i = 0; $i < $count; $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $single = [
                            'name'     => $files['name'][$i],
                            'type'     => $files['type'][$i],
                            'tmp_name' => $files['tmp_name'][$i],
                            'error'    => $files['error'][$i],
                            'size'     => $files['size'][$i],
                        ];
                        $savedImages[] = saveUploadedImage($single, 'requests');
                    }
                }
            } elseif ($files['error'] === UPLOAD_ERR_OK) {
                $savedImages[] = saveUploadedImage($files, 'requests');
            }
        }

        if (!empty($input['images']) && is_array($input['images'])) {
            foreach (array_slice($input['images'], 0, 5) as $img) {
                if (!is_string($img) || trim($img) === '') {
                    continue;
                }
                if (strpos($img, 'data:image/') === 0) {
                    $localPath = saveBase64Image($img, 'requests');
                    if ($localPath) {
                        $savedImages[] = $localPath;
                    }
                } elseif (strpos($img, 'blob:') !== 0) {
                    $savedImages[] = trim($img);
                }
            }
        }

        // Generate sequential readable Request Reference PRQ-1007, PRQ-1008...
        $maxNum = 1006;
        $idRows = $pdo->query("SELECT id FROM custom_requests WHERE id LIKE 'PRQ-%'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($idRows as $existingId) {
            $num = (int)preg_replace('/[^0-9]/', '', (string)$existingId);
            if ($num > $maxNum) {
                $maxNum = $num;
            }
        }
        $newId = 'PRQ-' . ($maxNum + 1);
        $requestDate = date('d-m-Y');

        $ins = $pdo->prepare(
            'INSERT INTO custom_requests (
                id, request_date, customer_id, full_name, email, mobile, account_id,
                product_name, category, subcategory, brand, status, quantity, budget,
                requirements, location, delivery_date, contact_preference, notes, images
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([
            $newId,
            $requestDate,
            $customer['id'] ?? null,
            $fullName,
            $email,
            $mobile,
            $accountId,
            $productName,
            $category,
            $subcategory,
            $brand,
            'Under Review',
            $quantity,
            $budget,
            $requirements,
            $location,
            $deliveryDate,
            $contactPreference,
            $notes,
            json_encode(array_values(array_slice($savedImages, 0, 5)), JSON_UNESCAPED_SLASHES),
        ]);

        $stmt = $pdo->prepare('SELECT * FROM custom_requests WHERE id = ? LIMIT 1');
        $stmt->execute([$newId]);
        $created = formatCustomRequest($stmt->fetch());

        jsonSuccess($created, "Request submitted! Reference: {$newId}", 201);
    }

    jsonError('Method not allowed.', 405);
} catch (Throwable $e) {
    jsonError('Custom requests API error: ' . (APP_DEBUG ? $e->getMessage() : 'Internal error'), 500);
}
