<?php
/**
 * 收藏列表管理
 */
require_once '../common/session.php';
require_once '../../common/db.php';
require_once '../../common/functions.php';

checkAdminLogin();

global $pdo;

$page = intval($_GET['page'] ?? 1);
$pageSize = 20;
$offset = ($page - 1) * $pageSize;

// 获取筛选参数
$userId = $_GET['user_id'] ?? '';
$materialType = isset($_GET['material_type']) ? intval($_GET['material_type']) : -1; // -1表示全部
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

// 构建查询条件
$whereConditions = [];
$params = [];

if (!empty($userId)) {
    $whereConditions[] = "uf.`user_id` = ?";
    $params[] = $userId;
}

if ($materialType > 0) {
    $whereConditions[] = "uf.`material_type` = ?";
    $params[] = $materialType;
}

if (!empty($startDate)) {
    $whereConditions[] = "DATE(uf.`created_at`) >= ?";
    $params[] = $startDate;
}

if (!empty($endDate)) {
    $whereConditions[] = "DATE(uf.`created_at`) <= ?";
    $params[] = $endDate;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// 获取收藏列表
$sql = "SELECT uf.`id`, uf.`user_id`, uf.`material_id`, uf.`material_type`, uf.`created_at`,
               u.`username`, u.`device_id`
        FROM `user_favorites` uf
        LEFT JOIN `users` u ON uf.`user_id` = u.`user_id`
        $whereClause
        ORDER BY uf.`created_at` DESC
        LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
$params[] = $pageSize;
$params[] = $offset;
$stmt->execute($params);
$favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取每个收藏的详细信息
foreach ($favorites as &$favorite) {
    switch ($favorite['material_type']) {
        case 1: // 单视频
            $stmt = $pdo->prepare("SELECT `name`, `video_url`, `thumbnail_url`
                                 FROM `video_materials` WHERE `material_id` = ?");
            $stmt->execute([$favorite['material_id']]);
            $detail = $stmt->fetch(PDO::FETCH_ASSOC);
            $favorite['material_name'] = $detail['name'] ?? '';
            $favorite['material_url'] = $detail['video_url'] ?? '';
            $favorite['thumbnail_url'] = $detail['thumbnail_url'] ?? '';
            break;
        case 2: // 图片+文案
            $stmt = $pdo->prepare("SELECT `material_id` FROM `image_text_materials` WHERE `material_id` = ?");
            $stmt->execute([$favorite['material_id']]);
            $detail = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($detail) {
                $stmt = $pdo->prepare("SELECT `image_url` FROM `image_text_images`
                                      WHERE `material_id` = ? ORDER BY `sort` ASC LIMIT 1");
                $stmt->execute([$favorite['material_id']]);
                $img = $stmt->fetch(PDO::FETCH_ASSOC);
                $favorite['thumbnail_url'] = $img['image_url'] ?? '';
            }
            $favorite['material_name'] = '图片+文案素材';
            $favorite['material_url'] = '';
            break;
        case 3: // 视频+文案
            $stmt = $pdo->prepare("SELECT `video_url`, `thumbnail_url`
                                 FROM `video_text_materials` WHERE `material_id` = ?");
            $stmt->execute([$favorite['material_id']]);
            $detail = $stmt->fetch(PDO::FETCH_ASSOC);
            $favorite['material_name'] = '视频+文案素材';
            $favorite['material_url'] = $detail['video_url'] ?? '';
            $favorite['thumbnail_url'] = $detail['thumbnail_url'] ?? '';
            break;
        case 4: // 纯文案
            $stmt = $pdo->prepare("SELECT `content` FROM `text_materials` WHERE `material_id` = ?");
            $stmt->execute([$favorite['material_id']]);
            $detail = $stmt->fetch(PDO::FETCH_ASSOC);
            $favorite['material_name'] = mb_substr($detail['content'] ?? '纯文案素材', 0, 30) . '...';
            $favorite['material_url'] = '';
            $favorite['thumbnail_url'] = '';
            break;
        default:
            $favorite['material_name'] = '未知类型';
            $favorite['material_url'] = '';
            $favorite['thumbnail_url'] = '';
    }
}

// 获取总数
$countSql = "SELECT COUNT(*) FROM `user_favorites` uf $whereClause";
$countParams = [];
foreach ($params as $i => $param) {
    if ($i < count($params) - 2) {
        $countParams[] = $param;
    }
}
$stmt = $pdo->prepare($countSql);
$stmt->execute($countParams);
$total = $stmt->fetchColumn();
$totalPages = ceil($total / $pageSize);

// 获取类型列表（用于筛选）
$typeNames = [
    1 => '单视频',
    2 => '图片+文案',
    3 => '视频+文案',
    4 => '纯文案'
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>收藏管理</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
        }
        .main-content {
            padding: 20px;
        }
        .page-header {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .page-header h1 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        .filter-bar {
            background: white;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .filter-form {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .filter-group label {
            font-size: 12px;
            color: #666;
        }
        .filter-group input,
        .filter-group select {
            padding: 6px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .btn {
            padding: 6px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            display: inline-block;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5568d3;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        table {
            width: 100%;
            background: white;
            border-collapse: collapse;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .type-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        .type-1 { background: #e3f2fd; color: #1976d2; }
        .type-2 { background: #f3e5f5; color: #7b1fa2; }
        .type-3 { background: #e8f5e9; color: #388e3c; }
        .type-4 { background: #fff3e0; color: #f57c00; }
        .thumbnail {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
        }
        .pagination {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 10px;
        }
        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
        }
        .pagination a:hover {
            background: #f0f0f0;
        }
        .pagination .current {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <?php include '../common/sidebar.php'; ?>
    <div class="main-content">
        <div class="page-header">
            <h1>收藏管理</h1>
            <p style="color: #666; font-size: 14px;">共 <?php echo $total; ?> 条收藏记录</p>
        </div>

        <div class="filter-bar">
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <label>用户ID</label>
                    <input type="text" name="user_id" value="<?php echo htmlspecialchars($userId); ?>" placeholder="输入用户ID">
                </div>
                <div class="filter-group">
                    <label>素材类型</label>
                    <select name="material_type">
                        <option value="-1" <?php echo $materialType == -1 ? 'selected' : ''; ?>>全部</option>
                        <?php foreach ($typeNames as $type => $name): ?>
                            <option value="<?php echo $type; ?>" <?php echo $materialType == $type ? 'selected' : ''; ?>>
                                <?php echo $name; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>开始日期</label>
                    <input type="date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
                </div>
                <div class="filter-group">
                    <label>结束日期</label>
                    <input type="date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary">筛选</button>
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <a href="list.php" class="btn btn-secondary">重置</a>
                </div>
            </form>
        </div>

        <?php if (empty($favorites)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <p>暂无收藏记录</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>用户信息</th>
                        <th>素材类型</th>
                        <th>素材信息</th>
                        <th>缩略图</th>
                        <th>收藏时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($favorites as $favorite): ?>
                        <tr>
                            <td><?php echo $favorite['id']; ?></td>
                            <td>
                                <div>ID: <?php echo htmlspecialchars($favorite['user_id']); ?></div>
                                <?php if ($favorite['username']): ?>
                                    <div style="font-size: 12px; color: #666;"><?php echo htmlspecialchars($favorite['username']); ?></div>
                                <?php endif; ?>
                                <?php if ($favorite['device_id']): ?>
                                    <div style="font-size: 12px; color: #999;">设备: <?php echo htmlspecialchars($favorite['device_id']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="type-badge type-<?php echo $favorite['material_type']; ?>">
                                    <?php echo $typeNames[$favorite['material_type']] ?? '未知'; ?>
                                </span>
                            </td>
                            <td>
                                <div style="font-weight: 500;"><?php echo htmlspecialchars($favorite['material_name']); ?></div>
                                <div style="font-size: 12px; color: #666; margin-top: 4px;">
                                    ID: <?php echo htmlspecialchars($favorite['material_id']); ?>
                                </div>
                                <?php if ($favorite['material_url']): ?>
                                    <div style="font-size: 11px; color: #999; margin-top: 2px; max-width: 300px; overflow: hidden; text-overflow: ellipsis;">
                                        <?php echo htmlspecialchars($favorite['material_url']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($favorite['thumbnail_url']): ?>
                                    <img src="<?php echo htmlspecialchars($favorite['thumbnail_url']); ?>"
                                         alt="缩略图"
                                         class="thumbnail"
                                         onerror="this.style.display='none'">
                                <?php else: ?>
                                    <span style="color: #999; font-size: 12px;">无缩略图</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('Y-m-d H:i:s', strtotime($favorite['created_at'])); ?></td>
                            <td>
                                <a href="delete.php?id=<?php echo $favorite['id']; ?>"
                                   class="btn btn-danger"
                                   onclick="return confirm('确定要删除这条收藏记录吗？');">删除</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&user_id=<?php echo urlencode($userId); ?>&material_type=<?php echo $materialType; ?>&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>">上一页</a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>&user_id=<?php echo urlencode($userId); ?>&material_type=<?php echo $materialType; ?>&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&user_id=<?php echo urlencode($userId); ?>&material_type=<?php echo $materialType; ?>&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>">下一页</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>

