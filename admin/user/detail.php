<?php
/**
 * 用户详情
 */
require_once '../common/session.php';
require_once '../../common/db.php';

checkAdminLogin();

global $pdo;

$userId = $_GET['user_id'] ?? '';

if (empty($userId)) {
    header('Location: list.php');
    exit;
}

// 获取用户基本信息
$stmt = $pdo->prepare("SELECT * FROM `users` WHERE `user_id` = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: list.php');
    exit;
}

// 获取支付记录（Android）
$stmt = $pdo->prepare("SELECT * FROM `payment_records` WHERE `user_id` = ? ORDER BY `created_at` DESC");
$stmt->execute([$userId]);
$paymentRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取订阅信息（iOS）
$stmt = $pdo->prepare("SELECT * FROM `subscription_records` WHERE `user_id` = ? ORDER BY `created_at` DESC");
$stmt->execute([$userId]);
$subscriptionRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取下载记录
$stmt = $pdo->prepare("SELECT * FROM `download_logs` WHERE `user_id` = ? ORDER BY `created_at` DESC LIMIT 50");
$stmt->execute([$userId]);
$downloadLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取收藏记录（包含素材详细信息）
$stmt = $pdo->prepare("SELECT uf.*,
    CASE
        WHEN uf.material_type = 1 THEN vm.name
        WHEN uf.material_type = 2 THEN (SELECT GROUP_CONCAT(itc.content SEPARATOR ' | ') FROM image_text_contents itc WHERE itc.material_id = uf.material_id LIMIT 3)
        WHEN uf.material_type = 3 THEN (SELECT GROUP_CONCAT(vtc.content SEPARATOR ' | ') FROM video_text_contents vtc WHERE vtc.material_id = uf.material_id LIMIT 3)
        WHEN uf.material_type = 4 THEN tm.content
        ELSE '未知素材'
    END as material_info,
    CASE
        WHEN uf.material_type = 1 THEN vm.thumbnail_url
        WHEN uf.material_type = 2 THEN (SELECT iti.image_url FROM image_text_images iti WHERE iti.material_id = uf.material_id ORDER BY iti.sort ASC LIMIT 1)
        WHEN uf.material_type = 3 THEN vtm.thumbnail_url
        ELSE NULL
    END as thumbnail_url
FROM `user_favorites` uf
LEFT JOIN `video_materials` vm ON uf.material_id = vm.material_id AND uf.material_type = 1
LEFT JOIN `video_text_materials` vtm ON uf.material_id = vtm.material_id AND uf.material_type = 3
LEFT JOIN `text_materials` tm ON uf.material_id = tm.material_id AND uf.material_type = 4
WHERE uf.user_id = ?
ORDER BY uf.created_at DESC
LIMIT 50");
$stmt->execute([$userId]);
$favoriteLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$typeNames = [1 => '单视频', 2 => '图片+文案', 3 => '视频+文案', 4 => '纯文案'];
$paymentMethodNames = ['alipay' => '支付宝', 'wechat' => '微信支付'];
$orderStatusNames = ['pending' => '待支付', 'paid' => '已支付', 'failed' => '支付失败', 'refunded' => '已退款'];
$subscriptionStatusNames = ['active' => '有效', 'expired' => '已过期', 'cancelled' => '已取消', 'refunded' => '已退款'];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户详情</title>
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
            max-width: 1200px;
            margin: 0 auto;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #667eea;
            text-decoration: none;
            padding: 8px 16px;
            background: white;
            border-radius: 4px;
        }
        .section {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .section h2 {
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
            font-size: 18px;
        }
        .material-thumbnail {
            width: 60px;
            height: 40px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #ddd;
        }
        .material-info {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .material-content {
            max-width: 400px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 12px;
            color: #666;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .info-item {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        .info-item label {
            display: block;
            font-weight: 600;
            color: #666;
            margin-bottom: 5px;
            font-size: 12px;
        }
        .info-item .value {
            color: #333;
            font-size: 14px;
        }
        .status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
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
        .status-paid {
            background: #d4edda;
            color: #155724;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        .status-failed {
            background: #f8d7da;
            color: #721c24;
        }
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        .status-expired {
            background: #f8d7da;
            color: #721c24;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            font-size: 12px;
        }
        td {
            font-size: 13px;
        }
        .empty-message {
            text-align: center;
            padding: 40px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include '../common/sidebar.php'; ?>
        <div class="main-content">
            <div class="container">
                <a href="list.php" class="back-link">← 返回列表</a>

        <!-- 基本信息 -->
        <div class="section">
            <h2>基本信息</h2>
            <div class="info-grid">
                <div class="info-item">
                    <label>用户ID</label>
                    <div class="value"><?php echo htmlspecialchars($user['user_id']); ?></div>
                </div>
                <div class="info-item">
                    <label>账号信息</label>
                    <div class="value">
                        <?php
                        $accountInfo = [];
                        if ($user['username']) {
                            $accountInfo[] = '用户名: ' . htmlspecialchars($user['username']);
                        }
                        if ($user['apple_id']) {
                            $accountInfo[] = 'Apple ID: ' . htmlspecialchars($user['apple_id']);
                        }
                        if ($user['wechat_openid']) {
                            $accountInfo[] = '微信OpenID: ' . htmlspecialchars($user['wechat_openid']);
                        }
                        if ($user['phone']) {
                            $accountInfo[] = '手机号: ' . htmlspecialchars($user['phone']);
                        }
                        if ($user['email']) {
                            $accountInfo[] = '邮箱: ' . htmlspecialchars($user['email']);
                        }
                        if ($user['device_id']) {
                            $accountInfo[] = '设备ID: ' . htmlspecialchars($user['device_id']);
                        }
                        echo !empty($accountInfo) ? implode('<br>', $accountInfo) : '游客用户';
                        ?>
                    </div>
                </div>
                <div class="info-item">
                    <label>平台</label>
                    <div class="value"><?php echo $user['platform'] ? strtoupper($user['platform']) : '-'; ?></div>
                </div>
                <div class="info-item">
                    <label>最后登录平台</label>
                    <div class="value"><?php echo $user['last_login_platform'] ? strtoupper($user['last_login_platform']) : '-'; ?></div>
                </div>
                <div class="info-item">
                    <label>注册时间</label>
                    <div class="value"><?php echo date('Y-m-d H:i:s', strtotime($user['created_at'])); ?></div>
                </div>
                <div class="info-item">
                    <label>用户类型</label>
                    <div class="value"><?php echo $user['user_type'] == 1 ? '注册用户' : '游客'; ?></div>
                </div>
            </div>
        </div>

        <!-- VIP信息 -->
        <div class="section">
            <h2>VIP信息</h2>
            <div class="info-grid">
                <div class="info-item">
                    <label>VIP状态</label>
                    <div class="value">
                        <span class="status <?php echo $user['is_vip'] ? 'status-vip' : 'status-normal'; ?>">
                            <?php echo $user['is_vip'] ? 'VIP' : '普通用户'; ?>
                        </span>
                    </div>
                </div>
                <div class="info-item">
                    <label>VIP到期时间</label>
                    <div class="value">
                        <?php
                        if ($user['is_vip'] && $user['vip_expire_time']) {
                            $expireTime = strtotime($user['vip_expire_time']);
                            $now = time();
                            echo date('Y-m-d H:i:s', $expireTime);
                            if ($expireTime <= $now) {
                                echo ' <span style="color: #e74c3c;">(已过期)</span>';
                            }
                        } else {
                            echo '-';
                        }
                        ?>
                    </div>
                </div>
                <div class="info-item">
                    <label>已下载数量</label>
                    <div class="value"><?php echo $user['download_count']; ?></div>
                </div>
            </div>
        </div>

        <!-- 支付记录（Android） -->
        <div class="section">
            <h2>支付记录（Android）</h2>
            <?php if (!empty($paymentRecords)): ?>
            <table>
                <thead>
                    <tr>
                        <th>订单ID</th>
                        <th>产品ID</th>
                        <th>支付方式</th>
                        <th>金额</th>
                        <th>状态</th>
                        <th>交易ID</th>
                        <th>创建时间</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paymentRecords as $record): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($record['order_id']); ?></td>
                        <td><?php echo htmlspecialchars($record['product_id']); ?></td>
                        <td><?php echo $paymentMethodNames[$record['payment_method']] ?? $record['payment_method']; ?></td>
                        <td>¥<?php echo number_format($record['amount'], 2); ?></td>
                        <td>
                            <span class="status status-<?php echo $record['order_status']; ?>">
                                <?php echo $orderStatusNames[$record['order_status']] ?? $record['order_status']; ?>
                            </span>
                        </td>
                        <td><?php echo $record['transaction_id'] ? htmlspecialchars($record['transaction_id']) : '-'; ?></td>
                        <td><?php echo date('Y-m-d H:i:s', strtotime($record['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <div class="empty-message">暂无支付记录</div>
            <?php endif; ?>
        </div>

        <!-- 订阅信息（iOS） -->
        <div class="section">
            <h2>订阅信息（iOS）</h2>
            <?php if (!empty($subscriptionRecords)): ?>
            <table>
                <thead>
                    <tr>
                        <th>订阅ID</th>
                        <th>产品ID</th>
                        <th>交易ID</th>
                        <th>状态</th>
                        <th>开始时间</th>
                        <th>到期时间</th>
                        <th>创建时间</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($subscriptionRecords as $record): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($record['subscription_id']); ?></td>
                        <td><?php echo htmlspecialchars($record['product_id']); ?></td>
                        <td><?php echo htmlspecialchars($record['transaction_id']); ?></td>
                        <td>
                            <span class="status status-<?php echo $record['subscription_status']; ?>">
                                <?php echo $subscriptionStatusNames[$record['subscription_status']] ?? $record['subscription_status']; ?>
                            </span>
                        </td>
                        <td><?php echo date('Y-m-d H:i:s', strtotime($record['start_time'])); ?></td>
                        <td>
                            <?php
                            echo date('Y-m-d H:i:s', strtotime($record['expire_time']));
                            $expireTime = strtotime($record['expire_time']);
                            $now = time();
                            if ($record['subscription_status'] == 'active' && $expireTime <= $now) {
                                echo ' <span style="color: #e74c3c;">(已过期)</span>';
                            }
                            ?>
                        </td>
                        <td><?php echo date('Y-m-d H:i:s', strtotime($record['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <div class="empty-message">暂无订阅记录</div>
            <?php endif; ?>
        </div>

        <!-- 下载记录 -->
        <div class="section">
            <h2>下载记录（最近50条）</h2>
            <?php if (!empty($downloadLogs)): ?>
            <table>
                <thead>
                    <tr>
                        <th>素材类型</th>
                        <th>素材ID</th>
                        <th>下载类型</th>
                        <th>下载时间</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($downloadLogs as $log): ?>
                    <tr>
                        <td><?php echo $typeNames[$log['material_type']] ?? '未知'; ?></td>
                        <td><?php echo htmlspecialchars($log['material_id']); ?></td>
                        <td><?php echo htmlspecialchars($log['download_type'] ?? '-'); ?></td>
                        <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <div class="empty-message">暂无下载记录</div>
            <?php endif; ?>
        </div>

        <!-- 收藏记录 -->
        <div class="section">
            <h2>收藏记录（最近50条）</h2>
            <?php if (!empty($favoriteLogs)): ?>
            <table>
                <thead>
                    <tr>
                        <th width="80">缩略图</th>
                        <th>素材类型</th>
                        <th>素材信息</th>
                        <th>素材内容</th>
                        <th>收藏时间</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($favoriteLogs as $log): ?>
                    <tr>
                        <td>
                            <?php if (!empty($log['thumbnail_url'])): ?>
                                <img src="<?php echo htmlspecialchars($log['thumbnail_url']); ?>" class="material-thumbnail" alt="缩略图">
                            <?php else: ?>
                                <span style="color: #ccc; font-size: 11px;">无图</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status status-<?php echo $log['material_type']; ?>">
                                <?php echo $typeNames[$log['material_type']] ?? '未知'; ?>
                            </span>
                        </td>
                        <td class="material-info">
                            <?php
                            $info = $log['material_info'] ?: '未知素材';
                            if ($log['material_type'] == 1) {
                                // 单视频显示名称
                                echo htmlspecialchars(mb_substr($info, 0, 30));
                            } elseif ($log['material_type'] == 2) {
                                // 图片+文案显示文案预览
                                echo htmlspecialchars(mb_substr($info, 0, 30));
                            } elseif ($log['material_type'] == 3) {
                                // 视频+文案显示文案预览
                                echo htmlspecialchars(mb_substr($info, 0, 30));
                            } elseif ($log['material_type'] == 4) {
                                // 纯文案显示内容预览
                                echo htmlspecialchars(mb_substr($info, 0, 30));
                            }
                            if (mb_strlen($info) > 30) {
                                echo '...';
                            }
                            ?>
                        </td>
                        <td class="material-content">
                            <?php
                            $content = $log['material_info'] ?: '未知素材';
                            echo htmlspecialchars(mb_substr($content, 0, 50));
                            if (mb_strlen($content) > 50) {
                                echo '...';
                            }
                            ?>
                        </td>
                        <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <div class="empty-message">暂无收藏记录</div>
            <?php endif; ?>
        </div>
            </div>
        </div>
    </div>
</body>
</html>