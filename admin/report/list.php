<?php
/**
 * 举报管理列表
 */
require_once '../common/session.php';
require_once '../../common/db.php';

checkAdminLogin();

global $pdo;

$status = intval($_GET['status'] ?? -1); // -1全部 0待处理 1已处理 2已驳回
$page = intval($_GET['page'] ?? 1);
$pageSize = 20;
$offset = ($page - 1) * $pageSize;

$sql = "SELECT mr.*, u.user_id, u.username, u.device_id
        FROM `material_reports` mr
        LEFT JOIN `users` u ON mr.user_id = u.user_id";
$params = [];

if ($status >= 0) {
    $sql .= " WHERE mr.status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY mr.created_at DESC LIMIT ? OFFSET ?";
$params[] = $pageSize;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取总数
$countSql = "SELECT COUNT(*) FROM `material_reports`";
$countParams = [];
if ($status >= 0) {
    $countSql .= " WHERE status = ?";
    $countParams[] = $status;
}
$stmt = $pdo->prepare($countSql);
$stmt->execute($countParams);
$total = $stmt->fetchColumn();
$totalPages = ceil($total / $pageSize);

$typeNames = [1 => '单视频', 2 => '图片+文案', 3 => '视频+文案', 4 => '纯文案'];
$reportTypeNames = [
    'porn' => '色情低俗',
    'violence' => '暴力血腥',
    'ad' => '广告骚扰',
    'illegal' => '违法违规',
    'other' => '其他'
];
$statusNames = [0 => '待处理', 1 => '已处理', 2 => '已驳回'];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>举报管理</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .header {
            background: white;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .header h1 {
            display: inline-block;
        }
        .header a {
            float: right;
            display: inline-block;
            padding: 8px 16px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        .filter {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        .filter select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        table {
            width: 100%;
            background: white;
            border-collapse: collapse;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .status {
            padding: 3px 6px;
            border-radius: 3px;
            font-size: 11px;
        }
        .status-0 {
            background: #fff3cd;
            color: #856404;
        }
        .status-1 {
            background: #d4edda;
            color: #155724;
        }
        .status-2 {
            background: #f8d7da;
            color: #721c24;
        }
        .btn {
            padding: 4px 10px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 12px;
        }
        .btn-view {
            background: #667eea;
            color: white;
        }
        .pagination {
            margin-top: 20px;
            text-align: center;
        }
        .pagination a {
            display: inline-block;
            padding: 8px 12px;
            margin: 0 4px;
            background: white;
            color: #667eea;
            text-decoration: none;
            border-radius: 4px;
        }
        .pagination a.active {
            background: #667eea;
            color: white;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include '../common/sidebar.php'; ?>
        <div class="main-content">
            <h1 style="margin: 0 0 12px 0; font-size: 20px;">举报管理</h1>
        <div class="filter">
            <form method="GET">
                <label>筛选状态：</label>
                <select name="status" onchange="this.form.submit()">
                    <option value="-1" <?php echo $status == -1 ? 'selected' : ''; ?>>全部</option>
                    <option value="0" <?php echo $status == 0 ? 'selected' : ''; ?>>待处理</option>
                    <option value="1" <?php echo $status == 1 ? 'selected' : ''; ?>>已处理</option>
                    <option value="2" <?php echo $status == 2 ? 'selected' : ''; ?>>已驳回</option>
                </select>
            </form>
        </div>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>用户</th>
                    <th>素材类型</th>
                    <th>素材ID</th>
                    <th>举报类型</th>
                    <th>举报内容</th>
                    <th>状态</th>
                    <th>举报时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reports as $report): ?>
                <tr>
                    <td><?php echo $report['id']; ?></td>
                    <td>
                        <?php
                        // 优先显示8位数的真实用户ID
                        if (!empty($report['user_id'])) {
                            $userInfo = htmlspecialchars($report['user_id']);
                            // 如果有用户名，在ID后面显示用户名
                            if (!empty($report['username'])) {
                                $userInfo .= ' (' . htmlspecialchars($report['username']) . ')';
                            } elseif (!empty($report['device_id'])) {
                                // 如果是游客，显示设备ID前8位
                                $userInfo .= ' [游客:' . htmlspecialchars(substr($report['device_id'], 0, 8)) . '...]';
                            }
                            echo $userInfo;
                        } else {
                            echo '未知';
                        }
                        ?>
                    </td>
                    <td><?php echo $typeNames[$report['material_type']] ?? '未知'; ?></td>
                    <td><?php echo substr($report['material_id'], 0, 8); ?>...</td>
                    <td><?php echo $reportTypeNames[$report['report_type']] ?? $report['report_type']; ?></td>
                    <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                        <?php echo htmlspecialchars(mb_substr($report['report_content'] ?? '', 0, 30)); ?>
                    </td>
                    <td>
                        <span class="status status-<?php echo $report['status']; ?>">
                            <?php echo $statusNames[$report['status']] ?? '未知'; ?>
                        </span>
                    </td>
                    <td><?php echo date('Y-m-d H:i', strtotime($report['created_at'])); ?></td>
                    <td>
                        <a href="detail.php?id=<?php echo $report['id']; ?>" class="btn btn-view">查看详情</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?status=<?php echo $status; ?>&page=<?php echo $i; ?>" class="<?php echo $i == $page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
