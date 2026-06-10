<?php
/**
 * 用户注册接口
 */

require_once '../../common/db.php';
require_once '../../common/functions.php';
require_once '../../common/response.php';

setCorsHeaders();
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);

// 检查是否是设备游客注册
$deviceId = $data['device_id'] ?? null;
$userType = $data['user_type'] ?? 1; // 默认为注册用户(1)，游客(0)

if ($deviceId && $userType == 0) {
    // 游客账户创建
    $username = 'guest_' . substr($deviceId, 0, 8);
    $password = substr(md5($deviceId), 0, 10); // 设备ID的MD5前10位作为密码
    $phone = null;
    $email = null;
    
    // 尝试创建游客账户
    $result = createGuestUser($deviceId);
    
    if (isset($result['error'])) {
        echo jsonResponse(400, $result['error'], null);
    } elseif ($result) {
        echo jsonResponse(200, '游客账户创建成功', $result);
    } else {
        echo jsonResponse(500, '游客账户创建失败', null);
    }
    exit;
}

// 正常用户注册
$username = $data['username'] ?? '';
$password = $data['password'] ?? '';
$phone = $data['phone'] ?? null;
$email = $data['email'] ?? null;

if (empty($username) || empty($password)) {
    echo jsonResponse(400, '用户名和密码不能为空', null);
    exit;
}

$result = registerUser($username, $password, $phone, $email);

if (isset($result['error'])) {
    echo jsonResponse(400, $result['error'], null);
} elseif ($result) {
    echo jsonResponse(200, '注册成功', $result);
} else {
    echo jsonResponse(500, '注册失败', null);
}
