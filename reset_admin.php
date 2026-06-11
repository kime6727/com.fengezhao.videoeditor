<?php
/**
 * 重置管理员密码为 admin123
 * 使用后请删除此文件！
 */
require_once __DIR__ . '/common/db.php';

$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    // Check if admin exists
    $stmt = $pdo->prepare("SELECT id, username FROM admins WHERE username = 'admin'");
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin) {
        // Update existing admin
        $stmt = $pdo->prepare("UPDATE admins SET password = ? WHERE username = 'admin'");
        $stmt->execute([$hash]);
        $result = [
            'status' => 'ok',
            'message' => '密码已重置',
            'username' => 'admin',
            'password' => 'admin123',
            'action' => 'updated',
        ];
    } else {
        // Create admin
        $stmt = $pdo->prepare("INSERT INTO admins (username, password, status) VALUES ('admin', ?, 1)");
        $stmt->execute([$hash]);
        $result = [
            'status' => 'ok',
            'message' => '管理员已创建',
            'username' => 'admin',
            'password' => 'admin123',
            'action' => 'created',
        ];
    }

    // Also delete admin_config.php if it exists (might override DB password)
    $configFile = __DIR__ . '/admin/common/admin_config.php';
    if (file_exists($configFile)) {
        unlink($configFile);
        $result['config_deleted'] = true;
    }

} catch (Exception $e) {
    $result = [
        'status' => 'error',
        'message' => $e->getMessage(),
    ];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
