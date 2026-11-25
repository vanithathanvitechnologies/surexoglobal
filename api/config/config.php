<?php
/**
 * Surexoglobal API Configuration
 *
 * Automatically detects local development (localhost / 127.0.0.1 / XAMPP / Laragon)
 * vs live production hosting (https://ttbilling.in/surexoglobal/).
 *
 * Update the database credentials below for your local MySQL and live cPanel MySQL.
 */

if (!defined('SUREXO_API')) {
    define('SUREXO_API', true);
}

// Detect environment (local vs live)
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$isLocal = (
    stripos($host, 'localhost') !== false ||
    stripos($host, '127.0.0.1') !== false ||
    stripos($host, '::1') !== false ||
    preg_match('/\.local(host)?(:\d+)?$/i', $host) ||
    PHP_SAPI === 'cli'
);

define('APP_ENV', $isLocal ? 'local' : 'production');
define('APP_DEBUG', $isLocal);

// Database configuration (supports environment variable overrides)
if ($isLocal) {
    define('DB_HOST', getenv('SUREXO_DB_HOST') ?: '127.0.0.1');
    define('DB_PORT', getenv('SUREXO_DB_PORT') ?: '3306');
    define('DB_NAME', getenv('SUREXO_DB_NAME') ?: 'surexoglobal_db');
    define('DB_USER', getenv('SUREXO_DB_USER') ?: 'root');
    define('DB_PASS', getenv('SUREXO_DB_PASS') !== false ? getenv('SUREXO_DB_PASS') : '');
} else {
    // Live cPanel MySQL credentials for https://ttbilling.in/surexoglobal/
    define('DB_HOST', getenv('SUREXO_DB_HOST') ?: 'localhost');
    define('DB_PORT', getenv('SUREXO_DB_PORT') ?: '3306');
    define('DB_NAME', getenv('SUREXO_DB_NAME') ?: 'surexoglobal_db');
    define('DB_USER', getenv('SUREXO_DB_USER') ?: 'root');
    define('DB_PASS', getenv('SUREXO_DB_PASS') !== false ? getenv('SUREXO_DB_PASS') : '');
}

define('DB_CHARSET', 'utf8mb4');

// Local filesystem paths (relative to project root — works on both local & live cPanel)
define('API_ROOT_DIR', dirname(__DIR__));
define('PROJECT_ROOT_DIR', dirname(API_ROOT_DIR));
define('UPLOADS_DIR', PROJECT_ROOT_DIR . DIRECTORY_SEPARATOR . 'uploads');
define('UPLOADS_REL_PATH', './uploads');

// Upload constraints
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10 MB
define('ALLOWED_IMAGE_MIMES', [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
]);

// Auth & token settings
define('ADMIN_AUTH_VERSION', 1);
define('TOKEN_TTL_DAYS', 30);

// CORS allowed origins (add extra origins if frontend is hosted separately)
define('CORS_ALLOWED_ORIGINS', [
    'http://localhost',
    'http://localhost:3000',
    'http://localhost:5173',
    'http://localhost:8000',
    'http://localhost:8080',
    'http://127.0.0.1',
    'http://127.0.0.1:5173',
    'http://127.0.0.1:8000',
    'http://127.0.0.1:8080',
    'https://ttbilling.in',
    'https://www.ttbilling.in',
]);

// Timezone
date_default_timezone_set('Asia/Kolkata');

// Error reporting
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '0'); // Always return clean JSON errors, never HTML warnings
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}
