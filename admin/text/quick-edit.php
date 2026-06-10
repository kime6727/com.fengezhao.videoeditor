<?php
/**
 * 快速编辑文案素材内容
 */
require_once '../common/session.php';
require_once '../../common/db.php';

checkAdminLogin();

global $pdo;

$materialId = $_POST['material_id'] ?? '';
$content = trim($_POST['content'] ?? '');

if (empty($materialId) || empty($content)) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE `text_materials` SET `content` = ? WHERE `material_id` = ?");
    $stmt->execute([$content, $materialId]);

    echo json_encode([
        'success' => true,
        'message' => '更新成功'
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '更新失败：' . $e->getMessage()]);
}
