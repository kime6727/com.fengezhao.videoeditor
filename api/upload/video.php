<?php
/**
 * 用户上传视频素材接口
 */
require_once '../../common/db.php';
require_once '../../common/functions.php';
require_once '../../common/response.php';

setCorsHeaders();
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);

$userId = $data['user_id'] ?? '';
$name = trim($data['name'] ?? '');
$videoUrl = trim($data['video_url'] ?? '');
$thumbnailUrl = trim($data['thumbnail_url'] ?? '');
$categoryIds = $data['category_ids'] ?? [];

if (empty($userId)) {
    echo jsonResponse(400, '用户ID不能为空', null);
    exit;
}

if (empty($name) || empty($videoUrl)) {
    echo jsonResponse(400, '名称和视频URL不能为空', null);
    exit;
}

try {
    global $pdo;

    // 验证用户是否存在
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE `user_id` = ?");
    $stmt->execute([$userId]);
    if ($stmt->fetchColumn() == 0) {
        echo jsonResponse(404, '用户不存在', null);
        exit;
    }

    $pdo->beginTransaction();

    // 生成素材ID
    $materialId = generateUniqueIdWithCheck('video_materials', 'material_id');

    // 插入视频素材，状态为待审核(2)
    $stmt = $pdo->prepare("INSERT INTO `video_materials`
                           (`material_id`, `author_id`, `name`, `video_url`, `thumbnail_url`, `status`)
                           VALUES (?, ?, ?, ?, ?, 2)");
    $stmt->execute([$materialId, $userId, $name, $videoUrl, $thumbnailUrl]);

    // 添加分类关联
    if (!empty($categoryIds)) {
        $stmt = $pdo->prepare("INSERT INTO `category_relations`
                              (`category_id`, `material_id`, `material_type`)
                              VALUES (?, ?, 1)");
        foreach ($categoryIds as $categoryId) {
            $stmt->execute([$categoryId, $materialId]);
        }
    }

    $pdo->commit();

    echo jsonResponse(200, '上传成功，等待审核', [
        'material_id' => $materialId,
        'status' => 2,
        'name' => $name
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo jsonResponse(500, '上传失败：' . $e->getMessage(), null);
}
