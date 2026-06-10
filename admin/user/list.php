<?php
/**
 * 用户列表
 */
require_once '../common/session.php';
require_once '../../common/db.php';
require_once '../../common/functions.php';

checkAdminLogin();

global $pdo;

$page = intval($_GET['page'] ?? 1);
$pageSize = 20;
$offset = ($page - 1) * $pageSize;

// 获取筛选和排序参数
$vipStatus = isset($_GET['vip_status']) ? intval($_GET['vip_status']) : -1; // -1表示全部
$platform = $_GET['platform'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$eightDigitId = trim($_GET['eight_digit_id'] ?? '');
$orderBy = $_GET['order_by'] ?? 'created_at';
$orderDir = strtoupper($_GET['order_dir'] ?? 'DESC');

// 验证排序字段和方向
$allowedOrderBy = ['created_at', 'vip_expire_time'];
if (!in_array($orderBy, $allowedOrderBy)) {
    $orderBy = 'created_at';
}
if (!in_array($orderDir, ['ASC', 'DESC'])) {
    $orderDir = 'DESC';
}

// 构建查询条件
$whereConditions = [];
$params = [];

// VIP状态筛选
if ($vipStatus >= 0) {
    $whereConditions[] = "u.`is_vip` = ?";
    $params[] = $vipStatus;
}

// 平台筛选
if (!empty($platform)) {
    $whereConditions[] = "u.`platform` = ?";
    $params[] = $platform;
}

// 注册时间范围筛选
if (!empty($startDate)) {
    $whereConditions[] = "DATE(u.`created_at`) >= ?";
    $params[] = $startDate;
}
if (!empty($endDate)) {
    $whereConditions[] = "DATE(u.`created_at`) <= ?";
    $params[] = $endDate;
}

// 8位数字ID搜索
if (!empty($eightDigitId)) {
    $whereConditions[] = "RIGHT(u.`user_id`, 8) = ?";
    $params[] = $eightDigitId;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// 获取用户列表
$sql = "SELECT u.`user_id`, u.`username`, u.`device_id`, u.`phone`, u.`email`,
               u.`apple_id`, u.`wechat_openid`, u.`platform`, u.`last_login_platform`,
               u.`is_vip`, u.`vip_expire_time`, u.`created_at`,
               (SELECT COUNT(*) FROM `payment_records` pr WHERE pr.`user_id` = u.`user_id` AND pr.`order_status` = 'paid') as payment_count,
               (SELECT COUNT(*) FROM `subscription_records` sr WHERE sr.`user_id` = u.`user_id` AND sr.`subscription_status` = 'active') as subscription_count,
               (SELECT COUNT(*) FROM `user_favorites` uf WHERE uf.`user_id` = u.`user_id`) as favorite_count
        FROM `users` u
        $whereClause
        ORDER BY u.`$orderBy` $orderDir
        LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
$params[] = $pageSize;
$params[] = $offset;
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取总数
$countSql = "SELECT COUNT(*) FROM `users` u $whereClause";
// 重建参数数组，排除LIMIT和OFFSET参数
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
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户管理</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        table {
            width: 100%;
            background: white;
            border-collapse: collapse;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        th, td {
            padding: 8px 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 13px;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            font-size: 12px;
        }
        .status {
            padding: 3px 6px;
            border-radius: 3px;
            font-size: 11px;
            display: inline-block;
        }
        .status-vip {
            background: #d4edda;
            color: #155724;
        }
        .status-normal {
            background: #f8d7da;
            color: #721c24;
        }
        .platform-tag {
            display: inline-block;
            padding: 2px 6px;
            background: #e3f2fd;
            color: #1976d2;
            border-radius: 10px;
            font-size: 11px;
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
        .filters {
            background: white;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        .filters select, .filters input {
            padding: 6px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
        }
        .filters label {
            font-weight: 500;
            margin-right: 5px;
        }
        .sort-link {
            color: #667eea;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .sort-link:hover {
            text-decoration: underline;
        }
        .account-info {
            font-size: 12px;
            color: #666;
        }
    </style>
    <script>
        function applyFilters() {
            const eightDigitId = document.querySelector('input[name="eight_digit_id"]').value.trim();
            const vipStatus = document.querySelector('select[name="vip_status"]').value;
            const platform = document.querySelector('select[name="platform"]').value;
            const startDate = document.querySelector('input[name="start_date"]').value;
            const endDate = document.querySelector('input[name="end_date"]').value;
            const orderBy = document.querySelector('select[name="order_by"]').value;
            const orderDir = document.querySelector('select[name="order_dir"]').value;

            const params = new URLSearchParams();
            if (eightDigitId) params.set('eight_digit_id', eightDigitId);
            if (vipStatus != -1) params.set('vip_status', vipStatus);
            if (platform) params.set('platform', platform);
            if (startDate) params.set('start_date', startDate);
            if (endDate) params.set('end_date', endDate);
            params.set('order_by', orderBy);
            params.set('order_dir', orderDir);

            window.location.href = '?' + params.toString();
        }
    </script>
</head>
<body>
    <div class="admin-layout">
        <?php include '../common/sidebar.php'; ?>
        <div class="main-content">
            <h1 style="margin: 0 0 12px 0; font-size: 20px;">用户管理</h1>

        <!-- 筛选和排序 -->
        <div class="filters">
            <div>
                <label>8位数字ID：</label>
                <input type="text" name="eight_digit_id" value="<?php echo htmlspecialchars($eightDigitId); ?>" placeholder="输入8位数字ID" maxlength="8" style="width: 120px;">
                <button onclick="applyFilters()" style="padding: 6px 12px; background: #667eea; color: white; border: none; border-radius: 4px; cursor: pointer;">搜索</button>
            </div>
            <div>
                <label>VIP状态：</label>
                <select name="vip_status" onchange="applyFilters()">
                    <option value="-1">全部</option>
                    <option value="1" <?php echo $vipStatus == 1 ? 'selected' : ''; ?>>VIP</option>
                    <option value="0" <?php echo $vipStatus == 0 ? 'selected' : ''; ?>>非VIP</option>
                </select>
            </div>
            <div>
                <label>平台：</label>
                <select name="platform" onchange="applyFilters()">
                    <option value="">全部平台</option>
                    <option value="ios" <?php echo $platform == 'ios' ? 'selected' : ''; ?>>iOS</option>
                    <option value="android" <?php echo $platform == 'android' ? 'selected' : ''; ?>>Android</option>
                </select>
            </div>
            <div>
                <label>注册时间：</label>
                <input type="date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>" onchange="applyFilters()">
                <span>至</span>
                <input type="date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>" onchange="applyFilters()">
            </div>
            <div>
                <label>排序：</label>
                <select name="order_by" onchange="applyFilters()">
                    <option value="created_at" <?php echo $orderBy == 'created_at' ? 'selected' : ''; ?>>注册时间</option>
                    <option value="vip_expire_time" <?php echo $orderBy == 'vip_expire_time' ? 'selected' : ''; ?>>VIP到期时间</option>
                </select>
                <select name="order_dir" onchange="applyFilters()">
                    <option value="DESC" <?php echo $orderDir == 'DESC' ? 'selected' : ''; ?>>降序</option>
                    <option value="ASC" <?php echo $orderDir == 'ASC' ? 'selected' : ''; ?>>升序</option>
                </select>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>用户ID</th>
                    <th>账号信息</th>
                    <th>登录方式</th>
                    <th>收藏数量</th>
                    <th>VIP状态</th>
                    <th>VIP到期时间</th>
                    <th>平台</th>
                    <th>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['order_by' => 'created_at', 'order_dir' => $orderBy == 'created_at' && $orderDir == 'DESC' ? 'ASC' : 'DESC'])); ?>" class="sort-link">
                            注册时间 <?php if ($orderBy == 'created_at'): ?><?php echo $orderDir == 'DESC' ? '↓' : '↑'; ?><?php endif; ?>
                        </a>
                    </th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['user_id']); ?></td>
                    <td>
                        <div class="account-info">
                            <?php
                            $accountInfo = [];
                            if ($user['username']) {
                                $accountInfo[] = '用户名: ' . htmlspecialchars($user['username']);
                            }
                            if ($user['apple_id']) {
                                $accountInfo[] = 'Apple ID: ' . htmlspecialchars(substr($user['apple_id'], 0, 20)) . '...';
                            }
                            if ($user['wechat_openid']) {
                                $accountInfo[] = '微信: ' . htmlspecialchars(substr($user['wechat_openid'], 0, 20)) . '...';
                            }
                            if ($user['phone']) {
                                $accountInfo[] = '手机: ' . htmlspecialchars($user['phone']);
                            }
                            if ($user['device_id']) {
                                $accountInfo[] = '设备: ' . htmlspecialchars(substr($user['device_id'], 0, 12)) . '...';
                            }
                            echo !empty($accountInfo) ? implode('<br>', $accountInfo) : '游客用户';
                            ?>
                        </div>
                    </td>
                    <td>
                        <?php
                        $loginType = '游客';
                        if (!empty($user['apple_id'])) {
                            $loginType = 'Apple ID';
                        }
                        echo '<span class="platform-tag">' . htmlspecialchars($loginType) . '</span>';
                        ?>
                    </td>
                    <td>
                        <strong><?php echo intval($user['favorite_count'] ?? 0); ?></strong>
                    </td>
                    <td>
                        <span class="status <?php echo $user['is_vip'] ? 'status-vip' : 'status-normal'; ?>">
                            <?php echo $user['is_vip'] ? 'VIP' : '普通'; ?>
                        </span>
                    </td>
                    <td>
                        <?php
                        if ($user['is_vip'] && $user['vip_expire_time']) {
                            $expireTime = strtotime($user['vip_expire_time']);
                            $now = time();
                            if ($expireTime > $now) {
                                echo date('Y-m-d H:i', $expireTime);
                            } else {
                                echo '<span style="color: #e74c3c;">已过期</span>';
                            }
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                    <td>
                        <?php if ($user['platform']): ?>
                            <span class="platform-tag"><?php echo strtoupper($user['platform']); ?></span>
                        <?php else: ?>
                            <span style="color: #999;">-</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo date('Y-m-d H:i', strtotime($user['created_at'])); ?></td>
                    <td>
                        <a href="detail.php?user_id=<?php echo $user['user_id']; ?>" class="btn btn-view">查看详情</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php
            $queryParams = $_GET;
            for ($i = 1; $i <= $totalPages; $i++):
                $queryParams['page'] = $i;
            ?>
                <a href="?<?php echo http_build_query($queryParams); ?>" class="<?php echo $i == $page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        </div>
    </div>
</body>
</html>