<?php
/**
 * Dokploy Deployment Diagnostic
 * DELETE THIS FILE after deployment is verified
 */
header('Content-Type: application/json; charset=utf-8');

$result = [
    'status' => 'ok',
    'timestamp' => date('Y-m-d H:i:s'),
    'server' => [
        'php_version' => phpversion(),
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'unknown',
        'hostname' => gethostname(),
        'ip' => gethostbyname(gethostname()),
    ],
    'extensions' => [
        'pdo' => extension_loaded('pdo'),
        'pdo_mysql' => extension_loaded('pdo_mysql'),
        'mysqli' => extension_loaded('mysqli'),
        'mbstring' => extension_loaded('mbstring'),
        'gd' => extension_loaded('gd'),
        'zip' => extension_loaded('zip'),
    ],
    'environment' => [
        'DB_HOST' => getenv('DB_HOST') ?: 'not set',
        'DB_PORT' => getenv('DB_PORT') ?: 'not set',
        'DB_NAME' => getenv('DB_NAME') ?: 'not set',
        'DB_USER' => getenv('DB_USER') ?: 'not set',
    ],
    'database' => null,
    'uploads_dir' => null,
];

// Test DB connection
try {
    $dbConfig = require __DIR__ . '/config/database.php';
    $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $result['database'] = [
        'connected' => true,
        'tables_count' => count($tables),
        'tables' => $tables,
    ];
} catch (Exception $e) {
    $result['database'] = [
        'connected' => false,
        'error' => $e->getMessage(),
    ];
}

// Test uploads dir
$uploadPath = __DIR__ . '/uploads';
$result['uploads_dir'] = [
    'exists' => is_dir($uploadPath),
    'writable' => is_writable($uploadPath),
    'path' => $uploadPath,
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
