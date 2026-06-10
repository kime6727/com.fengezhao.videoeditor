<?php
/**
 * 删除Banner
 */
require_once '../common/session.php';
require_once '../../common/db.php';

checkAdminLogin();

$bannerId = $_GET['id'] ?? '';

if (empty($bannerId)) {
    header('Location: list.php');
    exit;
}

global $pdo;

try {
    $stmt = $pdo->prepare("DELETE FROM `banners` WHERE `banner_id` = ?");
    $stmt->execute([$bannerId]);
    
    header('Location: list.php?msg=删除成功');
    
} catch (Exception $e) {
    header('Location: list.php?msg=删除失败：' . urlencode($e->getMessage()));
}
