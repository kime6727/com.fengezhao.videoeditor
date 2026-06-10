<?php
/**
 * 点赞/取消点赞素材（通用版，支持所有素材类型）
 * 改造要点：
 * 1. 添加 user_likes 表防止重复点赞
 * 2. 认证用户身份（Token 或 device_id）
 * 3. 支持所有素材类型（video/image_text/video_text/text）
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
$materialType = intval($data['material_type'] ?? 1); // 默认视频类型
$action = $data['action'] ?? 'like'; // like 或 unlike

if (empty($materialId)) {
    echo jsonResponse(400, '素材ID不能为空', null);
    exit;
}

if (!in_array($materialType, [1, 2, 3, 4])) {
    echo jsonResponse(400, '素材类型无效', null);
    exit;
}

global $pdo;

// 确保 user_likes 表存在
ensureUserLikesTable();

// 确定素材表名
$tableMap = [
    1 => 'video_materials',
    2 => 'image_text_materials',
    3 => 'video_text_materials',
    4 => 'text_materials'
];
$tableName = $tableMap[$materialType];

// 检查素材是否存在
$stmt = $pdo->prepare("SELECT `material_id`, `like_count` FROM `{$tableName}` WHERE `material_id` = ? AND `status` = 1");
$stmt->execute([$materialId]);
$material = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$material) {
    echo jsonResponse(404, '素材不存在', null);
    exit;
}

try {
    $pdo->beginTransaction();

    if ($action === 'like') {
        // 检查是否已点赞
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `user_likes`
                               WHERE `user_id` = ? AND `material_id` = ? AND `material_type` = ?");
        $stmt->execute([$userId, $materialId, $materialType]);

        if ($stmt->fetchColumn() > 0) {
            $pdo->rollBack();
            echo jsonResponse(400, '已点赞，不可重复操作', [
                'material_id' => $materialId,
                'like_count' => intval($material['like_count']),
                'is_liked' => true
            ]);
            exit;
        }

        // 添加点赞记录
        $stmt = $pdo->prepare("INSERT INTO `user_likes` (`user_id`, `material_id`, `material_type`)
                               VALUES (?, ?, ?)");
        $stmt->execute([$userId, $materialId, $materialType]);

        // 增加点赞计数
        $stmt = $pdo->prepare("UPDATE `{$tableName}` SET `like_count` = `like_count` + 1 WHERE `material_id` = ?");
        $stmt->execute([$materialId]);

    } else {
        // 检查是否已点赞（取消点赞前需确认）
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `user_likes`
                               WHERE `user_id` = ? AND `material_id` = ? AND `material_type` = ?");
        $stmt->execute([$userId, $materialId, $materialType]);

        if ($stmt->fetchColumn() == 0) {
            $pdo->rollBack();
            echo jsonResponse(400, '未点赞，无法取消', [
                'material_id' => $materialId,
                'like_count' => intval($material['like_count']),
                'is_liked' => false
            ]);
            exit;
        }

        // 移除点赞记录
        $stmt = $pdo->prepare("DELETE FROM `user_likes`
                               WHERE `user_id` = ? AND `material_id` = ? AND `material_type` = ?");
        $stmt->execute([$userId, $materialId, $materialType]);

        // 减少点赞计数（不低于0）
        $stmt = $pdo->prepare("UPDATE `{$tableName}` SET `like_count` = GREATEST(`like_count` - 1, 0) WHERE `material_id` = ?");
        $stmt->execute([$materialId]);
    }

    $pdo->commit();

    // 获取更新后的点赞数
    $stmt = $pdo->prepare("SELECT `like_count` FROM `{$tableName}` WHERE `material_id` = ?");
    $stmt->execute([$materialId]);
    $likeCount = intval($stmt->fetchColumn());

    echo jsonResponse(200, '操作成功', [
        'material_id' => $materialId,
        'material_type' => $materialType,
        'like_count' => $likeCount,
        'action' => $action,
        'is_liked' => ($action === 'like')
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo jsonResponse(500, '操作失败：' . $e->getMessage(), null);
}

/**
 * 确保 user_likes 表存在
 */
function ensureUserLikesTable() {
    global $pdo;

    $stmt = $pdo->query("SHOW TABLES LIKE 'user_likes'");
    if ($stmt->fetchColumn() === false) {
        $sql = "CREATE TABLE IF NOT EXISTS `user_likes` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` VARCHAR(8) NOT NULL COMMENT '用户ID',
            `material_id` VARCHAR(32) NOT NULL COMMENT '素材ID',
            `material_type` TINYINT NOT NULL COMMENT '素材类型(1视频/2图文/3视频+文案/4纯文案)',
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '点赞时间',
            UNIQUE KEY `uk_user_material` (`user_id`, `material_id`, `material_type`),
            KEY `idx_material` (`material_id`, `material_type`),
            KEY `idx_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户点赞记录表'";
        $pdo->exec($sql);
    }
}