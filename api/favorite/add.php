<?php
/**
 * 添加收藏
 * 改造：添加认证支持，优先从 Token 获取 user_id
 */

require_once '../../common/db.php';
require_once '../../common/functions.php';
require_once '../../common/response.php';
require_once '../../common/auth.php';

setCorsHeaders();
header('Content-Type: application/json; charset=utf-8');

// 认证用户（兼容 device_id）
$user = authenticateUser();
if (!$user) {
    echo jsonResponse(401, '未授权，请先登录', null);
    exit;
}

// 优先使用认证用户的 user_id，也兼容 POST 传入
$data = json_decode(file_get_contents('php://input'), true);
$userId = $user['user_id'];
$materialId = $data['material_id'] ?? '';
$materialType = intval($data['material_type'] ?? 0);

if (empty($materialId) || empty($materialType)) {
    echo jsonResponse(400, '参数不完整', null);
    exit;
}

global $pdo;

// 检查是否已收藏
$stmt = $pdo->prepare("SELECT COUNT(*) FROM `user_favorites` 
                       WHERE `user_id` = ? AND `material_id` = ? AND `material_type` = ?");
$stmt->execute([$userId, $materialId, $materialType]);

if ($stmt->fetchColumn() > 0) {
    echo jsonResponse(400, '已收藏', null);
    exit;
}

// 添加收藏
$stmt = $pdo->prepare("INSERT INTO `user_favorites` (`user_id`, `material_id`, `material_type`) 
                       VALUES (?, ?, ?)");
$result = $stmt->execute([$userId, $materialId, $materialType]);

if ($result) {
    echo jsonResponse(200, '收藏成功', [
        'user_id' => $userId,
        'material_id' => $materialId,
        'material_type' => $materialType
    ]);
} else {
    echo jsonResponse(500, '收藏失败', null);
}
