<?php
/**
 * 系统配置管理页面
 * 管理员可以查看和修改系统配置参数
 */

require_once 'common/session.php';
require_once '../common/db.php';
require_once '../common/functions.php';

// 检查管理员权限
checkAdminLogin();

$message = '';
$messageType = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updates = [];

    // 获取表单数据
    if (isset($_POST['free_user_daily_limit'])) {
        $updates['free_user_daily_limit'] = intval($_POST['free_user_daily_limit']);
    }
    if (isset($_POST['vip_user_daily_limit'])) {
        $updates['vip_user_daily_limit'] = intval($_POST['vip_user_daily_limit']);
    }
    // 处理checkbox：如果存在则为true，不存在则为false
    // enable_download_limit 始终启用，不在页面上显示开关
    $updates['enable_download_limit'] = 'true'; // 始终启用下载限制功能
    $updates['enable_download_feature'] = isset($_POST['enable_download_feature']) ? 'true' : 'false';

    if (!empty($updates)) {
        try {
            // 更新配置
            foreach ($updates as $key => $value) {
                $stmt = $pdo->prepare("INSERT INTO system_config (config_key, config_value) VALUES (?, ?)
                                      ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)");
                $stmt->execute([$key, $value]);
            }

            $message = '配置更新成功！';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = '配置更新失败：' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// 获取当前配置
function getSystemConfig($key, $defaultValue = '') {
    global $pdo;
    $stmt = $pdo->prepare("SELECT config_value FROM system_config WHERE config_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetchColumn();
    return $result !== false ? $result : $defaultValue;
}

// 初始化默认配置
function initDefaultConfig() {
    global $pdo;

    $defaultConfigs = [
        ['key' => 'free_user_daily_limit', 'value' => '3', 'desc' => '非订阅用户每日下载限制数量'],
        ['key' => 'vip_user_daily_limit', 'value' => '50', 'desc' => 'VIP用户每日下载限制数量'],
        ['key' => 'enable_download_limit', 'value' => 'true', 'desc' => '是否启用下载限制功能'],
        ['key' => 'enable_download_feature', 'value' => 'false', 'desc' => '是否启用下载功能（关闭后隐藏视频和图片下载按钮）']
    ];

    foreach ($defaultConfigs as $config) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO system_config (config_key, config_value, config_desc) VALUES (?, ?, ?)");
        $stmt->execute([$config['key'], $config['value'], $config['desc']]);
    }
}

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

// 初始化
createSystemConfigTable();
initDefaultConfig();

// 获取配置值
$freeUserLimit = getSystemConfig('free_user_daily_limit', 3);
$vipUserLimit = getSystemConfig('vip_user_daily_limit', 50);
$enableDownloadLimit = getSystemConfig('enable_download_limit', 'true');
$enableDownloadFeature = getSystemConfig('enable_download_feature', 'false');

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统配置管理 - 后台管理</title>
    <link rel="stylesheet" href="common/style.css">
    <style>
        .config-form {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        .form-group input[type="number"] {
            width: 200px;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .form-group input[type="checkbox"] {
            margin-right: 8px;
        }
        .switch-container {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }
        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 26px;
        }
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 26px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .slider {
            background-color: #007bff;
        }
        input:checked + .slider:before {
            transform: translateX(24px);
        }
        .switch-label {
            font-weight: 600;
            color: #333;
            cursor: pointer;
        }
        .form-group small {
            display: block;
            color: #666;
            margin-top: 5px;
            font-size: 12px;
        }
        .submit-btn {
            background: #007bff;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
        }
        .submit-btn:hover {
            background: #0056b3;
        }
        .message {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .section-title {
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
            margin-bottom: 25px;
            color: #333;
        }
    </style>
</head>
<body>
    <?php include 'common/sidebar.php'; ?>

    <div class="main-content">
        <div class="header">
            <h1>系统配置管理</h1>
        </div>

        <div class="content">
            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="config-form">
                <h2 class="section-title">下载限制配置</h2>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="free_user_daily_limit">
                            非订阅用户每日下载限制（A值）
                        </label>
                        <input type="number"
                               id="free_user_daily_limit"
                               name="free_user_daily_limit"
                               value="<?php echo htmlspecialchars($freeUserLimit); ?>"
                               min="0"
                               max="999">
                        <small>
                            普通用户每日可下载素材的数量限制。设置为0表示无限制。
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="vip_user_daily_limit">
                            VIP用户每日下载限制（B值）
                        </label>
                        <input type="number"
                               id="vip_user_daily_limit"
                               name="vip_user_daily_limit"
                               value="<?php echo htmlspecialchars($vipUserLimit); ?>"
                               min="0"
                               max="9999">
                        <small>
                            VIP用户每日可下载素材的数量限制。设置为0表示无限制。
                        </small>
                    </div>

                    <div class="form-group">
                        <div class="switch-container">
                            <label class="switch">
                                <input type="checkbox"
                                       id="enable_download_feature"
                                       name="enable_download_feature"
                                       value="1"
                                       <?php echo $enableDownloadFeature === 'true' ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                            <label class="switch-label" for="enable_download_feature">
                                启用下载功能
                            </label>
                        </div>
                        <small>
                            关闭后，单视频详情页、发圈视频、发圈图文场景中的下载按钮将被隐藏。配音和文本复制功能不受影响。
                        </small>
                    </div>

                    <button type="submit" class="submit-btn">保存配置</button>
                </form>

                <div style="margin-top: 30px; padding: 15px; background: #f8f9fa; border-radius: 4px;">
                    <h4>配置说明：</h4>
                    <ul style="margin: 10px 0; padding-left: 20px;">
                        <li><strong>A值（非订阅用户限制）</strong>：控制普通用户的每日下载数量，促进用户升级VIP</li>
                        <li><strong>B值（VIP用户限制）</strong>：控制VIP用户的每日下载数量，建议设置较高数值</li>
                        <li><strong>限制重置时间</strong>：每日凌晨0点重置下载计数</li>
                        <li><strong>计算方式</strong>：按用户账号计算，不是按设备</li>
                        <li><strong>适用范围</strong>：包括单视频下载、发圈视频提取素材、发圈图文下载图片等所有下载操作</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</body>
</html>





