<?php
/**
 * 快速编辑视频+文案素材（更新视频URL）
 */
require_once '../common/session.php';
require_once '../../common/db.php';

checkAdminLogin();

global $pdo;

$materialId = $_POST['material_id'] ?? '';
$videoUrl = trim($_POST['video_url'] ?? '');

if (empty($materialId) || empty($videoUrl)) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE `video_text_materials` SET `video_url` = ? WHERE `material_id` = ?");
    $stmt->execute([$videoUrl, $materialId]);

    echo json_encode([
        'success' => true,
        'message' => '更新成功'
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '更新失败：' . $e->getMessage()]);
}
