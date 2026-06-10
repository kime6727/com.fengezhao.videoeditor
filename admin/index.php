<?php
/**
 * 后台管理首页
 */
require_once __DIR__ . '/common/session.php';
require_once __DIR__ . '/../common/db.php';
require_once __DIR__ . '/../common/functions.php';

checkAdminLogin();

// 获取统计数据
global $pdo;

$stats = [];

// 视频素材数量
$stmt = $pdo->query("SELECT COUNT(*) FROM `video_materials` WHERE `status` = 1");
$stats['videos'] = $stmt->fetchColumn();

// 图片+文案素材数量
$stmt = $pdo->query("SELECT COUNT(*) FROM `image_text_materials` WHERE `status` = 1");
$stats['image_texts'] = $stmt->fetchColumn();

// 视频+文案素材数量
$stmt = $pdo->query("SELECT COUNT(*) FROM `video_text_materials` WHERE `status` = 1");
$stats['video_texts'] = $stmt->fetchColumn();

// 纯文案素材数量
$stmt = $pdo->query("SELECT COUNT(*) FROM `text_materials` WHERE `status` = 1");
$stats['texts'] = $stmt->fetchColumn();

// 用户数量
$stmt = $pdo->query("SELECT COUNT(*) FROM `users`");
$stats['users'] = $stmt->fetchColumn();

// 待处理举报数量
$stmt = $pdo->query("SELECT COUNT(*) FROM `material_reports` WHERE `status` = 0");
$stats['reports'] = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>后台管理 - 首页</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-card h3 {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }
        .stat-card .number {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
            transition: transform 0.2s;
            cursor: pointer;
        }
        .stat-card .number:hover {
            transform: scale(1.1);
            color: #5568d3;
        }
        .stat-card a {
            text-decoration: none;
            color: inherit;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include 'common/sidebar.php'; ?>
        <div class="main-content">
            <h1 style="margin-bottom: 20px;">后台管理 - 首页</h1>
        <div class="stats">
            <div class="stat-card">
                <h3>单视频素材</h3>
                <a href="video/list.php" style="text-decoration: none; color: inherit;">
                    <div class="number" style="cursor: pointer;"><?php echo $stats['videos']; ?></div>
                </a>
            </div>
            <div class="stat-card">
                <h3>图片+文案</h3>
                <a href="image-text/list.php" style="text-decoration: none; color: inherit;">
                    <div class="number" style="cursor: pointer;"><?php echo $stats['image_texts']; ?></div>
                </a>
            </div>
            <div class="stat-card">
                <h3>视频+文案</h3>
                <a href="video-text/list.php" style="text-decoration: none; color: inherit;">
                    <div class="number" style="cursor: pointer;"><?php echo $stats['video_texts']; ?></div>
                </a>
            </div>
            <div class="stat-card">
                <h3>纯文案</h3>
                <a href="text/list.php" style="text-decoration: none; color: inherit;">
                    <div class="number" style="cursor: pointer;"><?php echo $stats['texts']; ?></div>
                </a>
            </div>
            <div class="stat-card">
                <h3>用户总数</h3>
                <a href="user/list.php" style="text-decoration: none; color: inherit;">
                    <div class="number" style="cursor: pointer;"><?php echo $stats['users']; ?></div>
                </a>
            </div>
            <div class="stat-card">
                <h3>待处理举报</h3>
                <a href="report/list.php" style="text-decoration: none; color: inherit;">
                    <div class="number" style="cursor: pointer;"><?php echo $stats['reports']; ?></div>
                </a>
            </div>
        </div>
    </div>
</body>
</html>
