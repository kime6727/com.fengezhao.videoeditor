<?php
/**
 * 获取用户信息接口
 * 支持游客模式和注册用户
 * 新增：返回 Token 用于后续认证
 */

require_once '../../common/db.php';
require_once '../../common/functions.php';
require_once '../../common/response.php';
require_once '../../common/auth.php';

setCorsHeaders();
header('Content-Type: application/json; charset=utf-8');

// 1. 优先尝试 Token 认证
$token = extractTokenFromRequest();
if ($token) {
    $existingUser = verifyToken($token);
    if ($existingUser) {
        // Token 有效，直接返回用户信息 + 新 Token（刷新）
        unset($existingUser['password']);

        // 检查 VIP 状态
        if ($existingUser['is_vip'] == 1 && $existingUser['vip_expire_time']) {
            if (strtotime($existingUser['vip_expire_time']) < time()) {
                global $pdo;
                $stmt = $pdo->prepare("UPDATE `users` SET `is_vip` = 0 WHERE `user_id` = ?");
                $stmt->execute([$existingUser['user_id']]);
                $existingUser['is_vip'] = 0;
            }
        }

        // 获取下载限制信息
        $existingUser['remaining_downloads'] = calculateRemainingDownloads($existingUser['user_id'], $existingUser['is_vip']);

        // 获取 device_id 用于刷新 Token
        $stmt = $pdo->prepare("SELECT `device_id` FROM `users` WHERE `user_id` = ?");
        $stmt->execute([$existingUser['user_id']]);
        $deviceId = $stmt->fetchColumn();

        // 刷新 Token
        $tokenInfo = generateToken($existingUser['user_id'], $deviceId);
        $existingUser['token'] = $tokenInfo['token'];
        $existingUser['token_expires_at'] = $tokenInfo['expires_at'];

        echo jsonResponse(200, '获取成功', $existingUser);
        exit;
    }
}

// 2. 兼容模式：使用 device_id
$deviceId = $_GET['device_id'] ?? $_SERVER['HTTP_DEVICE_ID'] ?? '';

if (empty($deviceId)) {
    echo jsonResponse(400, '设备ID不能为空', null);
    exit;
}

// 获取或创建用户
$user = getOrCreateUserByDevice($deviceId);

if ($user) {
    // 隐藏敏感信息
    unset($user['password']);

    // 检查VIP状态
    if ($user['is_vip'] == 1 && $user['vip_expire_time']) {
        $vipExpired = strtotime($user['vip_expire_time']) < time();
        if ($vipExpired) {
            global $pdo;
            $stmt = $pdo->prepare("UPDATE `users` SET `is_vip` = 0 WHERE `user_id` = ?");
            $stmt->execute([$user['user_id']]);
            $user['is_vip'] = 0;
        }
    }

    // 生成 Token
    $tokenInfo = generateToken($user['user_id'], $deviceId);
    $user['token'] = $tokenInfo['token'];
    $user['token_expires_at'] = $tokenInfo['expires_at'];

    // 计算剩余下载次数
    $user['remaining_downloads'] = calculateRemainingDownloads($user['user_id'], $user['is_vip']);

    echo jsonResponse(200, '获取成功', $user);
} else {
    echo jsonResponse(500, '获取用户信息失败', null);
}

/**
 * 计算剩余下载次数
 */
function calculateRemainingDownloads($userId, $isVip) {
    global $pdo;

    $freeUserLimit = intval(getSystemConfig('free_user_daily_limit', 5));
    $vipUserLimit = intval(getSystemConfig('vip_user_daily_limit', 50));
    $enableDownloadLimit = strtolower(strval(getSystemConfig('enable_download_limit', 'true')));

    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM download_logs WHERE user_id = ? AND DATE(created_at) = ?");
    $stmt->execute([$userId, $today]);
    $todayCount = intval($stmt->fetchColumn());

    if ($enableDownloadLimit !== 'true') {
        return 9999;
    }

    $userLimit = $isVip == 1 ? $vipUserLimit : $freeUserLimit;
    return ($userLimit > 0) ? ($userLimit - $todayCount) : 9999;
}
