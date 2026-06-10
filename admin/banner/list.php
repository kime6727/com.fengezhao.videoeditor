<?php
/**
 * Banner管理列表
 */
require_once '../common/session.php';
require_once '../../common/db.php';
require_once '../../common/functions.php';

checkAdminLogin();

global $pdo;

$stmt = $pdo->query("SELECT `banner_id`, `title`, `image_url`, `link_url`, `link_type`,
                            `sort`, `status`, `start_time`, `end_time`, `created_at`
                     FROM `banners`
                     ORDER BY `sort` ASC, `created_at` DESC");
$banners = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banner管理</title>
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
        .header .actions {
            float: right;
        }
        .header a {
            display: inline-block;
            padding: 8px 16px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-left: 10px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
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
        .thumbnail {
            width: 100px;
            height: 40px;
            object-fit: cover;
            border-radius: 3px;
        }
        .status {
            padding: 3px 6px;
            border-radius: 3px;
            font-size: 11px;
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
        .btn-delete {
            background: #e74c3c;
            color: white;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include '../common/sidebar.php'; ?>
        <div class="main-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <h1 style="margin: 0; font-size: 20px;">Banner管理</h1>
                <a href="add.php" style="display: inline-block; padding: 6px 12px; background: #667eea; color: white; text-decoration: none; border-radius: 4px; font-size: 13px;">添加Banner</a>
    </div>
        <table>
            <thead>
                <tr>
                    <th width="180">ID</th>
                    <th width="130">图片</th>
                    <th>标题</th>
                    <th>链接</th>
                    <th>排序</th>
                    <th>状态</th>
                    <th>生效时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($banners as $banner): ?>
                <tr>
                    <td title="<?php echo htmlspecialchars($banner['banner_id']); ?>" style="font-size: 11px; word-break: break-all;"><?php echo htmlspecialchars($banner['banner_id']); ?></td>
                    <td>
                        <img src="<?php echo htmlspecialchars($banner['image_url']); ?>" class="thumbnail" alt="">
                    </td>
                    <td><?php echo htmlspecialchars($banner['title'] ?? '-'); ?></td>
                    <td>
                        <?php if ($banner['link_url']): ?>
                            <a href="<?php echo htmlspecialchars($banner['link_url']); ?>" target="_blank">
                                <?php echo htmlspecialchars($banner['link_url']); ?>
                            </a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td><?php echo $banner['sort']; ?></td>
                    <td>
                        <span class="status status-<?php echo $banner['status']; ?>">
                            <?php echo $banner['status'] == 1 ? '启用' : '禁用'; ?>
                        </span>
                    </td>
                    <td>
                        <?php
                        $start = $banner['start_time'] ? date('Y-m-d', strtotime($banner['start_time'])) : '无限制';
                        $end = $banner['end_time'] ? date('Y-m-d', strtotime($banner['end_time'])) : '无限制';
                        echo $start . ' ~ ' . $end;
                        ?>
                    </td>
                    <td>
                        <a href="edit.php?id=<?php echo $banner['banner_id']; ?>" class="btn btn-edit">编辑</a>
                        <a href="delete.php?id=<?php echo $banner['banner_id']; ?>" class="btn btn-delete" onclick="return confirm('确定删除吗？')">删除</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</body>
</html>
