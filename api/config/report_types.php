<?php
/**
 * 获取举报反馈选项配置
 */

require_once '../../common/response.php';
require_once '../../common/db.php';

setCorsHeaders();
header('Content-Type: application/json; charset=utf-8');

// 尝试从系统配置表中获取
try {
    global $pdo;
    $stmt = $pdo->prepare("SELECT config_value FROM system_config WHERE config_key = 'report_types'");
    $stmt->execute();
    $result = $stmt->fetchColumn();
    
    if ($result) {
        $types = json_decode($result, true);
        if ($types) {
            echo jsonResponse(200, '获取成功', $types);
            exit;
        }
    }
} catch (Exception $e) {
    // 忽略错误，使用默认值
}

// 默认反馈选项
$defaultTypes = [
    ['key' => 'porn', 'name' => '色情低俗'],
    ['key' => 'violence', 'name' => '暴力恐怖'],
    ['key' => 'political', 'name' => '政治敏感'],
    ['key' => 'ad', 'name' => '垃圾广告'],
    ['key' => 'copyright', 'name' => '版权侵犯'],
    ['key' => 'content_error', 'name' => '内容错误'],
    ['key' => 'other', 'name' => '其他问题']
];

echo jsonResponse(200, '获取成功', $defaultTypes);
