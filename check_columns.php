<?php
// Temporary diagnostic script - check & fix table columns
header('Content-Type: application/json; charset=utf-8');

$dbConfig = require __DIR__ . '/config/database.php';
$dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";

try {
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $dbConfig['options']);
    
    // Check columns for all material tables
    $tables = ['video_materials', 'image_text_materials', 'video_text_materials', 'text_materials', 'users'];
    $result = [];
    
    foreach ($tables as $table) {
        $stmt = $pdo->query("DESCRIBE `$table`");
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $result[$table] = array_map(fn($c) => $c['Field'] . ' (' . $c['Type'] . ')', $cols);
    }
    
    // Try running the ALTER TABLE manually
    $alterResult = [];
    try {
        $pdo->exec("ALTER TABLE `video_materials` ADD COLUMN `author_id` varchar(64) DEFAULT NULL COMMENT '发布者用户ID' AFTER `material_id`, ADD KEY `author_id` (`author_id`)");
        $alterResult['video_materials'] = 'author_id added';
    } catch (Exception $e) {
        $alterResult['video_materials'] = $e->getMessage();
    }
    
    try {
        $pdo->exec("ALTER TABLE `image_text_materials` ADD COLUMN `author_id` varchar(64) DEFAULT NULL COMMENT '发布者用户ID' AFTER `material_id`, ADD KEY `author_id_img` (`author_id`)");
        $alterResult['image_text_materials'] = 'author_id added';
    } catch (Exception $e) {
        $alterResult['image_text_materials'] = $e->getMessage();
    }
    
    try {
        $pdo->exec("ALTER TABLE `video_text_materials` ADD COLUMN `author_id` varchar(64) DEFAULT NULL COMMENT '发布者用户ID' AFTER `material_id`, ADD KEY `author_id_vt` (`author_id`)");
        $alterResult['video_text_materials'] = 'author_id added';
    } catch (Exception $e) {
        $alterResult['video_text_materials'] = $e->getMessage();
    }
    
    try {
        $pdo->exec("ALTER TABLE `text_materials` ADD COLUMN `author_id` varchar(64) DEFAULT NULL COMMENT '发布者用户ID' AFTER `material_id`, ADD KEY `author_id_txt` (`author_id`)");
        $alterResult['text_materials'] = 'author_id added';
    } catch (Exception $e) {
        $alterResult['text_materials'] = $e->getMessage();
    }
    
    // Check if default user exists
    try {
        $stmt = $pdo->prepare("SELECT user_id, username FROM users WHERE user_id = ?");
        $stmt->execute(['00000000']);
        $defaultUser = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$defaultUser) {
            $pdo->exec("INSERT INTO `users` (`user_id`, `username`, `password`, `device_id`, `phone`, `email`, `avatar`, `is_vip`, `user_type`, `platform`, `created_at`, `updated_at`) VALUES ('00000000', '好素材官方', '', NULL, NULL, NULL, NULL, 0, 1, 'system', NOW(), NOW())");
            $alterResult['default_user'] = 'created';
        } else {
            $alterResult['default_user'] = 'exists: ' . json_encode($defaultUser);
        }
    } catch (Exception $e) {
        $alterResult['default_user'] = $e->getMessage();
    }
    
    // Re-check columns after alter
    $afterResult = [];
    foreach ($tables as $table) {
        $stmt = $pdo->query("DESCRIBE `$table`");
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $afterResult[$table] = array_map(fn($c) => $c['Field'], $cols);
    }
    
    echo json_encode([
        'before' => $result,
        'alter_results' => $alterResult,
        'after' => $afterResult,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()], JSON_PRETTY_PRINT);
}
