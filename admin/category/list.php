<?php
/**
 * 分类管理列表
 */
require_once '../common/session.php';
require_once '../../common/db.php';
require_once '../../common/functions.php';

checkAdminLogin();

global $pdo;

$type = intval($_GET['type'] ?? 0);

$sql = "SELECT `category_id`, `name`, `type`, `sort`, `is_top`, `status`, `created_at`
        FROM `categories`";
$params = [];

if ($type > 0) {
    $sql .= " WHERE `type` = ?";
    $params[] = $type;
}

$sql .= " ORDER BY `type` ASC, `is_top` DESC, `sort` ASC, `created_at` ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$typeNames = [1 => '单视频', 2 => '图片+文案', 3 => '视频+文案', 4 => '纯文案'];

// 获取成功/错误消息
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>分类管理</title>
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
        .nav {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
        }
        .nav a {
            display: inline-block;
            padding: 10px 20px;
            margin-right: 10px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .filter {
            background: white;
            padding: 10px 15px;
            margin-bottom: 12px;
            border-radius: 4px;
        }
        .filter select {
            padding: 6px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
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
            font-size: 12px;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            font-size: 12px;
        }
        .editable-name {
            cursor: pointer;
            color: #667eea;
            font-weight: 500;
        }
        .editable-name:hover {
            text-decoration: underline;
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
        .is-top {
            color: #f39c12;
            font-weight: bold;
        }
        .status-toggle {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .switch {
            position: relative;
            display: inline-block;
            width: 40px;
            height: 20px;
        }
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 20px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 2px;
            bottom: 2px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .slider {
            background-color: #667eea;
        }
        input:checked + .slider:before {
            transform: translateX(20px);
        }
        .status-text {
            font-size: 12px;
            color: #666;
        }
        .edit-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .edit-modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 20px;
            border-radius: 8px;
            width: 400px;
            max-width: 90%;
        }
        .edit-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .edit-modal-header h3 {
            margin: 0;
            font-size: 16px;
        }
        .edit-modal-close {
            color: #aaa;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
        }
        .edit-modal-close:hover {
            color: #000;
        }
        .edit-modal-body {
            margin-bottom: 15px;
        }
        .edit-modal-body label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            font-size: 13px;
        }
        .edit-modal-body input {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
        }
        .edit-modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }
    </style>
    <script>
        function toggleCategoryStatus(categoryId, currentStatus, element) {
            const newStatus = currentStatus == 1 ? 0 : 1;
            const row = element.closest('tr');
            row.classList.add('loading');

            const formData = new FormData();
            formData.append('category_id', categoryId);
            formData.append('status', newStatus);

            fetch('toggle-status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                row.classList.remove('loading');
                if (data.success) {
                    element.checked = newStatus == 1;
                    const statusText = element.parentElement.nextElementSibling;
                    statusText.textContent = newStatus == 1 ? '启用' : '禁用';
                } else {
                    alert('操作失败：' + data.message);
                    element.checked = currentStatus == 1;
                }
            })
            .catch(error => {
                row.classList.remove('loading');
                alert('操作失败：' + error);
                element.checked = currentStatus == 1;
            });
        }

        function openEditModal(categoryId, currentName) {
            document.getElementById('editCategoryId').value = categoryId;
            document.getElementById('editName').value = currentName;
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function saveEditName() {
            const categoryId = document.getElementById('editCategoryId').value;
            const newName = document.getElementById('editName').value.trim();

            if (!newName) {
                alert('名称不能为空');
                return;
            }

            const formData = new FormData();
            formData.append('category_id', categoryId);
            formData.append('name', newName);

            document.getElementById('saveEditBtn').disabled = true;
            document.getElementById('saveEditBtn').textContent = '保存中...';

            fetch('quick-edit.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('saveEditBtn').disabled = false;
                document.getElementById('saveEditBtn').textContent = '保存';
                if (data.success) {
                    const nameCell = document.querySelector(`td[data-category-id="${categoryId}"]`);
                    if (nameCell) {
                        nameCell.textContent = newName;
                        nameCell.title = newName;
                    }
                    closeEditModal();
                } else {
                    alert('保存失败：' + data.message);
                }
            })
            .catch(error => {
                document.getElementById('saveEditBtn').disabled = false;
                document.getElementById('saveEditBtn').textContent = '保存';
                alert('保存失败：' + error);
            });
        }

        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) {
                closeEditModal();
            }
        }
    </script>
</head>
<body>
    <div class="admin-layout">
        <?php include '../common/sidebar.php'; ?>
        <div class="main-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <h1 style="margin: 0; font-size: 20px;">分类管理</h1>
                <a href="add.php" style="display: inline-block; padding: 6px 12px; background: #667eea; color: white; text-decoration: none; border-radius: 4px; font-size: 13px;">添加分类</a>
        </div>

        <?php if ($success): ?>
            <div style="background: #d4edda; color: #155724; padding: 12px 16px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #c3e6cb;">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 12px 16px; border-radius: 4px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="filter">
            <form method="GET">
                <label>筛选类型：</label>
                <select name="type" onchange="this.form.submit()">
                    <option value="0" <?php echo $type == 0 ? 'selected' : ''; ?>>全部</option>
                    <option value="1" <?php echo $type == 1 ? 'selected' : ''; ?>>单视频</option>
                    <option value="2" <?php echo $type == 2 ? 'selected' : ''; ?>>图片+文案</option>
                    <option value="3" <?php echo $type == 3 ? 'selected' : ''; ?>>视频+文案</option>
                    <option value="4" <?php echo $type == 4 ? 'selected' : ''; ?>>纯文案</option>
                </select>
            </form>
        </div>

        <table>
            <thead>
                <tr>
                    <th width="150">ID</th>
                    <th>名称</th>
                    <th width="80">类型</th>
                    <th width="60">排序</th>
                    <th width="50">置顶</th>
                    <th width="70">状态</th>
                    <th width="140">创建时间</th>
                    <th width="100">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $category): ?>
                <tr>
                    <td title="<?php echo htmlspecialchars($category['category_id']); ?>" style="font-size: 11px; word-break: break-all; max-width: 150px; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($category['category_id']); ?></td>
                    <td class="editable-name" onclick="openEditModal('<?php echo $category['category_id']; ?>', '<?php echo htmlspecialchars($category['name'], ENT_QUOTES); ?>')" data-category-id="<?php echo $category['category_id']; ?>"><?php echo htmlspecialchars($category['name']); ?></td>
                    <td style="font-size: 12px;"><?php echo $typeNames[$category['type']] ?? '未知'; ?></td>
                    <td style="text-align: center;"><?php echo $category['sort']; ?></td>
                    <td style="text-align: center;">
                        <?php if ($category['is_top']): ?>
                            <span class="is-top" style="font-size: 12px;">✓</span>
                        <?php else: ?>
                            <span style="color: #ccc;">-</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: center;">
                        <div class="status-toggle">
                            <label class="switch">
                                <input type="checkbox" <?php echo $category['status'] == 1 ? 'checked' : ''; ?>
                                       onchange="toggleCategoryStatus('<?php echo $category['category_id']; ?>', <?php echo $category['status']; ?>, this)">
                                <span class="slider"></span>
                            </label>
                            <span class="status-text"><?php echo $category['status'] == 1 ? '启用' : '禁用'; ?></span>
                        </div>
                    </td>
                    <td style="font-size: 12px;"><?php echo date('Y-m-d H:i', strtotime($category['created_at'])); ?></td>
                    <td>
                        <a href="edit.php?id=<?php echo $category['category_id']; ?>" class="btn btn-edit">编辑</a>
                        <a href="delete.php?id=<?php echo $category['category_id']; ?>" class="btn btn-delete" onclick="return confirm('确定删除吗？')">删除</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <div id="editModal" class="edit-modal">
        <div class="edit-modal-content">
            <div class="edit-modal-header">
                <h3>编辑分类名称</h3>
                <span class="edit-modal-close" onclick="closeEditModal()">&times;</span>
            </div>
            <div class="edit-modal-body">
                <input type="hidden" id="editCategoryId">
                <label>名称：</label>
                <input type="text" id="editName" placeholder="请输入分类名称">
            </div>
            <div class="edit-modal-footer">
                <button type="button" onclick="closeEditModal()" class="btn" style="background: #95a5a6; color: white;">取消</button>
                <button type="button" id="saveEditBtn" onclick="saveEditName()" class="btn btn-primary" style="background: #667eea; color: white;">保存</button>
            </div>
        </div>
    </div>
</body>
</html>
