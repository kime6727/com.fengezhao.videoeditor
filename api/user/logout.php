<?php
/**
 * 用户退出登录接口
 */

require_once '../../common/db.php';
require_once '../../common/functions.php';
require_once '../../common/response.php';

setCorsHeaders();
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);

$userId = $data['user_id'] ?? '';

if (empty($userId)) {
    echo jsonResponse(400, '用户ID不能为空', null);
    exit;
}

global $pdo;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE `user_id` = ?");
$stmt->execute([$userId]);

if ($stmt->fetchColumn() == 0) {
    echo jsonResponse(404, '用户不存在', null);
    exit;
}

echo jsonResponse(200, '退出成功', null);
