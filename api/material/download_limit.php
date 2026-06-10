<?php
/**
 * 检查下载限制
 * 验证用户是否超出每日下载限制
 * 改造要点：
 * 1. 移除 debug_info 生产响应泄露
 * 2. 添加认证支持（Token 或 device_id）
 * 3. 统一使用 COUNT(DISTINCT) 计数方式
 */

require_once '../../common/db.php';
require_once '../../common/functions.php';
require_once '../../common/response.php';
require_once '../../common/auth.php';

setCorsHeaders();
header('Content-Type: application/json; charset=utf-8');

// 认证用户（兼容 device_id）
$user = authenticateUser();
if (!$user) {
    echo jsonResponse(401, '未授权，请先登录', null);
    exit;
}

$userId = $user['user_id'];
$materialId = $_GET['material_id'] ?? '';
$materialType = intval($_GET['material_type'] ?? 0);

if (empty($materialId) || empty($materialType)) {
    echo jsonResponse(400, '参数不完整', null);
    exit;
}

global $pdo;

// 获取今日下载次数（统一使用 DISTINCT）
function getTodayDownloadCount($userId) {
    global $pdo;
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT material_id) FROM download_logs
                          WHERE user_id = ? AND DATE(created_at) = ?");
    $stmt->execute([$userId, $today]);
    return intval($stmt->fetchColumn());
}

// 记录下载行为
function recordDownload($userId, $materialId, $materialType) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO download_logs (user_id, material_id, material_type, created_at)
                          VALUES (?, ?, ?, NOW())");
    return $stmt->execute([$userId, $materialId, $materialType]);
}

// 检查今日是否已下载过该素材
function hasDownloadedToday($userId, $materialId) {
    global $pdo;
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM download_logs
                          WHERE user_id = ? AND material_id = ? AND DATE(created_at) = ?");
    $stmt->execute([$userId, $materialId, $today]);
    return intval($stmt->fetchColumn()) > 0;
}

try {
    // 检查用户是否为VIP
    $isVIP = isUserVIP($userId);

    // 获取下载限制配置
    $freeUserLimitConfig = getSystemConfig('free_user_daily_limit');
    $vipUserLimitConfig = getSystemConfig('vip_user_daily_limit');
    $enableDownloadLimit = strtolower(strval(getSystemConfig('enable_download_limit', 'true')));

    // 仅记录到日志，不返回给客户端
    if ($freeUserLimitConfig === false || $vipUserLimitConfig === false) {
        error_log("下载限制配置缺失: free_user_daily_limit=" . ($freeUserLimitConfig === false ? 'missing' : $freeUserLimitConfig) . ", vip_user_daily_limit=" . ($vipUserLimitConfig === false ? 'missing' : $vipUserLimitConfig));
        echo jsonResponse(429, '请升级VIP以继续下载', [
            'user_type' => $isVIP ? 'VIP用户' : '普通用户',
            'daily_limit' => 0,
            'today_count' => 0,
            'remaining' => 0,
            'can_download' => false,
            'reset_time' => date('Y-m-d 00:00:00', strtotime('+1 day'))
        ]);
        exit;
    }

    $freeUserLimit = intval($freeUserLimitConfig);
    $vipUserLimit = intval($vipUserLimitConfig);

    // 获取今日已下载次数
    $todayCount = getTodayDownloadCount($userId);
    $hasDownloaded = hasDownloadedToday($userId, $materialId);

    // 确定用户类型和对应限制
    $userLimit = $isVIP ? $vipUserLimit : $freeUserLimit;
    $userType = $isVIP ? 'VIP用户' : '普通用户';

    error_log("下载限制检查: userId=$userId, isVIP=" . ($isVIP ? 'true' : 'false') . ", freeLimit=$freeUserLimit, vipLimit=$vipUserLimit, userLimit=$userLimit, todayCount=$todayCount, materialType=$materialType, hasDownloaded=" . ($hasDownloaded ? 'true' : 'false'));

    // 文本复制不限次
    if ($materialType == 4) {
        echo jsonResponse(200, '文本复制不限次', [
            'user_type' => $userType,
            'daily_limit' => -1,
            'today_count' => $todayCount,
            'remaining' => 9999,
            'can_download' => true,
            'reset_time' => date('Y-m-d 00:00:00', strtotime('+1 day'))
        ]);
        exit;
    }

    // 今日已下载过该素材，不重复扣次
    if ($hasDownloaded) {
        $remaining = ($userLimit > 0) ? ($userLimit - $todayCount) : 9999;
        echo jsonResponse(200, '今日已下载过，不重复扣次', [
            'user_type' => $userType,
            'daily_limit' => $userLimit,
            'today_count' => $todayCount,
            'remaining' => $remaining,
            'can_download' => true,
            'reset_time' => date('Y-m-d 00:00:00', strtotime('+1 day'))
        ]);
        exit;
    }

    // 下载限制未启用（提审合规）
    if ($enableDownloadLimit !== 'true') {
        recordDownload($userId, $materialId, $materialType);
        echo jsonResponse(200, '下载限制未启用', [
            'user_type' => $userType,
            'daily_limit' => -1,
            'today_count' => $todayCount + 1,
            'remaining' => 9999,
            'can_download' => true,
            'reset_time' => date('Y-m-d 00:00:00', strtotime('+1 day'))
        ]);
        exit;
    }

    // 检查是否超出限制
    if ($userLimit > 0 && $todayCount >= $userLimit) {
        echo jsonResponse(429, '您今日下载已达上限，请明天再试', [
            'user_type' => $userType,
            'daily_limit' => $userLimit,
            'today_count' => $todayCount,
            'remaining' => 0,
            'can_download' => false,
            'reset_time' => date('Y-m-d 00:00:00', strtotime('+1 day'))
        ]);
        exit;
    }

    // 记录本次下载
    recordDownload($userId, $materialId, $materialType);

    $remaining = ($userLimit > 0) ? ($userLimit - $todayCount - 1) : 9999;

    echo jsonResponse(200, '下载检查通过', [
        'user_type' => $userType,
        'daily_limit' => $userLimit,
        'today_count' => $todayCount + 1,
        'remaining' => $remaining,
        'can_download' => true,
        'reset_time' => date('Y-m-d 00:00:00', strtotime('+1 day'))
    ]);

} catch (Exception $e) {
    echo jsonResponse(500, '系统错误', null);
}