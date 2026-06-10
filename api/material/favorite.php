<?php
/**
 * 收藏/取消收藏素材（通用版，支持所有素材类型）
 * 统一了原 video/favorite.php 和 favorite/add.php + remove.php
 * 改造要点：
 * 1. 认证用户身份（Token 或 device_id）
 * 2. 支持所有素材类型
 * 3. 防止重复收藏
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
$materialType = intval($data['material_type'] ?? 0);
$action = $data['action'] ?? 'add'; // add 或 remove

if (empty($materialId) || empty($materialType)) {
    echo jsonResponse(400, '参数不完整', null);
    exit;
}

if (!in_array($materialType, [1, 2, 3, 4])) {
    echo jsonResponse(400, '素材类型无效', null);
    exit;
}

global $pdo;

try {
    if ($action === 'add') {
        // 检查是否已收藏
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `user_favorites`
                               WHERE `user_id` = ? AND `material_id` = ? AND `material_type` = ?");
        $stmt->execute([$userId, $materialId, $materialType]);

        if ($stmt->fetchColumn() > 0) {
            echo jsonResponse(400, '已收藏', [
                'material_id' => $materialId,
                'material_type' => $materialType,
                'is_favorite' => true
            ]);
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
                'material_type' => $materialType,
                'is_favorite' => true
            ]);
        } else {
            echo jsonResponse(500, '收藏失败', null);
        }

    } else {
        // 取消收藏
        $stmt = $pdo->prepare("DELETE FROM `user_favorites`
                               WHERE `user_id` = ? AND `material_id` = ? AND `material_type` = ?");
        $result = $stmt->execute([$userId, $materialId, $materialType]);

        if ($result) {
            echo jsonResponse(200, '取消收藏成功', [
                'user_id' => $userId,
                'material_id' => $materialId,
                'material_type' => $materialType,
                'is_favorite' => false
            ]);
        } else {
            echo jsonResponse(500, '取消收藏失败', null);
        }
    }

} catch (Exception $e) {
    echo jsonResponse(500, '操作失败：' . $e->getMessage(), null);
}