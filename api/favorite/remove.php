<?php
/**
 * 取消收藏
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

// 优先使用认证用户的 user_id
$data = json_decode(file_get_contents('php://input'), true);
$userId = $user['user_id'];
$materialId = $data['material_id'] ?? '';
$materialType = intval($data['material_type'] ?? 0);

if (empty($materialId) || empty($materialType)) {
    echo jsonResponse(400, '参数不完整', null);
    exit;
}

global $pdo;

$stmt = $pdo->prepare("DELETE FROM `user_favorites` 
                       WHERE `user_id` = ? AND `material_id` = ? AND `material_type` = ?");
$result = $stmt->execute([$userId, $materialId, $materialType]);

if ($result) {
    echo jsonResponse(200, '取消收藏成功', null);
} else {
    echo jsonResponse(500, '取消收藏失败', null);
}
