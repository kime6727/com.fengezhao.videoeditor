<?php
/**
 * 删除收藏记录
 */
require_once '../common/session.php';
require_once '../../common/db.php';
require_once '../../common/functions.php';

checkAdminLogin();

global $pdo;

$id = intval($_GET['id'] ?? 0);

if (empty($id)) {
    header('Location: list.php');
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM `user_favorites` WHERE `id` = ?");
    $stmt->execute([$id]);

    header('Location: list.php?success=删除成功');
    exit;
} catch (Exception $e) {
    header('Location: list.php?error=删除失败：' . urlencode($e->getMessage()));
    exit;
}

