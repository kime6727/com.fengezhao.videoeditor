<?php
/**
 * 切换素材状态
 */
require_once __DIR__ . '/../common/session.php';
require_once __DIR__ . '/../../common/db.php';

checkAdminLogin();

global $pdo;

$materialId = $_POST['material_id'] ?? $_GET['material_id'] ?? '';
$status = intval($_POST['status'] ?? $_GET['status'] ?? -1);

if (empty($materialId) || $status < 0) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE `video_materials` SET `status` = ? WHERE `material_id` = ?");
    $stmt->execute([$status, $materialId]);

    $statusText = $status === 1 ? '上架' : '下架';
    echo json_encode([
        'success' => true,
        'message' => "已设置为{$statusText}状态",
        'status' => $status,
        'status_text' => $statusText
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '操作失败：' . $e->getMessage()]);
}