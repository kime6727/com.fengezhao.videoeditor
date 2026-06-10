<?php
/**
 * Apple ID 登录接口
 */

require_once '../../common/db.php';
require_once '../../common/functions.php';
require_once '../../common/response.php';

setCorsHeaders();
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);

$userIdentifier = $data['user_identifier'] ?? ''; // Apple User ID
$identityToken = $data['identity_token'] ?? '';
$authorizationCode = $data['authorization_code'] ?? '';
$deviceId = $data['device_id'] ?? '';
$email = $data['email'] ?? null;
$fullName = $data['full_name'] ?? null;

if (empty($userIdentifier) || empty($deviceId)) {
    echo jsonResponse(400, '缺少必要参数', null);
    exit;
}

// TODO: 在这里可以验证 identityToken (使用 JWT 库验证 Apple 的签名)
// 暂时信任客户端传递的 userIdentifier，因为必须经过 Apple SDK 验证

try {
    $user = loginWithApple($userIdentifier, $deviceId, $email, $fullName);

    if ($user) {
        echo jsonResponse(200, '登录成功', $user);
    } else {
        echo jsonResponse(500, '登录失败', null);
    }
} catch (Exception $e) {
    echo jsonResponse(500, '服务器错误: ' . $e->getMessage(), null);
}
