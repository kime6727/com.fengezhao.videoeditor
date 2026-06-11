<?php
/**
 * 公共函数库
 */

require_once __DIR__ . '/db.php';

function absoluteMediaUrl($url) {
    if (!$url) {
        return $url;
    }

    if (preg_match('#^https?://#i', $url)) {
        return $url;
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

    if (strpos($url, '//') === 0) {
        return $scheme . ':' . $url;
    }

    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (!$host) {
        return $url;
    }

    if ($url[0] !== '/') {
        $url = '/' . $url;
    }

    return $scheme . '://' . $host . $url;
}

/**
 * 生成8位数字用户ID（10000000 - 99999999）
 * @return string 8位数字ID
 */
function generateUserId() {
    $min = 10000000;
    $max = 99999999;
    return (string)rand($min, $max);
}

/**
 * 生成唯一ID（32位随机字符串）- 用于素材和分类
 * @param string $prefix 前缀（可选）
 * @return string 唯一ID
 */
function generateUniqueId($prefix = '') {
    $timestamp = time();
    $random = bin2hex(random_bytes(8)); // 16位随机字符串
    $uniqueId = $prefix . $timestamp . $random;

    if (strlen($uniqueId) > 32) {
        $uniqueId = substr($uniqueId, 0, 32);
    }

    return $uniqueId;
}

/**
 * 生成素材唯一ID（32位）
 * @return string 32位唯一ID
 */
function generateMaterialId() {
    $timestamp = time();
    $random = bin2hex(random_bytes(11));
    return $timestamp . $random;
}

/**
 * 生成分类唯一ID（32位）
 * @return string 32位唯一ID
 */
function generateCategoryId() {
    return generateMaterialId();
}

/**
 * 检查用户ID是否已存在
 * @param string $userId 8位数字用户ID
 * @return bool true-存在 false-不存在
 */
function checkUserIdExists($userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE `user_id` = ?");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn() > 0;
}

/**
 * 检查ID是否已存在（通用）
 * @param string $id 要检查的ID
 * @param string $table 表名
 * @param string $idField ID字段名
 * @return bool true-存在 false-不存在
 */
function checkIdExists($id, $table, $idField) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `{$table}` WHERE `{$idField}` = ?");
    $stmt->execute([$id]);
    return $stmt->fetchColumn() > 0;
}

/**
 * 生成并确保唯一的8位数字用户ID
 * @return string 8位数字用户ID
 */
function generateUniqueUserId() {
    $maxAttempts = 100;
    $attempts = 0;

    do {
        $userId = generateUserId();
        $exists = checkUserIdExists($userId);
        $attempts++;

        if ($attempts >= $maxAttempts) {
            // 如果100次都重复，使用时间戳后8位 + 随机数
            $timestamp = time();
            $random = rand(0, 99);
            $userId = substr($timestamp, -6) . str_pad($random, 2, '0', STR_PAD_LEFT);

            if (checkUserIdExists($userId)) {
                $userId = substr($timestamp, -5) . rand(100, 999);
            }
        }
    } while ($exists && $attempts < $maxAttempts);

    return $userId;
}

/**
 * 生成并确保唯一的ID（用于素材和分类）
 * @param string $table 表名
 * @param string $idField ID字段名
 * @param string $prefix 前缀（可选）
 * @return string 唯一ID
 */
function generateUniqueIdWithCheck($table, $idField, $prefix = '') {
    global $pdo;

    // 特殊处理分类ID：使用001、002、003...递增
    if ($table === 'categories' && $idField === 'category_id') {
        return generateIncrementalCategoryId();
    }

    // 其他表使用原有逻辑
    $maxAttempts = 10;
    $attempts = 0;

    do {
        $id = $prefix ? generateUniqueId($prefix) : generateMaterialId();
        $exists = checkIdExists($id, $table, $idField);
        $attempts++;

        if ($attempts >= $maxAttempts) {
            $id = $prefix . time() . bin2hex(random_bytes(16));
        }
    } while ($exists && $attempts < $maxAttempts);

    return $id;
}

/**
 * 生成递增的分类ID（id1、id2、id3...id999）
 * @return string 格式化的分类ID
 */
function generateIncrementalCategoryId() {
    global $pdo;

    // 获取当前最大的ID（支持id1格式和纯数字格式）
    $stmt = $pdo->query("SELECT category_id FROM categories ORDER BY
                        CASE
                            WHEN category_id REGEXP '^id[0-9]+$' THEN CAST(SUBSTRING(category_id, 3) AS UNSIGNED)
                            WHEN category_id REGEXP '^[0-9]+$' THEN CAST(category_id AS UNSIGNED)
                            ELSE 0
                        END DESC LIMIT 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        $currentId = $result['category_id'];
        // 如果是id1格式，提取数字部分
        if (preg_match('/^id(\d+)$/i', $currentId, $matches)) {
            $nextId = intval($matches[1]) + 1;
        }
        // 如果是纯数字格式，直接转换
        elseif (is_numeric($currentId)) {
            $nextId = intval($currentId) + 1;
        }
        // 其他格式，从1开始
        else {
            $nextId = 1;
        }
    } else {
        $nextId = 1;
    }

    // 限制最大值为999
    if ($nextId > 999) {
        $nextId = 999;
    }

    // 格式化为 id1, id2, id3... id999
    return 'id' . $nextId;
}



/**
 * 用户注册（生成8位数字用户ID）
 * @param string $username 用户名
 * @param string $password 密码
 * @param string $phone 手机号（可选）
 * @param string $email 邮箱（可选）
 * @return array|false 成功返回用户信息，失败返回false
 */
function registerUser($username, $password, $phone = null, $email = null) {
    global $pdo;

    // 检查用户名是否已存在
    if ($username) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE `username` = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() > 0) {
            return ['error' => '用户名已存在'];
        }
    }

    // 检查手机号是否已存在
    if ($phone) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE `phone` = ?");
        $stmt->execute([$phone]);
        if ($stmt->fetchColumn() > 0) {
            return ['error' => '手机号已存在'];
        }
    }

    // 生成8位数字用户ID
    $userId = generateUniqueUserId();

    // 加密密码
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO `users` (`user_id`, `username`, `password`, `phone`, `email`, `user_type`)
            VALUES (?, ?, ?, ?, ?, 1)";

    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$userId, $username, $hashedPassword, $phone, $email]);

    if ($result) {
        return [
            'user_id' => $userId,
            'username' => $username,
            'user_type' => 1
        ];
    }

    return false;
}

/**
 * 创建游客账户
 * @param string $deviceId 设备ID
 * @return array|false 用户信息或false
 */
function createGuestUser($deviceId) {
    global $pdo;

    // 检查设备ID是否已有游客账户
    $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `device_id` = ? AND `user_type` = 0");
    $stmt->execute([$deviceId]);
    $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingUser) {
        // 获取系统配置（必须从运营后台获取，无默认值）
        $freeUserLimitConfig = getSystemConfig('free_user_daily_limit');
        $vipUserLimitConfig = getSystemConfig('vip_user_daily_limit');
        $enableDownloadLimit = strtolower(strval(getSystemConfig('enable_download_limit', 'true')));

        // 如果配置不存在，返回0剩余下载次数
        if ($freeUserLimitConfig === false || $vipUserLimitConfig === false) {
            $remaining = 0;
        } else {
            $freeUserLimit = intval($freeUserLimitConfig);
            $vipUserLimit = intval($vipUserLimitConfig);

            // 获取今日下载次数
            $today = date('Y-m-d');
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM download_logs WHERE user_id = ? AND DATE(created_at) = ?");
            $stmt->execute([$existingUser['user_id'], $today]);
            $todayCount = intval($stmt->fetchColumn());

            // 计算剩余下载次数
            if ($enableDownloadLimit !== 'true') {
                $remaining = 9999;
            } else {
                $userLimit = $existingUser['is_vip'] == 1 ? $vipUserLimit : $freeUserLimit;
                $remaining = ($userLimit > 0) ? ($userLimit - $todayCount) : 9999;
            }
        }

        // 返回现有的游客账户
        return [
            'id' => (int)$existingUser['id'],
            'user_id' => $existingUser['user_id'],
            'device_id' => $existingUser['device_id'],
            'username' => $existingUser['username'],
            'is_vip' => (int)$existingUser['is_vip'],
            'vip_expire_time' => $existingUser['vip_expire_time'],
            'download_count' => (int)$existingUser['download_count'],
            'user_type' => (int)$existingUser['user_type'],
            'remaining_downloads' => $remaining,
            'created_at' => $existingUser['created_at'],
            'updated_at' => $existingUser['updated_at']
        ];
    }

    // 生成8位数字用户ID
    $userId = generateUniqueUserId();
    $username = 'guest_' . substr($deviceId, 0, 8);
    $password = substr(md5($deviceId), 0, 10);
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO `users` (`user_id`, `username`, `password`, `device_id`, `user_type`)
            VALUES (?, ?, ?, ?, 0)";

    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$userId, $username, $hashedPassword, $deviceId]);

    if ($result) {
        // 获取系统配置
        $freeUserLimit = intval(getSystemConfig('free_user_daily_limit', 5));
        $vipUserLimit = intval(getSystemConfig('vip_user_daily_limit', 50));
        $enableDownloadLimit = strtolower(strval(getSystemConfig('enable_download_limit', 'true')));

        // 计算剩余下载次数
        if ($enableDownloadLimit !== 'true') {
            $remaining = 9999;
        } else {
            $remaining = $freeUserLimit;
        }

        return [
            'id' => $pdo->lastInsertId(),
            'user_id' => $userId,
            'device_id' => $deviceId,
            'username' => $username,
            'is_vip' => 0,
            'vip_expire_time' => null,
            'download_count' => 0,
            'user_type' => 0,
            'remaining_downloads' => $remaining,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }

    return false;
}

/**
 * 根据用户ID获取用户信息
 * @param string $userId 8位数字用户ID
 * @return array|false 用户信息或false
 */
function getUserById($userId) {
    global $pdo;

    $stmt = $pdo->prepare("SELECT `id`, `user_id`, `username`, `phone`, `email`,
                                 `avatar`, `is_vip`, `vip_expire_time`,
                                 `download_count`, `user_type`, `created_at`, `updated_at`
                          FROM `users`
                          WHERE `user_id` = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * 根据设备ID获取或创建用户
 * @param string $deviceId 设备ID
 * @return array 用户信息
 */
function getOrCreateUserByDevice($deviceId) {
    global $pdo;

    // 先查询是否存在
    $stmt = $pdo->prepare("SELECT `id`, `user_id`, `username`, `phone`, `email`,
                                 `avatar`, `is_vip`, `vip_expire_time`,
                                 `download_count`, `user_type`, `created_at`, `updated_at`
                          FROM `users`
                          WHERE `device_id` = ?");
    $stmt->execute([$deviceId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        return $user;
    }

    // 不存在则创建游客用户
    $newUser = createGuestUser($deviceId);
    if ($newUser) {
        return $newUser;
    }

    return false;
}

/**
 * Apple ID 登录或注册
 * @param string $appleId Apple User Identifier
 * @param string $deviceId 设备ID
 * @param string $email 邮箱（可选）
 * @param string $fullName 全名（可选）
 * @return array|false 用户信息
 */
function loginWithApple($appleId, $deviceId, $email = null, $fullName = null) {
    global $pdo;

    // 1. 检查 Apple ID 是否已存在
    $stmt = $pdo->prepare("SELECT `id`, `user_id`, `username`, `phone`, `email`,
                                 `avatar`, `is_vip`, `vip_expire_time`,
                                 `download_count`, `user_type`, `created_at`
                          FROM `users`
                          WHERE `apple_id` = ?");
    $stmt->execute([$appleId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        return $user;
    }

    // 2. 如果不存在，创建新用户
    // 生成8位数字用户ID
    $userId = generateUniqueUserId();

    // 生成用户名 (优先使用全名，否则使用AppleUser_后缀)
    $username = $fullName ? $fullName : 'AppleUser_' . substr($appleId, 0, 6);

    // 如果用户名已存在，添加随机后缀
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE `username` = ?");
    $stmt->execute([$username]);
    if ($stmt->fetchColumn() > 0) {
        $username .= '_' . rand(1000, 9999);
    }

    // 随机密码
    $password = bin2hex(random_bytes(10));
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO `users` (`user_id`, `username`, `password`, `device_id`, `email`, `apple_id`, `user_type`)
            VALUES (?, ?, ?, ?, ?, ?, 1)";

    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$userId, $username, $hashedPassword, $deviceId, $email, $appleId]);

    if ($result) {
        return getUserById($userId);
    }

    return false;
}

/**
 * 检查素材是否对用户隐藏
 * @param string $userId 用户ID
 * @param string $materialId 素材ID
 * @param int $materialType 素材类型
 * @return bool true-已隐藏 false-未隐藏
 */
function isMaterialHidden($userId, $materialId, $materialType) {
    global $pdo;

    if (empty($userId)) {
        return false;
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `user_hidden_materials`
                           WHERE `user_id` = ? AND `material_id` = ? AND `material_type` = ?");
    $stmt->execute([$userId, $materialId, $materialType]);
    return $stmt->fetchColumn() > 0;
}

/**
 * 获取首页Banner列表
 * @return array Banner列表
 */
function getHomeBanners() {
    global $pdo;

    $sql = "SELECT `banner_id`, `title`, `image_url`, `link_url`, `link_type`
            FROM `banners`
            WHERE `status` = 1
            AND (`start_time` IS NULL OR `start_time` <= NOW())
            AND (`end_time` IS NULL OR `end_time` >= NOW())
            ORDER BY `sort` ASC, `created_at` DESC
            LIMIT 5";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 提交举报
 * @param string $userId 用户ID
 * @param string $materialId 素材ID
 * @param int $materialType 素材类型
 * @param string $reportType 举报类型
 * @param string $reportContent 举报内容
 * @return array|false
 */
function submitReport($userId, $materialId, $materialType, $reportType, $reportContent = '') {
    global $pdo;

    try {
        $pdo->beginTransaction();

        // 检查是否已举报过
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `material_reports`
                               WHERE `user_id` = ? AND `material_id` = ? AND `material_type` = ?");
        $stmt->execute([$userId, $materialId, $materialType]);

        if ($stmt->fetchColumn() > 0) {
            $pdo->rollBack();
            return ['error' => '您已举报过该素材'];
        }

        // 插入举报记录
        $sql = "INSERT INTO `material_reports`
                (`user_id`, `material_id`, `material_type`, `report_type`, `report_content`)
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $materialId, $materialType, $reportType, $reportContent]);

        // 将该素材对该用户隐藏
        $sql = "INSERT INTO `user_hidden_materials`
                (`user_id`, `material_id`, `material_type`, `reason`)
                VALUES (?, ?, ?, 'report')
                ON DUPLICATE KEY UPDATE `reason` = 'report'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $materialId, $materialType]);

        $pdo->commit();

        return [
            'success' => true,
            'message' => '举报成功，该素材已对您隐藏'
        ];

    } catch (Exception $e) {
        $pdo->rollBack();
        return ['error' => '举报失败：' . $e->getMessage()];
    }
}

/**
 * 获取所有协议列表
 * @return array 协议列表
 */
function getAgreementsList() {
    global $pdo;

    // 检查是否存在 url 字段
    $checkUrlColumn = $pdo->query("SHOW COLUMNS FROM `agreements` LIKE 'url'");
    $hasUrlColumn = $checkUrlColumn->fetch() !== false;

    $sql = $hasUrlColumn
        ? "SELECT `agreement_id`, `title`, `type`, `version`, `updated_at`, `url` FROM `agreements` WHERE `status` = 1 ORDER BY `sort` ASC, `created_at` ASC"
        : "SELECT `agreement_id`, `title`, `type`, `version`, `updated_at` FROM `agreements` WHERE `status` = 1 ORDER BY `sort` ASC, `created_at` ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 如果没有 url 字段，为每条记录添加空的 url 字段
    if (!$hasUrlColumn) {
        foreach ($results as &$result) {
            $result['url'] = null;
        }
    }

    return $results;
}

/**
 * 获取协议详情
 * @param string $agreementId 协议ID
 * @return array|false
 */
function getAgreementDetail($agreementId) {
    global $pdo;

    // 检查是否存在 url 字段
    $checkUrlColumn = $pdo->query("SHOW COLUMNS FROM `agreements` LIKE 'url'");
    $hasUrlColumn = $checkUrlColumn->fetch() !== false;

    $sql = $hasUrlColumn
        ? "SELECT `agreement_id`, `title`, `content`, `type`, `version`, `updated_at`, `url` FROM `agreements` WHERE `agreement_id` = ? AND `status` = 1"
        : "SELECT `agreement_id`, `title`, `content`, `type`, `version`, `updated_at` FROM `agreements` WHERE `agreement_id` = ? AND `status` = 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$agreementId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // 如果没有 url 字段，添加空的 url 字段
    if ($result && !$hasUrlColumn) {
        $result['url'] = null;
    }

    return $result;
}

/**
 * 获取首页视频列表（瀑布流，支持过滤隐藏素材）
 * @param string $userId 用户ID（用于过滤隐藏素材）
 * @param string $categoryId 分类ID（可选）
 * @param int $page 页码
 * @param int $pageSize 每页数量
 * @return array
 */
function getVideoListForWaterfall($userId, $categoryId = null, $page = 1, $pageSize = 20) {
    global $pdo;

    // 获取隐藏的素材ID
    $hiddenCondition = '';
    $hiddenParams = [];
    if ($userId) {
        $stmt = $pdo->prepare("SELECT `material_id` FROM `user_hidden_materials`
                               WHERE `user_id` = ? AND `material_type` = 1");
        $stmt->execute([$userId]);
        $hiddenIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($hiddenIds)) {
            $placeholders = implode(',', array_fill(0, count($hiddenIds), '?'));
            $hiddenCondition = " AND vm.material_id NOT IN ($placeholders)";
            $hiddenParams = $hiddenIds;
        }
    }

    $sql = "SELECT DISTINCT vm.material_id, vm.name, vm.video_url, vm.thumbnail_url,
                   vm.download_count, vm.like_count, vm.created_at,
                   u.user_id as author_id, u.username as author_name, u.avatar as author_avatar
            FROM `video_materials` vm
            LEFT JOIN `users` u ON vm.author_id = u.user_id";

    $params = [];

    if ($categoryId) {
        $sql .= " INNER JOIN `category_relations` cr
                  ON vm.material_id = cr.material_id
                  AND cr.material_type = 1
                  WHERE cr.category_id = ? AND vm.status = 1";
        $params[] = $categoryId;
    } else {
        $sql .= " INNER JOIN `category_relations` cr
                  ON vm.material_id = cr.material_id
                  AND cr.material_type = 1
                  INNER JOIN `categories` c ON cr.category_id = c.category_id
                  WHERE vm.status = 1 AND c.status = 1";
    }

    $sql .= $hiddenCondition;
    $params = array_merge($params, $hiddenParams);

    $sql .= " ORDER BY vm.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $pageSize;
    $params[] = ($page - 1) * $pageSize;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 获取每个视频的分类和收藏状态
    foreach ($videos as &$video) {
        if (isset($video['video_url'])) {
            $video['video_url'] = absoluteMediaUrl($video['video_url']);
        }
        if (isset($video['thumbnail_url'])) {
            $video['thumbnail_url'] = absoluteMediaUrl($video['thumbnail_url']);
        }
        if (isset($video['author_avatar'])) {
            $video['author_avatar'] = absoluteMediaUrl($video['author_avatar']);
        }

        // 获取分类
        $sql = "SELECT c.category_id, c.name
                FROM `categories` c
                INNER JOIN `category_relations` cr ON c.category_id = cr.category_id
                WHERE cr.material_id = ? AND cr.material_type = 1
                ORDER BY c.is_top DESC, c.sort ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$video['material_id']]);
        $video['categories'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 获取收藏数量（平台所有用户对该素材的收藏总数）
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `user_favorites`
                               WHERE `material_id` = ? AND `material_type` = 1");
        $stmt->execute([$video['material_id']]);
        $video['favorite_count'] = intval($stmt->fetchColumn());

        // 检查当前用户是否收藏
        $video['is_favorite'] = false;
        if ($userId) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `user_favorites`
                                   WHERE `user_id` = ? AND `material_id` = ? AND `material_type` = 1");
            $stmt->execute([$userId, $video['material_id']]);
            $video['is_favorite'] = $stmt->fetchColumn() > 0;
        }
    }

    return $videos;
}

/**
 * 获取系统配置
 * @param string $key 配置键
 * @param mixed $defaultValue 默认值
 * @return mixed 配置值
 */
if (!function_exists('getSystemConfig')) {
    function getSystemConfig($key, $defaultValue = null) {
        global $pdo;
        try {
            $stmt = $pdo->prepare("SELECT config_value FROM system_config WHERE config_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetchColumn();
            return $result !== false ? $result : $defaultValue;
        } catch (Exception $e) {
            // 如果表不存在或查询失败，返回默认值
            return $defaultValue;
        }
    }
}

/**
 * 检查用户是否为VIP（基于苹果订阅状态）
 * 优先检查 subscription_records 表中的订阅记录，这是苹果的真实状态
 * @param string $userId 用户ID
 * @return bool 是否为VIP
 */
if (!function_exists('isUserVIP')) {
    function isUserVIP($userId) {
        global $pdo;

        try {
            // 首先检查 subscription_records 表中是否有有效的订阅（这是苹果的真实状态）
            // 1. 检查是否有终身版订阅（product_id 包含 'lifetime' 或 '9999' 且状态为 active）
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM subscription_records
                                  WHERE user_id = ?
                                  AND (product_id LIKE '%lifetime%' OR product_id LIKE '%9999%')
                                  AND subscription_status = 'active'");
            $stmt->execute([$userId]);
            if ($stmt->fetchColumn() > 0) {
                return true; // 终身版，直接返回 true
            }

            // 2. 检查是否有有效的订阅（未过期且状态为 active）
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM subscription_records
                                  WHERE user_id = ?
                                  AND subscription_status = 'active'
                                  AND expire_time > NOW()");
            $stmt->execute([$userId]);
            if ($stmt->fetchColumn() > 0) {
                return true; // 有有效的订阅
            }
        } catch (Exception $e) {
            // 如果 subscription_records 表不存在，回退到检查 users 表
            error_log("isUserVIP: subscription_records 表查询失败，回退到 users 表: " . $e->getMessage());
            try {
                $stmt = $pdo->prepare("SELECT is_vip, vip_expire_time FROM users WHERE user_id = ?");
                $stmt->execute([$userId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result && $result['is_vip'] == 1 && !empty($result['vip_expire_time'])) {
                    return strtotime($result['vip_expire_time']) >= time();
                }
            } catch (Exception $e2) {
                error_log("isUserVIP: users 表查询也失败: " . $e2->getMessage());
            }
        }

        // 如果没有找到有效的订阅记录，返回 false
        return false;
    }
}
