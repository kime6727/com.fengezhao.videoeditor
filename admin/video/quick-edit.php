<?php
/**
 * 快速编辑素材名称
 */
require_once __DIR__ . '/../common/session.php';
require_once __DIR__ . '/../../common/db.php';

checkAdminLogin();

global $pdo;

$materialId = $_POST['material_id'] ?? '';
$name = trim($_POST['name'] ?? '');

if (empty($materialId) || empty($name)) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE `video_materials` SET `name` = ? WHERE `material_id` = ?");
    $stmt->execute([$name, $materialId]);

    echo json_encode([
        'success' => true,
        'message' => '更新成功'
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '更新失败：' . $e->getMessage()]);
}
