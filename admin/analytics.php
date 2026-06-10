<?php
/**
 * 数据统计分析页面
 */
require_once __DIR__ . '/common/session.php';
require_once __DIR__ . '/../common/db.php';
require_once __DIR__ . '/../common/functions.php';

checkAdminLogin();

global $pdo;

// 获取时间范围参数
$timeRange = $_GET['time_range'] ?? '7'; // 默认7天
$endDate = date('Y-m-d');
$startDate = date('Y-m-d', strtotime("-{$timeRange} days"));

// 素材统计数据
$materialStats = [];

// 各类型素材数量统计
$materialStats['total'] = [
    'videos' => $pdo->query("SELECT COUNT(*) FROM `video_materials` WHERE `status` = 1")->fetchColumn(),
    'image_texts' => $pdo->query("SELECT COUNT(*) FROM `image_text_materials` WHERE `status` = 1")->fetchColumn(),
    'video_texts' => $pdo->query("SELECT COUNT(*) FROM `video_text_materials` WHERE `status` = 1")->fetchColumn(),
    'texts' => $pdo->query("SELECT COUNT(*) FROM `text_materials` WHERE `status` = 1")->fetchColumn(),
];

// 热门素材排行（按下载数）
$topDownloaded = $pdo->query("
    SELECT 'video' as type, material_id, name, download_count, like_count, created_at
    FROM `video_materials`
    WHERE status = 1
    UNION ALL
    SELECT 'image_text' as type, material_id, 'Image+Text' as name, download_count, like_count, created_at
    FROM `image_text_materials`
    WHERE status = 1
    UNION ALL
    SELECT 'video_text' as type, material_id, 'Video+Text' as name, download_count, like_count, created_at
    FROM `video_text_materials`
    WHERE status = 1
    UNION ALL
    SELECT 'text' as type, material_id, SUBSTRING(content, 1, 50) as name, copy_count as download_count, like_count, created_at
    FROM `text_materials`
    WHERE status = 1
    ORDER BY download_count DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

// 热门素材排行（按点赞数）
$topLiked = $pdo->query("
    SELECT 'video' as type, material_id, name, download_count, like_count, created_at
    FROM `video_materials`
    WHERE status = 1
    UNION ALL
    SELECT 'image_text' as type, material_id, 'Image+Text' as name, download_count, like_count, created_at
    FROM `image_text_materials`
    WHERE status = 1
    UNION ALL
    SELECT 'video_text' as type, material_id, 'Video+Text' as name, download_count, like_count, created_at
    FROM `video_text_materials`
    WHERE status = 1
    UNION ALL
    SELECT 'text' as type, material_id, SUBSTRING(content, 1, 50) as name, copy_count as download_count, like_count, created_at
    FROM `text_materials`
    WHERE status = 1
    ORDER BY like_count DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

// 用户行为统计
$userStats = [];

// 用户总数
$userStats['total'] = $pdo->query("SELECT COUNT(*) FROM `users`")->fetchColumn();

// 新增用户（按时间范围）
$userStats['new_users'] = $pdo->query("
    SELECT COUNT(*) FROM `users`
    WHERE DATE(created_at) BETWEEN '{$startDate}' AND '{$endDate}'
")->fetchColumn();

// 活跃用户（有下载或收藏记录的用户）
$userStats['active_users'] = $pdo->query("
    SELECT COUNT(DISTINCT user_id) FROM `download_logs`
    WHERE DATE(created_at) BETWEEN '{$startDate}' AND '{$endDate}'
")->fetchColumn();

// VIP用户数
$userStats['vip_users'] = $pdo->query("
    SELECT COUNT(*) FROM `users`
    WHERE is_vip = 1 AND (vip_expire_time IS NULL OR vip_expire_time > NOW())
")->fetchColumn();

// 用户收藏统计
$favoriteStats = $pdo->query("
    SELECT material_type, COUNT(*) as favorite_count
    FROM `user_favorites`
    WHERE DATE(created_at) BETWEEN '{$startDate}' AND '{$endDate}'
    GROUP BY material_type
")->fetchAll(PDO::FETCH_ASSOC);

// 下载统计
$downloadStats = $pdo->query("
    SELECT material_type, COUNT(*) as download_count
    FROM `download_logs`
    WHERE DATE(created_at) BETWEEN '{$startDate}' AND '{$endDate}'
    GROUP BY material_type
")->fetchAll(PDO::FETCH_ASSOC);

// 每日趋势数据
$dailyStats = [];
for ($i = $timeRange - 1; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $dailyStats[$date] = [
        'date' => $date,
        'downloads' => $pdo->query("
            SELECT COUNT(*) FROM `download_logs`
            WHERE DATE(created_at) = '{$date}'
        ")->fetchColumn(),
        'favorites' => $pdo->query("
            SELECT COUNT(*) FROM `user_favorites`
            WHERE DATE(created_at) = '{$date}'
        ")->fetchColumn(),
        'new_users' => $pdo->query("
            SELECT COUNT(*) FROM `users`
            WHERE DATE(created_at) = '{$date}'
        ")->fetchColumn(),
    ];
}

// 分类统计
$categoryStats = $pdo->query("
    SELECT c.name, c.type, COUNT(cr.material_id) as material_count,
           SUM(CASE WHEN vm.material_id IS NOT NULL THEN vm.download_count ELSE 0 END +
               CASE WHEN itm.material_id IS NOT NULL THEN itm.download_count ELSE 0 END +
               CASE WHEN vtm.material_id IS NOT NULL THEN vtm.download_count ELSE 0 END +
               CASE WHEN tm.material_id IS NOT NULL THEN tm.copy_count ELSE 0 END) as total_downloads
    FROM `categories` c
    LEFT JOIN `category_relations` cr ON c.category_id = cr.category_id
    LEFT JOIN `video_materials` vm ON cr.material_id = vm.material_id AND cr.material_type = 1
    LEFT JOIN `image_text_materials` itm ON cr.material_id = itm.material_id AND cr.material_type = 2
    LEFT JOIN `video_text_materials` vtm ON cr.material_id = vtm.material_id AND cr.material_type = 3
    LEFT JOIN `text_materials` tm ON cr.material_id = tm.material_id AND cr.material_type = 4
    WHERE c.status = 1
    GROUP BY c.category_id, c.name, c.type
    ORDER BY c.type, c.sort
")->fetchAll(PDO::FETCH_ASSOC);

$typeNames = [1 => '单视频', 2 => '图片+文案', 3 => '视频+文案', 4 => '纯文案'];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>数据统计分析</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .admin-layout {
            display: flex;
            min-height: calc(100vh - 40px);
        }
        .main-content {
            flex: 1;
            margin-left: 250px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .page-title {
            font-size: 24px;
            font-weight: 600;
            color: #333;
        }
        .time-filter {
            display: flex;
            gap: 10px;
        }
        .time-filter select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-card h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        .stat-change {
            font-size: 12px;
            color: #27ae60;
        }
        .stat-change.negative {
            color: #e74c3c;
        }
        .section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .section h2 {
            font-size: 18px;
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .chart-container {
            height: 300px;
            background: #f8f9fa;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            font-size: 13px;
        }
        td {
            font-size: 13px;
        }
        .rank-number {
            width: 30px;
            text-align: center;
            font-weight: bold;
            color: #667eea;
        }
        .type-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }
        .type-1 { background: #e3f2fd; color: #1976d2; }
        .type-2 { background: #f3e5f5; color: #7b1fa2; }
        .type-3 { background: #e8f5e8; color: #388e3c; }
        .type-4 { background: #fff3e0; color: #f57c00; }
        .progress-bar {
            width: 100px;
            height: 6px;
            background: #eee;
            border-radius: 3px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background: #667eea;
            transition: width 0.3s;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="admin-layout">
        <?php include 'common/sidebar.php'; ?>
        <div class="main-content">
            <div class="container">
                <div class="page-header">
                    <h1 class="page-title">数据统计分析</h1>
                    <div class="time-filter">
                        <label>时间范围：</label>
                        <select onchange="changeTimeRange(this.value)">
                            <option value="7" <?php echo $timeRange == 7 ? 'selected' : ''; ?>>最近7天</option>
                            <option value="30" <?php echo $timeRange == 30 ? 'selected' : ''; ?>>最近30天</option>
                            <option value="90" <?php echo $timeRange == 90 ? 'selected' : ''; ?>>最近90天</option>
                        </select>
                    </div>
                </div>

                <!-- 核心指标 -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>素材总数</h3>
                        <div class="stat-number"><?php echo array_sum($materialStats['total']); ?></div>
                        <div class="stat-change">
                            单视频: <?php echo $materialStats['total']['videos']; ?> |
                            图文: <?php echo $materialStats['total']['image_texts']; ?> |
                            视频: <?php echo $materialStats['total']['video_texts']; ?> |
                            文案: <?php echo $materialStats['total']['texts']; ?>
                        </div>
                    </div>
                    <div class="stat-card">
                        <h3>用户总数</h3>
                        <div class="stat-number"><?php echo $userStats['total']; ?></div>
                        <div class="stat-change">新增: <?php echo $userStats['new_users']; ?> (<?php echo $timeRange; ?>天内)</div>
                    </div>
                    <div class="stat-card">
                        <h3>活跃用户</h3>
                        <div class="stat-number"><?php echo $userStats['active_users']; ?></div>
                        <div class="stat-change">有操作记录的用户数</div>
                    </div>
                    <div class="stat-card">
                        <h3>VIP用户</h3>
                        <div class="stat-number"><?php echo $userStats['vip_users']; ?></div>
                        <div class="stat-change">
                            转化率: <?php echo $userStats['total'] > 0 ? round($userStats['vip_users'] / $userStats['total'] * 100, 1) : 0; ?>%
                        </div>
                    </div>
                </div>

                <!-- 趋势图表 -->
                <div class="section">
                    <h2>用户行为趋势</h2>
                    <div class="chart-container">
                        <canvas id="trendChart" width="800" height="250"></canvas>
                    </div>
                </div>

                <!-- 热门素材排行 -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="section">
                        <h2>热门素材排行（下载量）</h2>
                        <table>
                            <thead>
                                <tr>
                                    <th>排名</th>
                                    <th>类型</th>
                                    <th>素材信息</th>
                                    <th>下载数</th>
                                    <th>点赞数</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topDownloaded as $index => $item): ?>
                                <tr>
                                    <td class="rank-number"><?php echo $index + 1; ?></td>
                                    <td>
                                        <span class="type-badge type-<?php echo $item['type'] == 'video' ? '1' : ($item['type'] == 'image_text' ? '2' : ($item['type'] == 'video_text' ? '3' : '4')); ?>">
                                            <?php echo $item['type'] == 'video' ? '视频' : ($item['type'] == 'image_text' ? '图文' : ($item['type'] == 'video_text' ? '视频' : '文案')); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td><?php echo $item['download_count']; ?></td>
                                    <td><?php echo $item['like_count']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="section">
                        <h2>热门素材排行（点赞数）</h2>
                        <table>
                            <thead>
                                <tr>
                                    <th>排名</th>
                                    <th>类型</th>
                                    <th>素材信息</th>
                                    <th>下载数</th>
                                    <th>点赞数</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topLiked as $index => $item): ?>
                                <tr>
                                    <td class="rank-number"><?php echo $index + 1; ?></td>
                                    <td>
                                        <span class="type-badge type-<?php echo $item['type'] == 'video' ? '1' : ($item['type'] == 'image_text' ? '2' : ($item['type'] == 'video_text' ? '3' : '4')); ?>">
                                            <?php echo $item['type'] == 'video' ? '视频' : ($item['type'] == 'image_text' ? '图文' : ($item['type'] == 'video_text' ? '视频' : '文案')); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td><?php echo $item['download_count']; ?></td>
                                    <td><?php echo $item['like_count']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 分类统计 -->
                <div class="section">
                    <h2>分类表现统计</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>分类名称</th>
                                <th>类型</th>
                                <th>素材数量</th>
                                <th>总下载量</th>
                                <th>平均下载量</th>
                                <th>表现</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $maxDownloads = max(array_column($categoryStats, 'total_downloads'));
                            foreach ($categoryStats as $category):
                                $avgDownloads = $category['material_count'] > 0 ? round($category['total_downloads'] / $category['material_count'], 1) : 0;
                                $progressWidth = $maxDownloads > 0 ? ($category['total_downloads'] / $maxDownloads * 100) : 0;
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($category['name']); ?></td>
                                <td>
                                    <span class="type-badge type-<?php echo $category['type']; ?>">
                                        <?php echo $typeNames[$category['type']] ?? '未知'; ?>
                                    </span>
                                </td>
                                <td><?php echo $category['material_count']; ?></td>
                                <td><?php echo $category['total_downloads']; ?></td>
                                <td><?php echo $avgDownloads; ?></td>
                                <td>
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $progressWidth; ?>%"></div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // 时间范围切换
        function changeTimeRange(range) {
            window.location.href = '?time_range=' + range;
        }

        // 绘制趋势图表
        const ctx = document.getElementById('trendChart').getContext('2d');
        const trendChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($dailyStats, 'date')); ?>,
                datasets: [{
                    label: '下载量',
                    data: <?php echo json_encode(array_column($dailyStats, 'downloads')); ?>,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4
                }, {
                    label: '收藏数',
                    data: <?php echo json_encode(array_column($dailyStats, 'favorites')); ?>,
                    borderColor: '#27ae60',
                    backgroundColor: 'rgba(39, 174, 96, 0.1)',
                    tension: 0.4
                }, {
                    label: '新增用户',
                    data: <?php echo json_encode(array_column($dailyStats, 'new_users')); ?>,
                    borderColor: '#f39c12',
                    backgroundColor: 'rgba(243, 156, 18, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>
