<?php
/**
 * 协议管理列表
 */
require_once '../common/session.php';
require_once '../../common/db.php';
require_once '../../common/functions.php';

checkAdminLogin();

global $pdo;

$stmt = $pdo->query("SELECT `agreement_id`, `title`, `type`, `version`, `content`, `status`, `updated_at`
                     FROM `agreements`
                     ORDER BY `sort` ASC, `created_at` ASC");
$links = $stmt->fetchAll(PDO::FETCH_ASSOC);

$typeNames = [
    'user_agreement' => '用户协议',
    'privacy_policy' => '隐私政策',
    'auto_renewal' => '自动续费协议',
    'help_center' => '帮助中心',
    'feedback' => '客服反馈',
    'about' => '关于我们'
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>链接管理</title>
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
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        .status-1 {
            background: #d4edda;
            color: #155724;
        }
        .status-0 {
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
            margin-right: 4px;
        }
        .btn-edit {
            background: #667eea;
            color: white;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include '../common/sidebar.php'; ?>
        <div class="main-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <h1 style="margin: 0; font-size: 20px;">链接管理</h1>
                <a href="add.php" style="display: inline-block; padding: 6px 12px; background: #667eea; color: white; text-decoration: none; border-radius: 4px; font-size: 13px;">添加链接</a>
    </div>
        <table>
            <thead>
                <tr>
                    <th width="200">ID</th>
                    <th>链接名称</th>
                    <th>类型</th>
                    <th>URL地址</th>
                    <th>状态</th>
                    <th>更新时间</th>
                    <th width="120">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($links as $link): ?>
                <tr>
                    <td title="<?php echo htmlspecialchars($link['agreement_id']); ?>" style="font-size: 11px; word-break: break-all; max-width: 200px; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($link['agreement_id']); ?></td>
                    <td><?php echo htmlspecialchars($link['title']); ?></td>
                    <td><?php echo $typeNames[$link['type']] ?? $link['type']; ?></td>
                    <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($link['content'] ?? ''); ?>">
                        <?php if (!empty($link['content'])): ?>
                            <a href="<?php echo htmlspecialchars($link['content']); ?>" target="_blank" style="color: #667eea; text-decoration: none;"><?php echo htmlspecialchars(mb_substr($link['content'], 0, 50)) . (mb_strlen($link['content']) > 50 ? '...' : ''); ?></a>
                        <?php else: ?>
                            <span style="color: #999;">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="status status-<?php echo $link['status']; ?>">
                            <?php echo $link['status'] == 1 ? '启用' : '禁用'; ?>
                        </span>
                    </td>
                    <td><?php echo date('Y-m-d H:i', strtotime($link['updated_at'])); ?></td>
                    <td>
                        <a href="edit.php?id=<?php echo $link['agreement_id']; ?>" class="btn btn-edit">编辑</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</body>
</html>
