<?php
/**
 * One-click Database Installer & Seeder (Local CLI or Browser)
 *
 * Usage (CLI):  php api/database/install.php
 * Usage (Web):  http://localhost/surexoglobal/api/database/install.php
 *
 * NOTE: On production servers, delete or restrict access to this file after setup.
 */

require_once dirname(__DIR__) . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = Database::getServerConnection();

    $sqlFile = __DIR__ . '/schema.sql';
    if (!file_exists($sqlFile)) {
        throw new RuntimeException('schema.sql not found at ' . $sqlFile);
    }

    $sql = file_get_contents($sqlFile);
    if ($sql === false) {
        throw new RuntimeException('Failed to read schema.sql');
    }

    // Substitute configured DB_NAME so custom cPanel database names (e.g. ttbillin_surexoglobal) work too
    $dbName = preg_replace('/[^a-zA-Z0-9_]/', '', DB_NAME);
    $sql = str_replace('`surexoglobal_db`', '`' . $dbName . '`', $sql);

    // On shared cPanel hosting, CREATE DATABASE may not be permitted (DB already created via cPanel).
    // Attempt CREATE DATABASE first; if denied, directly USE the configured database.
    try {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    } catch (PDOException $e) {
        // Ignore privilege error when database already exists on cPanel
    }

    $pdo->exec("USE `{$dbName}`");

    // Remove CREATE DATABASE / USE lines before running remaining statements
    $sql = preg_replace('/^\s*CREATE\s+DATABASE\b.*?;$/ims', '', $sql);
    $sql = preg_replace('/^\s*USE\s+`[^`]+`\s*;$/im', '', $sql);

    // Split SQL safely while respecting string literals
    $statements = splitSqlStatements($sql);
    $executed = 0;
    foreach ($statements as $stmt) {
        $trimmed = trim($stmt);
        if ($trimmed === '') {
            continue;
        }
        $pdo->exec($trimmed);
        $executed++;
    }

    echo json_encode([
        'success'     => true,
        'message'     => "Database '{$dbName}' initialized and seeded successfully.",
        'statements'  => $executed,
        'environment' => APP_ENV,
        'adminLogin'  => [
            'email'    => 'admin@surexoglobal.com',
            'password' => 'admin123',
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database installation failed.',
        'error'   => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

/**
 * Split multi-statement SQL file cleanly without breaking quoted strings or comments.
 */
function splitSqlStatements(string $sql): array
{
    $statements = [];
    $current = '';
    $inSingle = false;
    $inDouble = false;
    $inLineComment = false;
    $inBlockComment = false;
    $len = strlen($sql);

    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        $next = ($i + 1 < $len) ? $sql[$i + 1] : '';

        if ($inLineComment) {
            if ($ch === "\n") {
                $inLineComment = false;
            }
            continue;
        }

        if ($inBlockComment) {
            if ($ch === '*' && $next === '/') {
                $inBlockComment = false;
                $i++;
            }
            continue;
        }

        if (!$inSingle && !$inDouble) {
            if ($ch === '-' && $next === '-') {
                $inLineComment = true;
                $i++;
                continue;
            }
            if ($ch === '/' && $next === '*') {
                $inBlockComment = true;
                $i++;
                continue;
            }
        }

        if ($ch === "'" && !$inDouble) {
            if ($inSingle && $i > 0 && $sql[$i - 1] === '\\' && ($i < 2 || $sql[$i - 2] !== '\\')) {
                // Escaped quote inside string
            } else {
                $inSingle = !$inSingle;
            }
        } elseif ($ch === '"' && !$inSingle) {
            if ($inDouble && $i > 0 && $sql[$i - 1] === '\\' && ($i < 2 || $sql[$i - 2] !== '\\')) {
                // Escaped quote
            } else {
                $inDouble = !$inDouble;
            }
        }

        if ($ch === ';' && !$inSingle && !$inDouble) {
            if (trim($current) !== '') {
                $statements[] = $current;
            }
            $current = '';
        } else {
            $current .= $ch;
        }
    }

    if (trim($current) !== '') {
        $statements[] = $current;
    }

    return $statements;
}
