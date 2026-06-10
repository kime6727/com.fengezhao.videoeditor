<?php
/**
 * 数据库连接文件
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/config.php';

$dbConfig = require dirname(__DIR__) . '/config/database.php';

try {
    $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
} catch (PDOException $e) {
    die(json_encode([
        'code' => 500,
        'message' => '数据库连接失败：' . $e->getMessage(),
        'data' => null
    ], JSON_UNESCAPED_UNICODE));
}