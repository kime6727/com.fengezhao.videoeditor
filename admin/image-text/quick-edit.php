<?php
/**
 * 快速编辑图片+文案素材（更新第一张图片URL）
 */
require_once '../common/session.php';
require_once '../../common/db.php';

checkAdminLogin();

global $pdo;

$materialId = $_POST['material_id'] ?? '';
$imageUrl = trim($_POST['image_url'] ?? '');

if (empty($materialId)) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE `image_text_images` SET `image_url` = ? 
                          WHERE `material_id` = ? ORDER BY `sort` ASC LIMIT 1");
    $stmt->execute([$imageUrl, $materialId]);

    echo json_encode([
        'success' => true,
        'message' => '更新成功'
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '更新失败：' . $e->getMessage()]);
}
