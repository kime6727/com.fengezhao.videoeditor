<?php
/**
 * 复制文案
 */

require_once '../../common/db.php';
require_once '../../common/functions.php';
require_once '../../common/response.php';

setCorsHeaders();
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);

$userId = $data['user_id'] ?? '';
$materialId = $data['material_id'] ?? '';

if (empty($materialId)) {
    echo jsonResponse(400, '素材ID不能为空', null);
    exit;
}

global $pdo;

// 获取文案内容
$stmt = $pdo->prepare("SELECT `material_id`, `content` FROM `text_materials` 
                       WHERE `material_id` = ? AND `status` = 1");
$stmt->execute([$materialId]);
$material = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$material) {
    echo jsonResponse(404, '文案不存在', null);
    exit;
}

try {
    // 记录复制日志
    if ($userId) {
        $stmt = $pdo->prepare("INSERT INTO `copy_logs` 
                               (`user_id`, `material_id`, `material_type`) 
                               VALUES (?, ?, 4)");
        $stmt->execute([$userId, $materialId]);
    }
    
    // 更新复制次数
    $stmt = $pdo->prepare("UPDATE `text_materials` 
                           SET `copy_count` = `copy_count` + 1 
                           WHERE `material_id` = ?");
    $stmt->execute([$materialId]);
    
    echo jsonResponse(200, '复制成功', [
        'content' => $material['content'],
        'material_id' => $materialId
    ]);
    
} catch (Exception $e) {
    echo jsonResponse(500, '操作失败：' . $e->getMessage(), null);
}
