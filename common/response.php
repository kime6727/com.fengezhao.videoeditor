<?php
/**
 * 统一响应格式
 */

/**
 * 统一JSON响应格式
 * @param int $code 状态码
 * @param string $message 消息
 * @param mixed $data 数据
 * @return string JSON字符串
 */
function jsonResponse($code, $message, $data) {
    return json_encode([
        'code' => $code,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * 设置CORS头（允许跨域）
 */
function setCorsHeaders() {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Device-ID');
    header('Access-Control-Max-Age: 86400');
    
    // 处理预检请求
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}