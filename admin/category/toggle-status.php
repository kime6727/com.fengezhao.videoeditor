<?php
/**
 * 切换分类状态
 */
require_once '../common/session.php';
require_once '../../common/db.php';

checkAdminLogin();

global $pdo;

$categoryId = $_POST['category_id'] ?? $_GET['category_id'] ?? '';
$status = intval($_POST['status'] ?? $_GET['status'] ?? -1);

if (empty($categoryId) || $status < 0) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE `categories` SET `status` = ? WHERE `category_id` = ?");
    $stmt->execute([$status, $categoryId]);

    $statusText = $status === 1 ? '启用' : '禁用';
    echo json_encode([
        'success' => true,
        'message' => "已设置为{$statusText}状态",
        'status' => $status,
        'status_text' => $statusText
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '操作失败：' . $e->getMessage()]);
}







