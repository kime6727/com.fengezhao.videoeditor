<?php
/**
 * 收藏/取消收藏视频 - 兼容旧客户端
 * 转发到 material/favorite.php（通用版本）
 * 新客户端请直接使用 /api/material/favorite.php
 */

require_once '../../common/db.php';
require_once '../../common/functions.php';
require_once '../../common/response.php';
require_once '../../common/auth.php';

setCorsHeaders();
header('Content-Type: application/json; charset=utf-8');

// 认证用户
$user = authenticateUser();
if (!$user) {
    echo jsonResponse(401, '未授权，请先登录', null);
    exit;
}

$userId = $user['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

$materialId = $data['material_id'] ?? '';
$action = $data['action'] ?? 'add'; // add 或 remove

if (empty($materialId)) {
    echo jsonResponse(400, '参数不完整', null);
    exit;
}

global $pdo;
$materialType = 1; // 视频类型

try {
    if ($action === 'add') {
        // 检查是否已收藏
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `user_favorites` WHERE `user_id` = ? AND `material_id` = ? AND `material_type` = 1");
        $stmt->execute([$userId, $materialId]);

        if ($stmt->fetchColumn() > 0) {
            echo jsonResponse(400, '已收藏', null);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO `user_favorites` (`user_id`, `material_id`, `material_type`) VALUES (?, ?, 1)");
        $result = $stmt->execute([$userId, $materialId]);
        $message = '收藏成功';
    } else {
        $stmt = $pdo->prepare("DELETE FROM `user_favorites` WHERE `user_id` = ? AND `material_id` = ? AND `material_type` = 1");
        $result = $stmt->execute([$userId, $materialId]);
        $message = '取消收藏成功';
    }

    if ($result) {
        echo jsonResponse(200, $message, [
            'user_id' => $userId,
            'material_id' => $materialId,
            'action' => $action
        ]);
    } else {
        echo jsonResponse(500, '操作失败', null);
    }
} catch (Exception $e) {
    echo jsonResponse(500, '操作失败：' . $e->getMessage(), null);
}