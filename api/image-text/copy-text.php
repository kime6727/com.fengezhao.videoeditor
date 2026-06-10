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
$contentId = $data['content_id'] ?? '';

if (empty($materialId)) {
    echo jsonResponse(400, '素材ID不能为空', null);
    exit;
}

global $pdo;

// 获取文案内容
if ($contentId) {
    $stmt = $pdo->prepare("SELECT `id`, `content` FROM `image_text_contents` 
                           WHERE `id` = ? AND `material_id` = ?");
    $stmt->execute([$contentId, $materialId]);
} else {
    // 如果没有指定content_id，随机返回一条
    $stmt = $pdo->prepare("SELECT `id`, `content` FROM `image_text_contents` 
                           WHERE `material_id` = ? 
                           ORDER BY RAND() LIMIT 1");
    $stmt->execute([$materialId]);
}

$content = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$content) {
    echo jsonResponse(404, '文案不存在', null);
    exit;
}

try {
    // 记录复制日志
    if ($userId) {
        $stmt = $pdo->prepare("INSERT INTO `copy_logs` 
                               (`user_id`, `material_id`, `material_type`, `content_id`) 
                               VALUES (?, ?, 2, ?)");
        $stmt->execute([$userId, $materialId, $contentId]);
    }
    
    echo jsonResponse(200, '复制成功', [
        'content' => $content['content'],
        'content_id' => $content['id']
    ]);
    
} catch (Exception $e) {
    echo jsonResponse(500, '操作失败：' . $e->getMessage(), null);
}
