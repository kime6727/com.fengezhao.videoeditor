<?php
/**
 * 用户登录接口
 */

require_once '../../common/db.php';
require_once '../../common/functions.php';
require_once '../../common/response.php';

setCorsHeaders();
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);

$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

if (empty($username) || empty($password)) {
    echo jsonResponse(400, '用户名和密码不能为空', null);
    exit;
}

global $pdo;

// 查询用户（支持用户名或手机号登录）
$stmt = $pdo->prepare("SELECT `id`, `user_id`, `username`, `password`, `phone`, `email`,
                              `avatar`, `is_vip`, `vip_expire_time`,
                              `download_count`, `user_type`, `created_at`, `updated_at`
                       FROM `users`
                       WHERE (`username` = ? OR `phone` = ?) AND `user_type` = 1");
$stmt->execute([$username, $username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo jsonResponse(404, '用户不存在', null);
    exit;
}

// 验证密码
if (!password_verify($password, $user['password'])) {
    echo jsonResponse(401, '密码错误', null);
    exit;
}

// 隐藏敏感信息
unset($user['password']);

echo jsonResponse(200, '登录成功', $user);
