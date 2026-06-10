<?php
/**
 * 快速切换图片+文案素材状态（上架/下架）
 */
require_once '../common/session.php';
require_once '../../common/db.php';

checkAdminLogin();

header('Content-Type: application/json; charset=utf-8');

$materialId = $_POST['material_id'] ?? '';
$status = intval($_POST['status'] ?? -1);

if (empty($materialId) || $status < 0) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

global $pdo;

try {
    $stmt = $pdo->prepare("UPDATE `image_text_materials` SET `status` = ? WHERE `material_id` = ?");
    $stmt->execute([$status, $materialId]);
    
    echo json_encode([
        'success' => true,
        'message' => '状态更新成功',
        'status' => $status,
        'status_text' => $status == 1 ? '上架' : '下架'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '更新失败：' . $e->getMessage()]);
}
