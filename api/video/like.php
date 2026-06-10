<?php
/**
 * 点赞视频 - 兼容旧客户端
 * 转发到 material/like.php（通用版本）
 * 新客户端请直接使用 /api/material/like.php
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
$action = $data['action'] ?? 'like';

if (empty($materialId)) {
    echo jsonResponse(400, '素材ID不能为空', null);
    exit;
}

$materialType = 1; // 视频类型

global $pdo;

// 确保 user_likes 表存在
ensureUserLikesTableCompat();

// 检查视频是否存在
$stmt = $pdo->prepare("SELECT `material_id`, `like_count` FROM `video_materials` WHERE `material_id` = ? AND `status` = 1");
$stmt->execute([$materialId]);
$video = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$video) {
    echo jsonResponse(404, '视频不存在', null);
    exit;
}

try {
    $pdo->beginTransaction();

    if ($action === 'like') {
        // 检查是否已点赞
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `user_likes` WHERE `user_id` = ? AND `material_id` = ? AND `material_type` = ?");
        $stmt->execute([$userId, $materialId, $materialType]);

        if ($stmt->fetchColumn() > 0) {
            $pdo->rollBack();
            // 返回旧格式响应（兼容旧客户端）
            echo jsonResponse(200, '已点赞', [
                'material_id' => $materialId,
                'like_count' => intval($video['like_count']),
                'action' => $action
            ]);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO `user_likes` (`user_id`, `material_id`, `material_type`) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $materialId, $materialType]);

        $stmt = $pdo->prepare("UPDATE `video_materials` SET `like_count` = `like_count` + 1 WHERE `material_id` = ?");
        $stmt->execute([$materialId]);
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `user_likes` WHERE `user_id` = ? AND `material_id` = ? AND `material_type` = ?");
        $stmt->execute([$userId, $materialId, $materialType]);

        if ($stmt->fetchColumn() == 0) {
            $pdo->rollBack();
            echo jsonResponse(200, '未点赞', [
                'material_id' => $materialId,
                'like_count' => intval($video['like_count']),
                'action' => $action
            ]);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM `user_likes` WHERE `user_id` = ? AND `material_id` = ? AND `material_type` = ?");
        $stmt->execute([$userId, $materialId, $materialType]);

        $stmt = $pdo->prepare("UPDATE `video_materials` SET `like_count` = GREATEST(`like_count` - 1, 0) WHERE `material_id` = ?");
        $stmt->execute([$materialId]);
    }

    $pdo->commit();

    $stmt = $pdo->prepare("SELECT `like_count` FROM `video_materials` WHERE `material_id` = ?");
    $stmt->execute([$materialId]);
    $likeCount = intval($stmt->fetchColumn());

    echo jsonResponse(200, '操作成功', [
        'material_id' => $materialId,
        'like_count' => $likeCount,
        'action' => $action
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo jsonResponse(500, '操作失败：' . $e->getMessage(), null);
}

function ensureUserLikesTableCompat() {
    global $pdo;
    $stmt = $pdo->query("SHOW TABLES LIKE 'user_likes'");
    if ($stmt->fetchColumn() === false) {
        $sql = "CREATE TABLE IF NOT EXISTS `user_likes` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` VARCHAR(8) NOT NULL,
            `material_id` VARCHAR(32) NOT NULL,
            `material_type` TINYINT NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_user_material` (`user_id`, `material_id`, `material_type`),
            KEY `idx_material` (`material_id`, `material_type`),
            KEY `idx_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $pdo->exec($sql);
    }
}