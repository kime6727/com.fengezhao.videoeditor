<?php
/**
 * 用户删除账户接口
 * 符合App Store合规要求，彻底删除用户所有数据
 */

require_once '../../common/db.php';
require_once '../../common/functions.php';
require_once '../../common/response.php';

setCorsHeaders();
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);

$userId = $data['user_id'] ?? '';
$deviceId = $data['device_id'] ?? $_SERVER['HTTP_X_DEVICE_ID'] ?? $_SERVER['HTTP_DEVICE_ID'] ?? '';
$password = $data['password'] ?? '';

if (empty($userId) && empty($deviceId)) {
    echo jsonResponse(400, '用户标识不能为空', null);
    exit;
}

global $pdo;

try {
    $pdo->beginTransaction();

    if (!empty($userId)) {
        $stmt = $pdo->prepare("SELECT `id`, `user_id`, `password`, `user_type` FROM `users` WHERE `user_id` = ? FOR UPDATE");
        $stmt->execute([$userId]);
    } else {
        $stmt = $pdo->prepare("SELECT `id`, `user_id`, `password`, `user_type` FROM `users` WHERE `device_id` = ? AND `user_type` = 0 FOR UPDATE");
        $stmt->execute([$deviceId]);
    }
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $userId = $user['user_id']; // 统一使用查出来的真实 user_id
    }

    if (!$user) {
        $pdo->rollBack();
        echo jsonResponse(404, '用户不存在', null);
        exit;
    }

    if ($user['user_type'] == 1) {
        if (empty($password)) {
            $pdo->rollBack();
            echo jsonResponse(400, '请提供密码以确认删除账户', null);
            exit;
        }

        if (!password_verify($password, $user['password'])) {
            $pdo->rollBack();
            echo jsonResponse(401, '密码错误', null);
            exit;
        }
    }

    $tables = [
        'user_favorites',
        'download_logs',
        'copy_logs',
        'material_reports',
        'user_hidden_materials',
        'subscription_records'
    ];

    foreach ($tables as $table) {
        $stmt = $pdo->prepare("DELETE FROM `{$table}` WHERE `user_id` = ?");
        $stmt->execute([$userId]);
    }

    $stmt = $pdo->prepare("DELETE FROM `users` WHERE `user_id` = ?");
    $stmt->execute([$userId]);

    $pdo->commit();

    echo jsonResponse(200, '账户删除成功', [
        'deleted_user_id' => $userId,
        'deleted_at' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo jsonResponse(500, '删除失败: ' . $e->getMessage(), null);
}
