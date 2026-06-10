<?php
/**
 * 管理员列表
 */
require_once __DIR__ . '/../common/session.php';
require_once __DIR__ . '/../../common/db.php';

checkAdminLogin();

global $pdo;

$error = '';
$success = '';

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);

    if (isset($_SESSION['admin_id']) && $id == $_SESSION['admin_id']) {
        $error = '不能删除当前登录的管理员账号';
    } else {
        $stmt = $pdo->prepare("DELETE FROM `admins` WHERE `id` = ? AND `username` != 'admin'");
        $stmt->execute([$id]);
        if ($stmt->rowCount() > 0) {
            $success = '管理员删除成功';
        } else {
            $error = '删除失败，该管理员可能不存在或不能删除';
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'toggle_status' && isset($_GET['id'])) {
    $id = intval($_GET['id']);

    if (isset($_SESSION['admin_id']) && $id == $_SESSION['admin_id']) {
        $error = '不能禁用当前登录的管理员账号';
    } else {
        $stmt = $pdo->prepare("UPDATE `admins` SET `status` = 1 - `status` WHERE `id` = ? AND `username` != 'admin'");
        $stmt->execute([$id]);
        if ($stmt->rowCount() > 0) {
            $success = '状态更新成功';
        } else {
            $error = '操作失败，该管理员可能不存在或不能修改';
        }
    }
}

$stmt = $pdo->query("SELECT `id`, `username`, `email`, `real_name`, `status`, `last_login_time`, `last_login_ip`, `created_at` FROM `admins` ORDER BY `id` ASC");
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员管理</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .page-header h1 {
            font-size: 20px;
            color: #333;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
        }
        .btn-add {
            background: #667eea;
            color: white;
        }
        .btn-add:hover {
            background: #5568d3;
        }
        .btn-disable {
            background: #e74c3c;
            color: white;
        }
        .btn-enable {
            background: #27ae60;
            color: white;
        }
        .btn-delete {
            background: #e74c3c;
            color: white;
        }
        table {
            width: 100%;
            background: white;
            border-collapse: collapse;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .status {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            display: inline-block;
        }
        .status-enabled {
            background: #d4edda;
            color: #155724;
        }
        .status-disabled {
            background: #f8d7da;
            color: #721c24;
        }
        .current-badge {
            background: #667eea;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 11px;
            margin-left: 5px;
        }
        .message {
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .message.error {
            background: #fee;
            color: #c33;
        }
        .message.success {
            background: #efe;
            color: #3c3;
        }
        .empty-tip {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            color: #0066cc;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include '../common/sidebar.php'; ?>
        <div class="main-content">
            <div class="page-header">
                <h1>管理员管理</h1>
                <a href="add.php" class="btn btn-add">+ 添加管理员</a>
            </div>

            <?php if ($error): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="message success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <div class="empty-tip">
                ℹ️ 提示：管理员账号拥有所有管理权限，无法设置不同的权限等级。
            </div>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>用户名</th>
                        <th>真实姓名</th>
                        <th>邮箱</th>
                        <th>状态</th>
                        <th>最后登录</th>
                        <th>创建时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($admins as $admin): ?>
                    <tr>
                        <td><?php echo $admin['id']; ?></td>
                        <td>
                            <?php echo htmlspecialchars($admin['username']); ?>
                            <?php if (isset($_SESSION['admin_id']) && $admin['id'] == $_SESSION['admin_id']): ?>
                                <span class="current-badge">当前登录</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($admin['real_name'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($admin['email'] ?? '-'); ?></td>
                        <td>
                            <span class="status <?php echo $admin['status'] ? 'status-enabled' : 'status-disabled'; ?>">
                                <?php echo $admin['status'] ? '启用' : '禁用'; ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($admin['last_login_time']): ?>
                                <?php echo date('Y-m-d H:i', strtotime($admin['last_login_time'])); ?>
                                <br>
                                <small style="color: #999;"><?php echo htmlspecialchars($admin['last_login_ip'] ?? ''); ?></small>
                            <?php else: ?>
                                <span style="color: #999;">从未登录</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('Y-m-d H:i', strtotime($admin['created_at'])); ?></td>
                        <td>
                            <?php if ($admin['username'] !== 'admin'): ?>
                                <?php if (!isset($_SESSION['admin_id']) || $admin['id'] != $_SESSION['admin_id']): ?>
                                    <?php if ($admin['status']): ?>
                                        <a href="?action=toggle_status&id=<?php echo $admin['id']; ?>" class="btn btn-disable" onclick="return confirm('确定要禁用该管理员吗？')">禁用</a>
                                    <?php else: ?>
                                        <a href="?action=toggle_status&id=<?php echo $admin['id']; ?>" class="btn btn-enable" onclick="return confirm('确定要启用该管理员吗？')">启用</a>
                                    <?php endif; ?>
                                    <a href="?action=delete&id=<?php echo $admin['id']; ?>" class="btn btn-delete" onclick="return confirm('确定要删除该管理员吗？此操作不可恢复！')">删除</a>
                                <?php else: ?>
                                    <span style="color: #999;">当前登录</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color: #999;">系统管理员</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
