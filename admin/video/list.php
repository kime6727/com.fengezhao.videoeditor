<?php
/**
 * 单视频素材列表
 */
require_once __DIR__ . '/../common/session.php';
require_once __DIR__ . '/../../common/db.php';
require_once __DIR__ . '/../../common/functions.php';

checkAdminLogin();

global $pdo;

$page = intval($_GET['page'] ?? 1);
$pageSize = 20;
$offset = ($page - 1) * $pageSize;

// 获取筛选和排序参数
$categoryId = $_GET['category_id'] ?? '';
$status = isset($_GET['status']) ? intval($_GET['status']) : -1; // -1表示全部
$keyword = $_GET['keyword'] ?? '';
$orderBy = $_GET['order_by'] ?? 'created_at';
$orderDir = strtoupper($_GET['order_dir'] ?? 'DESC');

// 验证排序字段和方向
$allowedOrderBy = ['created_at', 'download_count', 'like_count', 'status'];
if (!in_array($orderBy, $allowedOrderBy)) {
    $orderBy = 'created_at';
}
if (!in_array($orderDir, ['ASC', 'DESC'])) {
    $orderDir = 'DESC';
}

// 构建查询条件
$whereConditions = ["vm.`status` >= 0"];
$params = [];

// 分类筛选
if (!empty($categoryId)) {
    $whereConditions[] = "vm.`material_id` IN (
        SELECT `material_id` FROM `category_relations`
        WHERE `category_id` = ? AND `material_type` = 1
    )";
    $params[] = $categoryId;
}

// 状态筛选
if ($status != -1) {
    $whereConditions[] = "vm.`status` = ?";
    $params[] = $status;
}

// 关键词搜索
if (!empty($keyword)) {
    $whereConditions[] = "(vm.`name` LIKE ? OR vm.`material_id` LIKE ?)";
    $searchTerm = "%{$keyword}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereClause = implode(' AND ', $whereConditions);

// 获取总数
$countSql = "SELECT COUNT(*) FROM `video_materials` vm WHERE {$whereClause}";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total = $stmt->fetchColumn();
$totalPages = ceil($total / $pageSize);

// 获取数据（关联用户表获取发布者信息）
$sql = "SELECT vm.*,
        GROUP_CONCAT(c.`name` SEPARATOR ', ') as category_names,
        u.`username` as author_name, u.`user_id` as author_id
        FROM `video_materials` vm
        LEFT JOIN `category_relations` cr ON vm.`material_id` = cr.`material_id` AND cr.`material_type` = 1
        LEFT JOIN `categories` c ON cr.`category_id` = c.`category_id`
        LEFT JOIN `users` u ON vm.`author_id` = u.`user_id`
        WHERE {$whereClause}
        GROUP BY vm.`material_id`
        ORDER BY vm.`{$orderBy}` {$orderDir}
        LIMIT {$offset}, {$pageSize}";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$videos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取分类信息
$categories = $pdo->query("SELECT `category_id`, `name` FROM `categories` WHERE `type` = 1 AND `status` = 1 ORDER BY `sort`")->fetchAll(PDO::FETCH_ASSOC);

// 获取所有用户列表（用于批量修改发布者下拉框）
$allUsers = $pdo->query("SELECT `user_id`, `username` FROM `users` WHERE `user_type` = 1 ORDER BY `username` ASC")->fetchAll(PDO::FETCH_ASSOC);

// 获取成功/错误消息
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>单视频素材管理</title>
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
            margin-left: 0;
            width: 100%;
        }
        .container {
            max-width: none;
            margin: 0;
            padding: 0 20px;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .page-title {
            font-size: 24px;
            font-weight: 600;
            color: #333;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .batch-controls {
            background: white;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 6px;
            display: none;
            align-items: center;
            gap: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .batch-controls.active {
            display: flex;
        }
        .batch-select-all {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .batch-action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            margin-right: 8px;
        }
        .btn-batch-status {
            background: #667eea;
            color: white;
        }
        .btn-batch-delete {
            background: #e74c3c;
            color: white;
        }
        .filters {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            gap: 20px;
            align-items: end;
            flex-wrap: wrap;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .filter-group label {
            font-size: 13px;
            font-weight: 600;
            color: #555;
        }
        .filter-group input, .filter-group select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 13px;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .table-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 8px 10px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
            font-size: 12px;
            color: #555;
        }
        td {
            font-size: 12px;
        }
        .editable-name {
            cursor: pointer;
            color: #667eea;
            font-weight: 500;
        }
        .editable-name:hover {
            text-decoration: underline;
            color: #5568d3;
        }
        .thumbnail {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
        }
        .sort-link {
            color: #667eea;
            text-decoration: none;
        }
        .sort-link:hover {
            text-decoration: underline;
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
        .pagination {
            background: white;
            padding: 15px;
            margin-top: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        .pagination a {
            display: inline-block;
            padding: 8px 12px;
            margin: 0 4px;
            text-decoration: none;
            color: #667eea;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .pagination a.active {
            background: #667eea;
            color: white;
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
        function applyFilters() {
            const categoryId = document.querySelector('select[name="category_id"]').value;
            const status = document.querySelector('select[name="status"]').value;
            const keyword = document.querySelector('input[name="keyword"]').value;
            const orderBy = document.querySelector('select[name="order_by"]').value;
            const orderDir = document.querySelector('select[name="order_dir"]').value;

            const params = new URLSearchParams();
            if (categoryId) params.set('category_id', categoryId);
            if (status != -1) params.set('status', status);
            if (keyword) params.set('keyword', keyword);
            params.set('order_by', orderBy);
            params.set('order_dir', orderDir);

            window.location.href = '?' + params.toString();
        }

        function handleSearchKeyPress(event) {
            if (event.key === 'Enter') {
                applyFilters();
            }
        }

        // 批量操作相关函数
        function updateBatchControls() {
            const checkboxes = document.querySelectorAll('input[name="ids[]"]:checked');
            const batchControls = document.getElementById('batchControls');

            if (checkboxes.length > 0) {
                batchControls.classList.add('active');
                document.getElementById('selectedCount').textContent = checkboxes.length;
            } else {
                batchControls.classList.remove('active');
                const selectAllCheckbox = document.getElementById('selectAll');
                if (selectAllCheckbox) selectAllCheckbox.checked = false;
            }
        }

        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('input[name="ids[]"]');

            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            updateBatchControls();
        }

        function setBatchStatus(status) {
            const selectedIds = Array.from(document.querySelectorAll('input[name="ids[]"]:checked'))
                .map(cb => cb.value);

            if (selectedIds.length === 0) {
                alert('请先选择要操作的素材');
                return;
            }

            const statusText = status === 1 ? '上架' : '下架';
            if (confirm(`确定要将 ${selectedIds.length} 个素材设置为${statusText}状态吗？`)) {
                // 创建表单提交
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'batch-action.php';

                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = status === 1 ? '1' : '2'; // 1-上架，2-下架
                form.appendChild(actionInput);

                const typeInput = document.createElement('input');
                typeInput.type = 'hidden';
                typeInput.name = 'material_type';
                typeInput.value = '1'; // 视频类型
                form.appendChild(typeInput);

                selectedIds.forEach(id => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'selected_ids[]';
                    input.value = id;
                    form.appendChild(input);
                });

                document.body.appendChild(form);
                form.submit();
            }
        }

        function deleteBatch() {
            const selectedIds = Array.from(document.querySelectorAll('input[name="ids[]"]:checked'))
                .map(cb => cb.value);

            if (selectedIds.length === 0) {
                alert('请先选择要删除的素材');
                return;
            }

            if (confirm(`确定要删除 ${selectedIds.length} 个素材吗？此操作不可恢复！`)) {
                // 创建表单提交
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'batch-action.php';

                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = '3'; // 删除操作
                form.appendChild(actionInput);

                const typeInput = document.createElement('input');
                typeInput.type = 'hidden';
                typeInput.name = 'material_type';
                typeInput.value = '1'; // 视频类型
                form.appendChild(typeInput);

                selectedIds.forEach(id => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'selected_ids[]';
                    input.value = id;
                    form.appendChild(input);
                });

                document.body.appendChild(form);
                form.submit();
            }
        }

        function toggleStatus(materialId, currentStatus, element) {
            const newStatus = currentStatus == 1 ? 0 : 1;
            const row = element.closest('tr');
            row.classList.add('loading');

            const formData = new FormData();
            formData.append('material_id', materialId);
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
                    statusText.textContent = newStatus == 1 ? '上架' : '下架';
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

        function openEditModal(materialId, currentName) {
            document.getElementById('editMaterialId').value = materialId;
            document.getElementById('editName').value = currentName;
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function saveEditName() {
            const materialId = document.getElementById('editMaterialId').value;
            const newName = document.getElementById('editName').value.trim();

            if (!newName) {
                alert('名称不能为空');
                return;
            }

            const formData = new FormData();
            formData.append('material_id', materialId);
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
                    const nameCell = document.querySelector(`td[data-material-id="${materialId}"]`);
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
            <div class="container">
                <div class="page-header">
                    <h1 class="page-title">单视频素材管理</h1>
                    <div>
                        <button type="button" id="batchBtn" style="display: none; padding: 6px 12px; background: #e74c3c; color: white; border: none; border-radius: 4px; margin-right: 10px; font-size: 13px; cursor: pointer;">批量操作</button>
                        <a href="add.php" class="btn btn-primary">添加视频</a>
                        <a href="import.php" class="btn btn-secondary">批量导入</a>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <!-- 批量操作控制栏 -->
                <div id="batchControls" class="batch-controls">
                    <div class="batch-select-all">
                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                        <label for="selectAll">全选</label>
                        <span>已选择 <strong id="selectedCount">0</strong> 个素材</span>
                    </div>
                    <div>
                        <button type="button" class="batch-action-btn btn-batch-status" onclick="setBatchStatus(1)">批量上架</button>
                        <button type="button" class="batch-action-btn btn-batch-status" onclick="setBatchStatus(0)">批量下架</button>
                        <button type="button" class="batch-action-btn btn-batch-delete" onclick="deleteBatch()">批量删除</button>
                        <button type="button" class="batch-action-btn" style="background: #17a2b8; color: white;" onclick="openBatchAuthorModal()">批量修改发布者</button>
                    </div>
                </div>

                <!-- 筛选和排序 -->
                <div class="filters">
                    <div class="filter-group">
                        <label>搜索</label>
                        <input type="text" name="keyword" placeholder="输入名称或ID搜索..." value="<?php echo htmlspecialchars($keyword); ?>"
                               onkeypress="handleSearchKeyPress(event)">
                    </div>
                    <div class="filter-group">
                        <label>分类</label>
                        <select name="category_id" onchange="applyFilters()">
                            <option value="">全部分类</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['category_id']; ?>" <?php echo $categoryId == $cat['category_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>状态</label>
                        <select name="status" onchange="applyFilters()">
                            <option value="-1" <?php echo $status == -1 ? 'selected' : ''; ?>>全部状态</option>
                            <option value="1" <?php echo $status == 1 ? 'selected' : ''; ?>>上架</option>
                            <option value="0" <?php echo $status == 0 ? 'selected' : ''; ?>>下架</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>排序</label>
                        <div style="display: flex; gap: 8px;">
                            <select name="order_by" onchange="applyFilters()">
                                <option value="created_at" <?php echo $orderBy == 'created_at' ? 'selected' : ''; ?>>创建时间</option>
                                <option value="download_count" <?php echo $orderBy == 'download_count' ? 'selected' : ''; ?>>下载次数</option>
                                <option value="like_count" <?php echo $orderBy == 'like_count' ? 'selected' : ''; ?>>点赞数</option>
                                <option value="status" <?php echo $orderBy == 'status' ? 'selected' : ''; ?>>状态</option>
                            </select>
                            <select name="order_dir" onchange="applyFilters()">
                                <option value="DESC" <?php echo $orderDir == 'DESC' ? 'selected' : ''; ?>>降序</option>
                                <option value="ASC" <?php echo $orderDir == 'ASC' ? 'selected' : ''; ?>>升序</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- 素材列表 -->
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th width="50" style="text-align: center;">
                                    <input type="checkbox" id="selectAllTable" onchange="toggleSelectAll()">
                                </th>
                                <th width="120">ID</th>
                                <th width="80">缩略图</th>
                                <th>名称</th>
                                <th>分类</th>
                                <th>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['order_by' => 'download_count', 'order_dir' => $orderBy == 'download_count' && $orderDir == 'DESC' ? 'ASC' : 'DESC'])); ?>" class="sort-link">
                                        下载次数 <?php if ($orderBy == 'download_count'): ?><?php echo $orderDir == 'DESC' ? '↓' : '↑'; ?><?php endif; ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['order_by' => 'like_count', 'order_dir' => $orderBy == 'like_count' && $orderDir == 'DESC' ? 'ASC' : 'DESC'])); ?>" class="sort-link">
                                        点赞数 <?php if ($orderBy == 'like_count'): ?><?php echo $orderDir == 'DESC' ? '↓' : '↑'; ?><?php endif; ?>
                                    </a>
                                </th>
                                <th>发布者</th>
                                <th>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['order_by' => 'status', 'order_dir' => $orderBy == 'status' && $orderDir == 'DESC' ? 'ASC' : 'DESC'])); ?>" class="sort-link">
                                        状态 <?php if ($orderBy == 'status'): ?><?php echo $orderDir == 'DESC' ? '↓' : '↑'; ?><?php endif; ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['order_by' => 'created_at', 'order_dir' => $orderBy == 'created_at' && $orderDir == 'DESC' ? 'ASC' : 'DESC'])); ?>" class="sort-link">
                                        创建时间 <?php if ($orderBy == 'created_at'): ?><?php echo $orderDir == 'DESC' ? '↓' : '↑'; ?><?php endif; ?>
                                    </a>
                                </th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($videos as $video): ?>
                            <tr>
                                <td style="text-align: center;">
                                    <input type="checkbox" name="ids[]" value="<?php echo $video['material_id']; ?>" onchange="updateBatchControls()">
                                </td>
                                <td title="<?php echo htmlspecialchars($video['material_id']); ?>" style="font-size: 11px; word-break: break-all;">
                                    <?php echo htmlspecialchars(substr($video['material_id'], 0, 12)) . '...'; ?>
                                </td>
                                <td>
                                    <?php if ($video['thumbnail_url']): ?>
                                        <img src="<?php echo htmlspecialchars($video['thumbnail_url']); ?>" class="thumbnail" alt="">
                                    <?php else: ?>
                                        <span style="color: #999; font-size: 11px;">无缩略图</span>
                                    <?php endif; ?>
                                </td>
                                <td class="editable-name" onclick="openEditModal('<?php echo $video['material_id']; ?>', '<?php echo htmlspecialchars($video['name'], ENT_QUOTES); ?>')" data-material-id="<?php echo $video['material_id']; ?>"><?php echo htmlspecialchars($video['name']); ?></td>
                                <td>
                                    <?php if ($video['category_names']): ?>
                                        <span style="font-size: 11px; color: #666;"><?php echo htmlspecialchars($video['category_names']); ?></span>
                                    <?php else: ?>
                                        <span style="color: #999; font-size: 11px;">未分类</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $video['download_count']; ?></td>
                                <td><?php echo $video['like_count']; ?></td>
                                <td>
                                    <?php if ($video['author_name']): ?>
                                        <span style="font-size: 12px; color: #333;"><?php echo htmlspecialchars($video['author_name']); ?></span>
                                    <?php else: ?>
                                        <span style="font-size: 11px; color: #999;">未设置</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="status-toggle">
                                        <label class="switch">
                                            <input type="checkbox" <?php echo $video['status'] == 1 ? 'checked' : ''; ?>
                                                   onchange="toggleStatus('<?php echo $video['material_id']; ?>', <?php echo $video['status']; ?>, this)">
                                            <span class="slider"></span>
                                        </label>
                                        <span class="status-text"><?php echo $video['status'] == 1 ? '上架' : '下架'; ?></span>
                                    </div>
                                </td>
                                <td><?php echo date('Y-m-d H:i', strtotime($video['created_at'])); ?></td>
                                <td>
                                    <a href="edit.php?id=<?php echo $video['material_id']; ?>" class="btn btn-secondary" style="padding: 4px 8px; font-size: 11px;">编辑</a>
                                    <a href="delete.php?id=<?php echo $video['material_id']; ?>" class="btn btn-danger" style="padding: 4px 8px; font-size: 11px;" onclick="return confirm('确定删除吗？')">删除</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

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
    </div>

    <div id="editModal" class="edit-modal">
        <div class="edit-modal-content">
            <div class="edit-modal-header">
                <h3>编辑名称</h3>
                <span class="edit-modal-close" onclick="closeEditModal()">&times;</span>
            </div>
            <div class="edit-modal-body">
                <input type="hidden" id="editMaterialId">
                <label>名称：</label>
                <input type="text" id="editName" placeholder="请输入素材名称">
            </div>
            <div class="edit-modal-footer">
                <button type="button" onclick="closeEditModal()" class="btn" style="background: #95a5a6; color: white;">取消</button>
                <button type="button" id="saveEditBtn" onclick="saveEditName()" class="btn btn-primary" style="background: #667eea; color: white;">保存</button>
            </div>
        </div>
    </div>

    <!-- 批量修改发布者弹窗 -->
    <div id="batchAuthorModal" class="edit-modal">
        <div class="edit-modal-content">
            <div class="edit-modal-header">
                <h3>批量修改发布者</h3>
                <span class="edit-modal-close" onclick="closeBatchAuthorModal()">&times;</span>
            </div>
            <div class="edit-modal-body">
                <label>选择发布者：</label>
                <select id="batchAuthorSelect" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    <option value="">请选择发布者</option>
                    <?php foreach ($allUsers as $user): ?>
                        <option value="<?php echo $user['user_id']; ?>"><?php echo htmlspecialchars($user['username'] ?? $user['user_id']); ?> (<?php echo $user['user_id']; ?>)</option>
                    <?php endforeach; ?>
                </select>
                <p class="hint" style="margin-top: 8px; color: #666;">已选择 <strong id="batchAuthorCount">0</strong> 个素材</p>
            </div>
            <div class="edit-modal-footer">
                <button type="button" onclick="closeBatchAuthorModal()" class="btn" style="background: #95a5a6; color: white;">取消</button>
                <button type="button" id="saveBatchAuthorBtn" onclick="saveBatchAuthor()" class="btn btn-primary" style="background: #667eea; color: white;">确认修改</button>
            </div>
        </div>
    </div>

    <script>
        // 批量修改发布者相关函数
        function openBatchAuthorModal() {
            const selectedIds = Array.from(document.querySelectorAll('input[name="ids[]"]:checked'))
                .map(cb => cb.value);

            if (selectedIds.length === 0) {
                alert('请先选择要修改的素材');
                return;
            }

            document.getElementById('batchAuthorCount').textContent = selectedIds.length;
            document.getElementById('batchAuthorModal').style.display = 'block';
        }

        function closeBatchAuthorModal() {
            document.getElementById('batchAuthorModal').style.display = 'none';
        }

        function saveBatchAuthor() {
            const selectedIds = Array.from(document.querySelectorAll('input[name="ids[]"]:checked'))
                .map(cb => cb.value);
            const authorId = document.getElementById('batchAuthorSelect').value;

            if (!authorId) {
                alert('请选择发布者');
                return;
            }

            if (confirm(`确定要将 ${selectedIds.length} 个素材的发布者修改为该用户吗？`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'batch-action.php';

                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = '4';
                form.appendChild(actionInput);

                const typeInput = document.createElement('input');
                typeInput.type = 'hidden';
                typeInput.name = 'material_type';
                typeInput.value = '1';
                form.appendChild(typeInput);

                const authorInput = document.createElement('input');
                authorInput.type = 'hidden';
                authorInput.name = 'author_id';
                authorInput.value = authorId;
                form.appendChild(authorInput);

                selectedIds.forEach(id => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'selected_ids[]';
                    input.value = id;
                    form.appendChild(input);
                });

                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
