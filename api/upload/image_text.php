<?php
/**
 * 用户上传图文素材接口
 */
require_once '../../common/db.php';
require_once '../../common/functions.php';
require_once '../../common/response.php';

setCorsHeaders();
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);

$userId = $data['user_id'] ?? '';
$imageUrls = $data['image_urls'] ?? [];
$contents = $data['contents'] ?? [];
$categoryIds = $data['category_ids'] ?? [];

if (empty($userId)) {
    echo jsonResponse(400, '用户ID不能为空', null);
    exit;
}

if (empty($imageUrls) || empty($contents)) {
    echo jsonResponse(400, '图片和文案不能为空', null);
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
    $materialId = generateUniqueIdWithCheck('image_text_materials', 'material_id');

    // 插入图文素材主表，状态为待审核(2)
    $stmt = $pdo->prepare("INSERT INTO `image_text_materials`
                           (`material_id`, `author_id`, `status`)
                           VALUES (?, ?, 2)");
    $stmt->execute([$materialId, $userId]);

    // 插入图片
    $stmt = $pdo->prepare("INSERT INTO `image_text_images`
                          (`material_id`, `image_url`, `sort`)
                          VALUES (?, ?, ?)");
    foreach ($imageUrls as $index => $imageUrl) {
        $stmt->execute([$materialId, trim($imageUrl), $index]);
    }

    // 插入文案
    $stmt = $pdo->prepare("INSERT INTO `image_text_contents`
                          (`material_id`, `content`, `sort`)
                          VALUES (?, ?, ?)");
    foreach ($contents as $index => $content) {
        $stmt->execute([$materialId, trim($content), $index]);
    }

    // 添加分类关联
    if (!empty($categoryIds)) {
        $stmt = $pdo->prepare("INSERT INTO `category_relations`
                              (`category_id`, `material_id`, `material_type`)
                              VALUES (?, ?, 2)");
        foreach ($categoryIds as $categoryId) {
            $stmt->execute([$categoryId, $materialId]);
        }
    }

    $pdo->commit();

    echo jsonResponse(200, '上传成功，等待审核', [
        'material_id' => $materialId,
        'status' => 2,
        'image_count' => count($imageUrls),
        'content_count' => count($contents)
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo jsonResponse(500, '上传失败：' . $e->getMessage(), null);
}
