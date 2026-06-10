<?php
/**
 * 快速编辑分类名称
 */
require_once '../common/session.php';
require_once '../../common/db.php';

checkAdminLogin();

global $pdo;

$categoryId = $_POST['category_id'] ?? '';
$name = trim($_POST['name'] ?? '');

if (empty($categoryId) || empty($name)) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE `categories` SET `name` = ? WHERE `category_id` = ?");
    $stmt->execute([$name, $categoryId]);

    echo json_encode([
        'success' => true,
        'message' => '更新成功'
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '更新失败：' . $e->getMessage()]);
}
