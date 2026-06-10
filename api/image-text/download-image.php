<?php
/**
 * 下载图片
 */

require_once '../../common/db.php';
require_once '../../common/functions.php';
require_once '../../common/response.php';

setCorsHeaders();
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);

$userId = $data['user_id'] ?? '';
$materialId = $data['material_id'] ?? '';
$imageUrl = $data['image_url'] ?? '';

if (empty($materialId) || empty($imageUrl)) {
    echo jsonResponse(400, '参数不完整', null);
    exit;
}

global $pdo;

// 检查素材是否存在
$stmt = $pdo->prepare("SELECT `material_id` FROM `image_text_materials`
                       WHERE `material_id` = ? AND `status` = 1");
$stmt->execute([$materialId]);
$material = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$material) {
    echo jsonResponse(404, '素材不存在', null);
    exit;
}

// 检查图片是否属于该素材
$stmt = $pdo->prepare("SELECT COUNT(*) FROM `image_text_images`
                       WHERE `material_id` = ? AND `image_url` = ?");
$stmt->execute([$materialId, $imageUrl]);

if ($stmt->fetchColumn() == 0) {
    echo jsonResponse(400, '图片不属于该素材', null);
    exit;
}

// 获取用户信息（如果提供了userId）
$user = null;
$isVIP = false;
if ($userId) {
    $user = getUserById($userId);
    if ($user) {
        // 检查是否为VIP（基于苹果订阅状态）
        $isVIP = isUserVIP($userId);
    }
}

// 获取系统配置
function getSystemConfig($key, $defaultValue = null) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT config_value FROM system_config WHERE config_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetchColumn();
    return $result !== false ? $result : $defaultValue;
}

$freeUserLimitConfig = getSystemConfig('free_user_daily_limit');
$vipUserLimitConfig = getSystemConfig('vip_user_daily_limit');
$enableDownloadLimit = strtolower(strval(getSystemConfig('enable_download_limit', 'true')));

// 如果配置不存在，直接触发paywall
if ($freeUserLimitConfig === false || $vipUserLimitConfig === false) {
    error_log("下载限制配置缺失: free_user_daily_limit=" . ($freeUserLimitConfig === false ? 'missing' : $freeUserLimitConfig) . ", vip_user_daily_limit=" . ($vipUserLimitConfig === false ? 'missing' : $vipUserLimitConfig));
    echo jsonResponse(429, '请升级VIP以继续下载', [
        'user_type' => $isVIP ? 'VIP用户' : '普通用户',
        'daily_limit' => 0,
        'today_count' => 0,
        'remaining' => 0
    ]);
    exit;
}

$freeUserLimit = intval($freeUserLimitConfig);
$vipUserLimit = intval($vipUserLimitConfig);

// 检查下载限制（必须检查，确保A值生效）
// 如果提供了userId，必须检查限制；如果没有userId，拒绝下载（更安全）
if (!$userId) {
    echo jsonResponse(401, '用户未登录，无法下载', null);
    exit;
}

if ($enableDownloadLimit === 'true') {
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM download_logs
                          WHERE user_id = ? AND DATE(created_at) = ?");
    $stmt->execute([$userId, $today]);
    $todayCount = intval($stmt->fetchColumn());

    $userLimit = $isVIP ? $vipUserLimit : $freeUserLimit;

    // 调试日志
    error_log("image-text下载限制检查: userId=$userId, isVIP=" . ($isVIP ? 'true' : 'false') . ", freeLimit=$freeUserLimit, vipLimit=$vipUserLimit, userLimit=$userLimit, todayCount=$todayCount");

    // 检查限制（如果 userLimit 为 0，表示无限制）
    if ($userLimit > 0 && $todayCount >= $userLimit) {
        error_log("image-text下载限制检查失败: userId=$userId, userLimit=$userLimit, todayCount=$todayCount");
        echo jsonResponse(429, '您今日下载已达上限，请明天再试', [
            'user_type' => $isVIP ? 'VIP用户' : '普通用户',
            'daily_limit' => $userLimit,
            'today_count' => $todayCount,
            'remaining' => 0
        ]);
        exit;
    }
    error_log("image-text下载限制检查通过: userId=$userId, remaining=" . ($userLimit > 0 ? ($userLimit - $todayCount) : 9999));
}

try {
    if ($userId) {
        // 记录下载日志
        $stmt = $pdo->prepare("INSERT INTO `download_logs`
                               (`user_id`, `material_id`, `material_type`, `download_type`)
                               VALUES (?, ?, 2, 'image')");
        $stmt->execute([$userId, $materialId]);

        // 更新素材下载次数
        $stmt = $pdo->prepare("UPDATE `image_text_materials`
                               SET `download_count` = `download_count` + 1
                               WHERE `material_id` = ?");
        $stmt->execute([$materialId]);
    }

    echo jsonResponse(200, '下载成功', [
        'image_url' => absoluteMediaUrl($imageUrl),
        'material_id' => $materialId
    ]);

} catch (Exception $e) {
    echo jsonResponse(500, '下载失败：' . $e->getMessage(), null);
}
