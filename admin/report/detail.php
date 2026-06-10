<?php
/**
 * 举报详情和处理
 */
require_once '../common/session.php';
require_once '../../common/db.php';

checkAdminLogin();

global $pdo;

$reportId = intval($_GET['id'] ?? 0);

if (empty($reportId)) {
    header('Location: list.php');
    exit;
}

// 获取举报信息
$stmt = $pdo->prepare("SELECT mr.*, u.user_id, u.username, u.device_id
                       FROM `material_reports` mr
                       LEFT JOIN `users` u ON mr.user_id = u.user_id
                       WHERE mr.id = ?");
$stmt->execute([$reportId]);
$report = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$report) {
    header('Location: list.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = intval($_POST['status'] ?? 0);
    $adminRemark = trim($_POST['admin_remark'] ?? '');

    try {
        $stmt = $pdo->prepare("UPDATE `material_reports`
                              SET `status` = ?, `admin_remark` = ?, `updated_at` = NOW()
                              WHERE `id` = ?");
        $stmt->execute([$status, $adminRemark, $reportId]);

        $success = '处理成功！';

        // 重新获取数据
        $stmt = $pdo->prepare("SELECT mr.*, u.user_id, u.username, u.device_id
                              FROM `material_reports` mr
                              LEFT JOIN `users` u ON mr.user_id = u.user_id
                              WHERE mr.id = ?");
        $stmt->execute([$reportId]);
        $report = $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        $error = '处理失败：' . $e->getMessage();
    }
}

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
    <title>举报详情</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            margin-bottom: 30px;
        }
        .info-group {
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        .info-group label {
            display: block;
            font-weight: 600;
            color: #666;
            margin-bottom: 5px;
        }
        .info-group .value {
            color: #333;
        }
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            min-height: 100px;
            font-family: inherit;
        }
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        button {
            padding: 12px 24px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background: #5568d3;
        }
        .error {
            background: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .success {
            background: #efe;
            color: #3c3;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #667eea;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="list.php" class="back-link">← 返回列表</a>
        <h1>举报详情</h1>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="info-group">
            <label>用户信息</label>
            <div class="value">
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
            </div>
        </div>

        <div class="info-group">
            <label>素材类型</label>
            <div class="value"><?php echo $typeNames[$report['material_type']] ?? '未知'; ?></div>
        </div>

        <div class="info-group">
            <label>素材ID</label>
            <div class="value"><?php echo htmlspecialchars($report['material_id']); ?></div>
        </div>

        <div class="info-group">
            <label>举报类型</label>
            <div class="value"><?php echo $reportTypeNames[$report['report_type']] ?? $report['report_type']; ?></div>
        </div>

        <div class="info-group">
            <label>举报内容</label>
            <div class="value"><?php echo htmlspecialchars($report['report_content'] ?? '无'); ?></div>
        </div>

        <div class="info-group">
            <label>举报时间</label>
            <div class="value"><?php echo date('Y-m-d H:i:s', strtotime($report['created_at'])); ?></div>
        </div>

        <div class="info-group">
            <label>当前状态</label>
            <div class="value"><?php echo $statusNames[$report['status']] ?? '未知'; ?></div>
        </div>

        <?php if ($report['admin_remark']): ?>
        <div class="info-group">
            <label>管理员备注</label>
            <div class="value"><?php echo htmlspecialchars($report['admin_remark']); ?></div>
        </div>
        <?php endif; ?>

        <form method="POST" style="margin-top: 30px;">
            <div class="info-group">
                <label>处理状态</label>
                <select name="status">
                    <option value="0" <?php echo $report['status'] == 0 ? 'selected' : ''; ?>>待处理</option>
                    <option value="1" <?php echo $report['status'] == 1 ? 'selected' : ''; ?>>已处理</option>
                    <option value="2" <?php echo $report['status'] == 2 ? 'selected' : ''; ?>>已驳回</option>
                </select>
            </div>

            <div class="info-group">
                <label>管理员备注</label>
                <textarea name="admin_remark" placeholder="请输入处理备注"><?php echo htmlspecialchars($report['admin_remark'] ?? ''); ?></textarea>
            </div>

            <button type="submit">保存</button>
        </form>
    </div>
</body>
</html>
