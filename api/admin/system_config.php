<?php
/**
 * 系统配置管理
 * 获取和更新系统配置参数
 */

require_once '../../common/db.php';
require_once '../../common/functions.php';
require_once '../../common/response.php';

setCorsHeaders();
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

// 获取请求数据
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
} else {
    $data = $_GET;
}

global $pdo;

// 创建系统配置表（如果不存在）
function createSystemConfigTable() {
    global $pdo;
    $sql = "CREATE TABLE IF NOT EXISTS `system_config` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `config_key` varchar(100) NOT NULL COMMENT '配置键',
              `config_value` text COMMENT '配置值',
              `config_desc` varchar(255) COMMENT '配置描述',
              `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
              `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `config_key` (`config_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统配置表'";
    $pdo->exec($sql);
}

// 初始化默认配置
function initDefaultConfig() {
    global $pdo;

    $defaultConfigs = [
        [
            'key' => 'free_user_daily_limit',
            'value' => '5',
            'desc' => '非订阅用户每日下载限制数量'
        ],
        [
            'key' => 'vip_user_daily_limit',
            'value' => '100',
            'desc' => 'VIP用户每日下载限制数量'
        ],
        [
            'key' => 'enable_download_limit',
            'value' => 'true',
            'desc' => '是否启用下载限制功能'
        ],
        [
            'key' => 'enable_download_feature',
            'value' => 'false',
            'desc' => '是否启用下载功能（关闭后隐藏视频和图片下载按钮）'
        ],
        [
            'key' => 'report_types',
            'value' => json_encode([
                ['id' => 'porn', 'name' => '色情低俗', 'name_en' => 'Inappropriate'],
                ['id' => 'violence', 'name' => '暴力血腥', 'name_en' => 'Violence'],
                ['id' => 'ad', 'name' => '广告骚扰', 'name_en' => 'Spam'],
                ['id' => 'illegal', 'name' => '违法犯罪', 'name_en' => 'Illegal'],
                ['id' => 'other', 'name' => '其他', 'name_en' => 'Other']
            ], JSON_UNESCAPED_UNICODE),
            'desc' => '举报反馈类型列表'
        ]
    ];

    foreach ($defaultConfigs as $config) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO system_config (config_key, config_value, config_desc) VALUES (?, ?, ?)");
        $stmt->execute([$config['key'], $config['value'], $config['desc']]);
    }
}

try {
    createSystemConfigTable();
    initDefaultConfig();

    if ($method === 'GET') {
        // 获取配置列表
        $stmt = $pdo->prepare("SELECT config_key, config_value, config_desc FROM system_config ORDER BY config_key");
        $stmt->execute();
        $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 转换为键值对格式
        $configMap = [];
        foreach ($configs as $config) {
            $configMap[$config['config_key']] = [
                'value' => $config['config_value'],
                'desc' => $config['config_desc']
            ];
        }

        echo jsonResponse(200, '获取配置成功', $configMap);

    } elseif ($method === 'POST') {
        // 更新配置
        $updates = $data['updates'] ?? [];

        if (empty($updates)) {
            echo jsonResponse(400, '没有提供更新数据', null);
            exit;
        }

        $updated = 0;
        foreach ($updates as $key => $value) {
            $stmt = $pdo->prepare("INSERT INTO system_config (config_key, config_value) VALUES (?, ?)
                                  ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)");
            $result = $stmt->execute([$key, $value]);
            if ($result) {
                $updated++;
            }
        }

        echo jsonResponse(200, "成功更新 {$updated} 个配置项", [
            'updated_count' => $updated,
            'updated_keys' => array_keys($updates)
        ]);

    } else {
        echo jsonResponse(405, '不支持的请求方法', null);
    }

} catch (Exception $e) {
    echo jsonResponse(500, '系统错误: ' . $e->getMessage(), null);
}
?>
