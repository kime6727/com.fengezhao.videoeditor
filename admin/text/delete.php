<?php
/**
 * 删除纯文案素材
 */
require_once '../common/session.php';
require_once '../../common/db.php';

checkAdminLogin();

$materialId = $_GET['id'] ?? '';

if (empty($materialId)) {
    header('Location: list.php');
    exit;
}

global $pdo;

try {
    $pdo->beginTransaction();
    
    // 删除分类关联
    $stmt = $pdo->prepare("DELETE FROM `category_relations` 
                          WHERE `material_id` = ? AND `material_type` = 4");
    $stmt->execute([$materialId]);
    
    // 删除收藏记录
    $stmt = $pdo->prepare("DELETE FROM `user_favorites` 
                          WHERE `material_id` = ? AND `material_type` = 4");
    $stmt->execute([$materialId]);
    
    // 删除复制记录
    $stmt = $pdo->prepare("DELETE FROM `copy_logs` 
                          WHERE `material_id` = ? AND `material_type` = 4");
    $stmt->execute([$materialId]);
    
    // 删除隐藏记录
    $stmt = $pdo->prepare("DELETE FROM `user_hidden_materials` 
                          WHERE `material_id` = ? AND `material_type` = 4");
    $stmt->execute([$materialId]);
    
    // 删除举报记录
    $stmt = $pdo->prepare("DELETE FROM `material_reports` 
                          WHERE `material_id` = ? AND `material_type` = 4");
    $stmt->execute([$materialId]);
    
    // 删除素材
    $stmt = $pdo->prepare("DELETE FROM `text_materials` WHERE `material_id` = ?");
    $stmt->execute([$materialId]);
    
    $pdo->commit();
    header('Location: list.php?msg=删除成功');
    
} catch (Exception $e) {
    $pdo->rollBack();
    header('Location: list.php?msg=删除失败：' . urlencode($e->getMessage()));
}
