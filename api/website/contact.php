<?php
/**
 * Website Contact Us API
 *
 * POST api/website/contact.php   (Submit a contact / support message)
 */

require_once dirname(__DIR__) . '/helpers/response.php';

setupApiHeaders();

if (getRequestMethod() !== 'POST') {
    jsonError('Method not allowed. Use POST.', 405);
}

try {
    $pdo = Database::getConnection();
    $input = getJsonInput();

    $fullName = trim((string)($input['fullName'] ?? $input['name'] ?? ''));
    $email    = trim((string)($input['email'] ?? ''));
    $mobile   = trim((string)($input['mobile'] ?? $input['phone'] ?? ''));
    $subject  = trim((string)($input['subject'] ?? 'General Inquiry'));
    $message  = trim((string)($input['message'] ?? ''));

    if ($fullName === '' || $email === '' || $message === '') {
        jsonError('Full name, email address, and message are required.', 422);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonError('Please provide a valid email address.', 422);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO contact_messages (full_name, email, mobile, subject, message, status)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$fullName, $email, $mobile, $subject, $message, 'New']);

    $newId = (int)$pdo->lastInsertId();
    $fetch = $pdo->prepare('SELECT * FROM contact_messages WHERE id = ? LIMIT 1');
    $fetch->execute([$newId]);

    jsonSuccess(
        formatContactMessage($fetch->fetch()),
        "Thank you {$fullName}! Your message has been sent. Our team will contact you within 24 hours.",
        201
    );
} catch (Throwable $e) {
    jsonError('Contact API error: ' . (APP_DEBUG ? $e->getMessage() : 'Internal error'), 500);
}
