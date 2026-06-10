<?php
/**
 * 纯文案素材列表
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
$categoryId = $_GET['category_id'] ?? '';
$status = isset($_GET['status']) ? intval($_GET['status']) : -1; // -1表示全部
$keyword = $_GET['keyword'] ?? ''; // 搜索关键词
$orderBy = $_GET['order_by'] ?? 'created_at';
$orderDir = strtoupper($_GET['order_dir'] ?? 'DESC');

// 验证排序字段和方向
$allowedOrderBy = ['created_at', 'copy_count', 'like_count', 'status'];
if (!in_array($orderBy, $allowedOrderBy)) {
    $orderBy = 'created_at';
}
if (!in_array($orderDir, ['ASC', 'DESC'])) {
    $orderDir = 'DESC';
}

// 构建查询条件
$whereConditions = [];
$params = [];

// 分类筛选
if (!empty($categoryId)) {
    $whereConditions[] = "tm.`material_id` IN (
        SELECT `material_id` FROM `category_relations`
        WHERE `category_id` = ? AND `material_type` = 4
    )";
    $params[] = $categoryId;
}

// 状态筛选
if ($status >= 0) {
    $whereConditions[] = "tm.`status` = ?";
    $params[] = $status;
}

// 关键词搜索
if (!empty($keyword)) {
    $whereConditions[] = "(tm.`content` LIKE ? OR tm.`material_id` LIKE ?)";
    $searchParam = '%' . $keyword . '%';
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// 获取素材列表
$sql = "SELECT tm.`material_id`, tm.`content`, tm.`copy_count`, tm.`like_count`, tm.`status`, tm.`created_at`
        FROM `text_materials` tm
        $whereClause
        ORDER BY tm.`$orderBy` $orderDir
        LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
$params[] = $pageSize;
$params[] = $offset;
$stmt->execute($params);
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取每个文案的分类信息
foreach ($materials as &$material) {
    $stmt = $pdo->prepare("SELECT c.`category_id`, c.`name`
                          FROM `categories` c
                          INNER JOIN `category_relations` cr ON c.`category_id` = cr.`category_id`
                          WHERE cr.`material_id` = ? AND cr.`material_type` = 4
                          ORDER BY c.`sort` ASC, c.`name` ASC");
    $stmt->execute([$material['material_id']]);
    $material['categories'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 获取总数
$countSql = "SELECT COUNT(*) FROM `text_materials` tm $whereClause";
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

// 获取所有分类（用于筛选下拉框和批量修改）
$stmt = $pdo->prepare("SELECT `category_id`, `name` FROM `categories`
                      WHERE `type` = 4 AND `status` = 1
                      ORDER BY `sort` ASC, `name` ASC");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>纯文案素材管理</title>
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
        .editable-content {
            cursor: pointer;
            color: #667eea;
        }
        .editable-content:hover {
            text-decoration: underline;
        }
        .content-preview {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
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
        .status-toggle {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
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
            border-radius: 24px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .slider {
            background-color: #667eea;
        }
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        .status-text {
            font-size: 12px;
            color: #666;
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
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .filters label {
            font-weight: 500;
            margin-right: 5px;
        }
        .categories {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
        .category-tag {
            display: inline-block;
            padding: 2px 8px;
            background: #e3f2fd;
            color: #1976d2;
            border-radius: 12px;
            font-size: 12px;
        }
        /* 模态框样式 */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 20px;
            border: 1px solid #888;
            border-radius: 8px;
            width: 500px;
            max-width: 90%;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .modal-header h2 {
            margin: 0;
        }
        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover {
            color: #000;
        }
        .modal-body {
            margin-bottom: 20px;
        }
        .modal-body label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        .modal-body select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            min-height: 150px;
        }
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .search-input {
            flex: 1;
            min-width: 200px;
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
            width: 500px;
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
        .edit-modal-body textarea {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            min-height: 120px;
            resize: vertical;
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

        function openEditModal(materialId, currentContent) {
            document.getElementById('editMaterialId').value = materialId;
            document.getElementById('editContent').value = currentContent;
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function saveEditContent() {
            const materialId = document.getElementById('editMaterialId').value;
            const newContent = document.getElementById('editContent').value.trim();

            if (!newContent) {
                alert('内容不能为空');
                return;
            }

            const formData = new FormData();
            formData.append('material_id', materialId);
            formData.append('content', newContent);

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
                    const contentCell = document.querySelector(`td[data-material-id="${materialId}"]`);
                    if (contentCell) {
                        const displayText = newContent.length > 50 ? newContent.substring(0, 50) + '...' : newContent;
                        contentCell.textContent = displayText;
                        contentCell.title = newContent;
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
        function toggleAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('input[name="ids[]"]');
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
        }

        function getSelectedIds() {
            const checkboxes = document.querySelectorAll('input[name="ids[]"]:checked');
            return Array.from(checkboxes).map(cb => cb.value);
        }

        function batchDelete() {
            const ids = getSelectedIds();
            if (ids.length === 0) {
                alert('请选择要删除的项目');
                return;
            }
            if (!confirm(`确定要删除选中的 ${ids.length} 个项目吗？`)) {
                return;
            }
            const form = document.getElementById('batchForm');
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'action';
            input.value = 'delete';
            form.appendChild(input);
            form.submit();
        }

        function batchEditCategory() {
            const ids = getSelectedIds();
            if (ids.length === 0) {
                alert('请选择要修改的项目');
                return;
            }

            // 显示模态框
            document.getElementById('categoryModal').style.display = 'block';
        }

        function closeCategoryModal() {
            document.getElementById('categoryModal').style.display = 'none';
            // 清空选择
            const select = document.getElementById('batchCategorySelect');
            Array.from(select.options).forEach(option => {
                option.selected = false;
            });
        }

        function submitBatchCategory() {
            const select = document.getElementById('batchCategorySelect');
            const selectedCategories = Array.from(select.selectedOptions).map(option => option.value);
            const categoryIds = selectedCategories.join(',');

            const form = document.getElementById('batchForm');

            // 清除之前的隐藏字段
            const oldInputs = form.querySelectorAll('input[name="action"][value="update_category"]');
            oldInputs.forEach(input => input.remove());
            const oldCategoryInputs = form.querySelectorAll('input[name="category_ids"]');
            oldCategoryInputs.forEach(input => input.remove());

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'update_category';
            form.appendChild(actionInput);

            if (categoryIds) {
                const categoryInput = document.createElement('input');
                categoryInput.type = 'hidden';
                categoryInput.name = 'category_ids';
                categoryInput.value = categoryIds;
                form.appendChild(categoryInput);
            }

            closeCategoryModal();
            form.submit();
        }

        function applyFilters() {
            const categoryId = document.querySelector('select[name="category_id"]')?.value || '';
            const status = document.querySelector('select[name="status"]')?.value || -1;
            const keyword = document.querySelector('input[name="keyword"]')?.value || '';
            const orderBy = document.querySelector('select[name="order_by"]')?.value || 'created_at';
            const orderDir = document.querySelector('select[name="order_dir"]')?.value || 'DESC';

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

        // 点击模态框外部关闭
        window.onclick = function(event) {
            const modal = document.getElementById('categoryModal');
            if (event.target == modal) {
                closeCategoryModal();
            }
        }

        function batchToggleStatus(status) {
            const ids = getSelectedIds();
            if (ids.length === 0) {
                alert('请选择要操作的项目');
                return;
            }
            const statusText = status == 1 ? '上架' : '下架';
            if (!confirm(`确定要将选中的 ${ids.length} 个项目设置为${statusText}吗？`)) {
                return;
            }

            const form = document.getElementById('batchForm');
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'toggle_status';
            form.appendChild(actionInput);

            const statusInput = document.createElement('input');
            statusInput.type = 'hidden';
            statusInput.name = 'status';
            statusInput.value = status;
            form.appendChild(statusInput);

            form.submit();
        }

        function toggleStatus(materialId, currentStatus) {
            const newStatus = currentStatus == 1 ? 0 : 1;

            const formData = new FormData();
            formData.append('material_id', materialId);
            formData.append('status', newStatus);

            fetch('toggle-status.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('操作失败：' + data.message);
                    location.reload(); // 刷新以恢复状态
                }
            })
            .catch(error => {
                alert('操作失败：' + error);
                location.reload();
            });
        }
    </script>
</head>
<body>
    <div class="admin-layout">
        <?php include '../common/sidebar.php'; ?>
        <div class="main-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <h1 style="margin: 0; font-size: 20px;">纯文案素材管理</h1>
                <div>
                    <a href="add.php" style="display: inline-block; padding: 6px 12px; background: #667eea; color: white; text-decoration: none; border-radius: 4px; margin-left: 10px; font-size: 13px;">添加文案</a>
                    <a href="import.php" style="display: inline-block; padding: 6px 12px; background: #3498db; color: white; text-decoration: none; border-radius: 4px; margin-left: 10px; font-size: 13px;">批量导入</a>
        </div>
        </div>

        <!-- 筛选和排序 -->
        <div class="filters">
            <div class="search-input">
                <label>搜索：</label>
                <input type="text" name="keyword" placeholder="输入内容或ID搜索..." value="<?php echo htmlspecialchars($keyword); ?>"
                       onkeypress="handleSearchKeyPress(event)" style="width: 100%;">
            </div>
            <div>
                <label>分类：</label>
                <select name="category_id" onchange="applyFilters()">
                    <option value="">全部分类</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['category_id']; ?>" <?php echo $categoryId == $cat['category_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>状态：</label>
                <select name="status" onchange="applyFilters()">
                    <option value="-1">全部状态</option>
                    <option value="1" <?php echo $status == 1 ? 'selected' : ''; ?>>上架</option>
                    <option value="0" <?php echo $status == 0 ? 'selected' : ''; ?>>下架</option>
                </select>
            </div>
            <div>
                <label>排序：</label>
                <select name="order_by" onchange="applyFilters()">
                    <option value="created_at" <?php echo $orderBy == 'created_at' ? 'selected' : ''; ?>>创建时间</option>
                    <option value="copy_count" <?php echo $orderBy == 'copy_count' ? 'selected' : ''; ?>>复制次数</option>
                    <option value="like_count" <?php echo $orderBy == 'like_count' ? 'selected' : ''; ?>>点赞数</option>
                    <option value="status" <?php echo $orderBy == 'status' ? 'selected' : ''; ?>>状态</option>
                </select>
                <select name="order_dir" onchange="applyFilters()">
                    <option value="DESC" <?php echo $orderDir == 'DESC' ? 'selected' : ''; ?>>降序</option>
                    <option value="ASC" <?php echo $orderDir == 'ASC' ? 'selected' : ''; ?>>升序</option>
                </select>
            </div>
        </div>

        <div style="background: white; padding: 15px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <button type="button" onclick="document.getElementById('selectAll').checked=true;toggleAll()" class="btn" style="background: #667eea; color: white; margin-right: 10px;">全选</button>
            <button type="button" onclick="document.getElementById('selectAll').checked=false;toggleAll()" class="btn" style="background: #95a5a6; color: white; margin-right: 10px;">取消全选</button>
            <button type="button" onclick="batchDelete()" class="btn btn-delete" style="margin-right: 10px;">批量删除</button>
            <button type="button" onclick="batchEditCategory()" class="btn btn-edit" style="margin-right: 10px;">批量修改分类</button>
            <button type="button" onclick="batchToggleStatus(1)" class="btn" style="background: #27ae60; color: white; margin-right: 10px;">批量上架</button>
            <button type="button" onclick="batchToggleStatus(0)" class="btn" style="background: #e67e22; color: white;">批量下架</button>
        </div>

        <form id="batchForm" method="POST" action="batch-action.php">
        <table>
            <thead>
                <tr>
                    <th width="50"><input type="checkbox" id="selectAll" onchange="toggleAll()"></th>
                    <th width="180">ID</th>
                    <th>文案内容</th>
                    <th>分类</th>
                    <th>复制次数</th>
                    <th>点赞数</th>
                    <th>状态</th>
                    <th>创建时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($materials as $material): ?>
                <tr>
                    <td><input type="checkbox" name="ids[]" value="<?php echo $material['material_id']; ?>"></td>
                    <td title="<?php echo htmlspecialchars($material['material_id']); ?>" style="font-size: 11px; word-break: break-all;"><?php echo htmlspecialchars($material['material_id']); ?></td>
                    <td class="content-preview editable-content" onclick="openEditModal('<?php echo $material['material_id']; ?>', '<?php echo htmlspecialchars($material['content'], ENT_QUOTES); ?>')" title="点击编辑" data-material-id="<?php echo $material['material_id']; ?>">
                        <?php echo htmlspecialchars(mb_substr($material['content'], 0, 50)); ?>...
                    </td>
                    <td>
                        <?php if (!empty($material['categories'])): ?>
                            <div class="categories">
                                <?php foreach ($material['categories'] as $cat): ?>
                                    <span class="category-tag"><?php echo htmlspecialchars($cat['name']); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <span style="color: #999;">未分类</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $material['copy_count']; ?></td>
                    <td><?php echo $material['like_count']; ?></td>
                    <td>
                        <div class="status-toggle">
                            <label class="switch">
                                <input type="checkbox" <?php echo $material['status'] == 1 ? 'checked' : ''; ?> onchange="toggleStatus('<?php echo $material['material_id']; ?>', <?php echo $material['status']; ?>, this)">
                                <span class="slider"></span>
                            </label>
                            <span class="status-text"><?php echo $material['status'] == 1 ? '上架' : '下架'; ?></span>
                        </div>
                    </td>
                    <td><?php echo date('Y-m-d H:i', strtotime($material['created_at'])); ?></td>
                    <td>
                        <a href="edit.php?id=<?php echo $material['material_id']; ?>" class="btn btn-edit">编辑</a>
                        <a href="delete.php?id=<?php echo $material['material_id']; ?>" class="btn btn-delete" onclick="return confirm('确定删除吗？')">删除</a>
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
        </form>
    </div>

    <!-- 批量修改分类模态框 -->
    <div id="categoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>批量修改分类</h2>
                <span class="close" onclick="closeCategoryModal()">&times;</span>
            </div>
            <div class="modal-body">
                <label>选择分类（可多选，按住Ctrl/Cmd键选择多个）：</label>
                <select id="batchCategorySelect" multiple size="8">
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['category_id']; ?>">
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p style="margin-top: 10px; color: #666; font-size: 12px;">
                    提示：不选择任何分类将清除所有分类关联
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeCategoryModal()" class="btn" style="background: #95a5a6; color: white;">取消</button>
                <button type="button" onclick="submitBatchCategory()" class="btn btn-edit">确认</button>
            </div>
            </div>
        </div>
    </div>

    <div id="editModal" class="edit-modal">
        <div class="edit-modal-content">
            <div class="edit-modal-header">
                <h3>编辑文案内容</h3>
                <span class="edit-modal-close" onclick="closeEditModal()">&times;</span>
            </div>
            <div class="edit-modal-body">
                <input type="hidden" id="editMaterialId">
                <label>文案内容：</label>
                <textarea id="editContent" placeholder="请输入文案内容"></textarea>
            </div>
            <div class="edit-modal-footer">
                <button type="button" onclick="closeEditModal()" class="btn" style="background: #95a5a6; color: white;">取消</button>
                <button type="button" id="saveEditBtn" onclick="saveEditContent()" class="btn btn-edit" style="background: #667eea; color: white;">保存</button>
            </div>
        </div>
    </div>
</body>
</html>
