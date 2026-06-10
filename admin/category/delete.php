<?php
/**
 * 删除分类
 */
require_once '../common/session.php';
require_once '../../common/db.php';

checkAdminLogin();

$categoryId = $_GET['id'] ?? '';

if (empty($categoryId)) {
    header('Location: list.php');
    exit;
}

global $pdo;

try {
    // 检查是否有素材使用此分类
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `category_relations` WHERE `category_id` = ?");
    $stmt->execute([$categoryId]);
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        header('Location: list.php?msg=该分类下还有素材，无法删除');
        exit;
    }
    
    // 删除分类
    $stmt = $pdo->prepare("DELETE FROM `categories` WHERE `category_id` = ?");
    $stmt->execute([$categoryId]);
    
    header('Location: list.php?msg=删除成功');
    
} catch (Exception $e) {
    header('Location: list.php?msg=删除失败：' . urlencode($e->getMessage()));
}
