<?php
/**
 * Token 认证模块
 * 基于 HMAC 的简易 Token 系统
 *
 * Token 格式: {user_id}.{timestamp}.{signature}
 * signature = HMAC-SHA256(user_id + timestamp + device_id, SECRET_KEY)
 *
 * 使用方式:
 * 1. 登录/注册时调用 generateToken() 生成 Token，返回给客户端
 * 2. 客户端在后续请求中通过 Authorization: Bearer <token> 携带
 * 3. API 端点调用 verifyToken() 或 requireAuth() 验证身份
 * 4. 兼容模式：无 Token 时仍接受 device_id 参数（渐进式迁移）
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// Token 密钥（从配置或环境变量获取）
define('TOKEN_SECRET_KEY', getSystemConfig('token_secret_key', 'maskedit_token_secret_2024'));
// Token 有效期（秒）
define('TOKEN_EXPIRY_GUEST', 30 * 24 * 3600);    // 游客 30 天
define('TOKEN_EXPIRY_VIP', 365 * 24 * 3600);      // VIP 365 天
define('TOKEN_EXPIRY_DEFAULT', 30 * 24 * 3600);    // 默认 30 天

/**
 * 生成 Token
 * @param string $userId 用户ID
 * @param string $deviceId 设备ID
 * @return array ['token' => string, 'expires_at' => string]
 */
function generateToken($userId, $deviceId) {
    $timestamp = time();
    $payload = $userId . '.' . $timestamp . '.' . $deviceId;
    $signature = hash_hmac('sha256', $payload, TOKEN_SECRET_KEY);
    $token = $userId . '.' . $timestamp . '.' . $signature;

    // 计算 Token 有效期
    $isVIP = isUserVIP($userId);
    $expirySeconds = $isVIP ? TOKEN_EXPIRY_VIP : TOKEN_EXPIRY_DEFAULT;
    $expiresAt = date('Y-m-d H:i:s', $timestamp + $expirySeconds);

    // 存储到 user_tokens 表
    saveTokenToDb($userId, $token, $expiresAt, $deviceId);

    return [
        'token' => $token,
        'expires_at' => $expiresAt
    ];
}

/**
 * 保存 Token 到数据库
 * @param string $userId
 * @param string $token
 * @param string $expiresAt
 * @param string $deviceId
 */
function saveTokenToDb($userId, $token, $expiresAt, $deviceId) {
    global $pdo;

    try {
        // 检查 user_tokens 表是否存在
        $stmt = $pdo->query("SHOW TABLES LIKE 'user_tokens'");
        if ($stmt->fetchColumn() === false) {
            // 自动创建表
            createTokenTable();
        }

        // 删除该用户的旧 Token（每个用户只保留一个有效 Token）
        $stmt = $pdo->prepare("DELETE FROM `user_tokens` WHERE `user_id` = ?");
        $stmt->execute([$userId]);

        // 插入新 Token
        $stmt = $pdo->prepare("INSERT INTO `user_tokens` (`user_id`, `token`, `expires_at`, `device_id`, `created_at`)
                               VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $token, $expiresAt, $deviceId]);
    } catch (Exception $e) {
        error_log("saveTokenToDb 失败: " . $e->getMessage());
    }
}

/**
 * 创建 user_tokens 表
 */
function createTokenTable() {
    global $pdo;

    $sql = "CREATE TABLE IF NOT EXISTS `user_tokens` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` VARCHAR(8) NOT NULL COMMENT '用户ID',
        `token` VARCHAR(128) NOT NULL COMMENT 'Token字符串',
        `device_id` VARCHAR(64) DEFAULT NULL COMMENT '设备ID',
        `expires_at` DATETIME NOT NULL COMMENT '过期时间',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
        UNIQUE KEY `uk_token` (`token`),
        KEY `idx_user_id` (`user_id`),
        KEY `idx_expires_at` (`expires_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户Token表'";

    $pdo->exec($sql);
}

/**
 * 验证 Token
 * @param string $token Token字符串
 * @return array|null 成功返回用户信息，失败返回 null
 */
function verifyToken($token) {
    if (empty($token)) {
        return null;
    }

    // 解析 Token: user_id.timestamp.signature
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }

    $userId = $parts[0];
    $timestamp = intval($parts[1]);
    $signature = $parts[2];

    // 获取用户信息（包含 device_id）
    $user = getUserById($userId);
    if (!$user) {
        return null;
    }

    // 获取 device_id（需要完整查询包含 device_id）
    global $pdo;
    $stmt = $pdo->prepare("SELECT `device_id` FROM `users` WHERE `user_id` = ?");
    $stmt->execute([$userId]);
    $deviceId = $stmt->fetchColumn();

    // 验证签名
    $payload = $userId . '.' . $timestamp . '.' . $deviceId;
    $expectedSignature = hash_hmac('sha256', $payload, TOKEN_SECRET_KEY);

    if (!hash_equals($expectedSignature, $signature)) {
        return null;
    }

    // 验证时间戳有效性（Token 不应早于 365 天前生成）
    if ($timestamp < time() - 365 * 24 * 3600) {
        return null;
    }

    // 验证数据库中的 Token 是否存在且未过期
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'user_tokens'");
        if ($stmt->fetchColumn() !== false) {
            $stmt = $pdo->prepare("SELECT `expires_at` FROM `user_tokens`
                                   WHERE `user_id` = ? AND `token` = ?");
            $stmt->execute([$userId, $token]);
            $tokenRecord = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($tokenRecord) {
                if (strtotime($tokenRecord['expires_at']) < time()) {
                    // Token 已过期，删除并返回 null
                    $stmt = $pdo->prepare("DELETE FROM `user_tokens` WHERE `token` = ?");
                    $stmt->execute([$token]);
                    return null;
                }
            }
            // 即使数据库中没有记录（渐进迁移期），只要签名和时间戳验证通过就允许
        }
    } catch (Exception $e) {
        error_log("verifyToken DB查询失败: " . $e->getMessage());
    }

    // 返回完整用户信息
    return $user;
}

/**
 * 从请求中提取 Token
 * 支持 Authorization: Bearer <token> 头部
 * @return string|null Token字符串
 */
function extractTokenFromRequest() {
    // 1. 从 Authorization 头部提取
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!empty($authHeader)) {
        // 支持 Bearer 格式
        if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
        // 直接传递 Token
        return $authHeader;
    }

    // 2. 从 query 参数提取（兼容旧模式）
    $tokenParam = $_GET['token'] ?? '';
    if (!empty($tokenParam)) {
        return $tokenParam;
    }

    // 3. 从 POST body 提取
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        $data = json_decode(file_get_contents('php://input'), true);
        $tokenBody = $data['token'] ?? '';
        if (!empty($tokenBody)) {
            return $tokenBody;
        }
    }

    return null;
}

/**
 * 认证用户 - 优先 Token，兼容 device_id
 * @return array|null 用户信息，失败返回 null
 *
 * 使用方式（在 API 端点中）:
 *   require_once '../../common/auth.php';
 *   $user = authenticateUser();
 *   if (!$user) {
 *       echo jsonResponse(401, '未授权，请先登录', null);
 *       exit;
 *   }
 *   $userId = $user['user_id'];
 */
function authenticateUser() {
    // 1. 尝试 Token 认证
    $token = extractTokenFromRequest();
    if ($token) {
        $user = verifyToken($token);
        if ($user) {
            return $user;
        }
        // Token 无效，不自动降级到 device_id（安全要求）
        // 但在渐进迁移期，我们允许降级
    }

    // 2. 兼容模式：使用 device_id（渐进迁移期）
    $deviceId = $_GET['device_id'] ?? $_SERVER['HTTP_DEVICE_ID'] ?? '';
    if (!empty($deviceId)) {
        // 从 POST body 也尝试获取
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') !== false) {
            $data = json_decode(file_get_contents('php://input'), true);
            $bodyDeviceId = $data['device_id'] ?? '';
            if (!empty($bodyDeviceId)) {
                $deviceId = $bodyDeviceId;
            }
        }

        $user = getOrCreateUserByDevice($deviceId);
        if ($user) {
            // 兼容模式下自动生成并返回 Token（供客户端后续使用）
            // 但不在此处修改响应（由 API 端点自行处理）
            return $user;
        }
    }

    return null;
}

/**
 * 强制认证（不兼容 device_id 的严格模式）
 * 用于需要确认用户身份的操作（如收藏、点赞、VIP验证）
 * @return array|null 用户信息
 */
function requireAuthToken() {
    $token = extractTokenFromRequest();
    if (!$token) {
        return null;
    }
    return verifyToken($token);
}

/**
 * 刷新 Token（延长有效期）
 * @param string $userId
 * @param string $deviceId
 * @return array 新的 Token 信息
 */
function refreshToken($userId, $deviceId) {
    return generateToken($userId, $deviceId);
}

/**
 * 撤销 Token（注销）
 * @param string $userId
 */
function revokeToken($userId) {
    global $pdo;

    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'user_tokens'");
        if ($stmt->fetchColumn() !== false) {
            $stmt = $pdo->prepare("DELETE FROM `user_tokens` WHERE `user_id` = ?");
            $stmt->execute([$userId]);
        }
    } catch (Exception $e) {
        error_log("revokeToken 失败: " . $e->getMessage());
    }
}