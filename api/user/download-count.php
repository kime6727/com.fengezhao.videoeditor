<?php
/**
 * 获取用户已下载视频数量（用于判断是否超过2个）
 */

require_once '../../common/db.php';
require_once '../../common/functions.php';
require_once '../../common/response.php';

setCorsHeaders();
header('Content-Type: application/json; charset=utf-8');

$userId = $_GET['user_id'] ?? '';

if (empty($userId)) {
    echo jsonResponse(400, '用户ID不能为空', null);
    exit;
}

$user = getUserById($userId);

if (!$user) {
    echo jsonResponse(404, '用户不存在', null);
    exit;
}

// 检查VIP状态
$isVip = false;
if ($user['is_vip'] == 1 && $user['vip_expire_time']) {
    $isVip = strtotime($user['vip_expire_time']) >= time();
}

echo jsonResponse(200, '获取成功', [
    'user_id' => $user['user_id'],
    'download_count' => $user['download_count'],
    'is_vip' => $isVip,
    'can_download' => $isVip || $user['download_count'] < 2,
    'max_download' => $isVip ? -1 : 2  // -1表示无限制
]);
