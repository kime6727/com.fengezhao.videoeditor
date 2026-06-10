<?php
/**
 * 批量操作管理页面
 */
require_once __DIR__ . '/common/session.php';
require_once __DIR__ . '/../common/db.php';
require_once __DIR__ . '/../common/functions.php';

checkAdminLogin();

global $pdo;

$message = '';
$error = '';

// 处理批量操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $materialType = intval($_POST['material_type'] ?? 0);
    $selectedIds = $_POST['selected_ids'] ?? [];

    if (empty($selectedIds)) {
        $error = '请选择要操作的素材';
    } elseif (empty($action)) {
        $error = '请选择操作类型';
    } else {
        try {
            $pdo->beginTransaction();

            $successCount = 0;
            $tableName = '';
            $idField = 'material_id';

            // 根据素材类型确定表名
            switch ($materialType) {
                case 1:
                    $tableName = 'video_materials';
                    break;
                case 2:
                    $tableName = 'image_text_materials';
                    break;
                case 3:
                    $tableName = 'video_text_materials';
                    break;
                case 4:
                    $tableName = 'text_materials';
                    break;
                default:
                    throw new Exception('无效的素材类型');
            }

            $placeholders = str_repeat('?,', count($selectedIds) - 1) . '?';

            switch ($action) {
                case 'batch_status':
                    $newStatus = intval($_POST['new_status'] ?? 1);
                    $stmt = $pdo->prepare("UPDATE `{$tableName}` SET `status` = ? WHERE `material_id` IN ({$placeholders})");
                    $stmt->execute(array_merge([$newStatus], $selectedIds));
                    $successCount = $stmt->rowCount();
                    $message = "成功更新 {$successCount} 个素材的状态";
                    break;

                case 'batch_delete':
                    // 先删除分类关联
                    $stmt = $pdo->prepare("DELETE FROM `category_relations` WHERE `material_id` IN ({$placeholders}) AND `material_type` = ?");
                    $stmt->execute(array_merge($selectedIds, [$materialType]));

                    // 删除收藏记录
                    $stmt = $pdo->prepare("DELETE FROM `user_favorites` WHERE `material_id` IN ({$placeholders}) AND `material_type` = ?");
                    $stmt->execute(array_merge($selectedIds, [$materialType]));

                    // 删除下载记录
                    $stmt = $pdo->prepare("DELETE FROM `download_logs` WHERE `material_id` IN ({$placeholders}) AND `material_type` = ?");
                    $stmt->execute(array_merge($selectedIds, [$materialType]));

                    // 删除素材主表记录
                    $stmt = $pdo->prepare("DELETE FROM `{$tableName}` WHERE `material_id` IN ({$placeholders})");
                    $stmt->execute($selectedIds);
                    $successCount = $stmt->rowCount();
                    $message = "成功删除 {$successCount} 个素材";
                    break;

                case 'batch_category':
                    $newCategoryId = $_POST['new_category_id'] ?? '';
                    if (empty($newCategoryId)) {
                        throw new Exception('请选择新分类');
                    }

                    // 删除旧的分类关联
                    $stmt = $pdo->prepare("DELETE FROM `category_relations` WHERE `material_id` IN ({$placeholders}) AND `material_type` = ?");
                    $stmt->execute(array_merge($selectedIds, [$materialType]));

                    // 添加新的分类关联
                    $stmt = $pdo->prepare("INSERT INTO `category_relations` (`category_id`, `material_id`, `material_type`) VALUES (?, ?, ?)");
                    foreach ($selectedIds as $materialId) {
                        $stmt->execute([$newCategoryId, $materialId, $materialType]);
                        $successCount++;
                    }
                    $message = "成功将 {$successCount} 个素材移动到新分类";
                    break;

                default:
                    throw new Exception('未知的操作类型');
            }

            $pdo->commit();

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = '操作失败：' . $e->getMessage();
        }
    }
}

// 获取素材统计信息
$stats = [
    'videos' => $pdo->query("SELECT COUNT(*) FROM `video_materials` WHERE `status` = 1")->fetchColumn(),
    'image_texts' => $pdo->query("SELECT COUNT(*) FROM `image_text_materials` WHERE `status` = 1")->fetchColumn(),
    'video_texts' => $pdo->query("SELECT COUNT(*) FROM `video_text_materials` WHERE `status` = 1")->fetchColumn(),
    'texts' => $pdo->query("SELECT COUNT(*) FROM `text_materials` WHERE `status` = 1")->fetchColumn(),
];

// 获取所有分类
$categories = $pdo->query("SELECT `category_id`, `name`, `type` FROM `categories` WHERE `status` = 1 ORDER BY `type`, `sort`")->fetchAll(PDO::FETCH_ASSOC);

$typeNames = [1 => '单视频', 2 => '图片+文案', 3 => '视频+文案', 4 => '纯文案'];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>批量操作管理</title>
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
        .page-header {
            margin-bottom: 30px;
        }
        .page-title {
            font-size: 24px;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
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
        .section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .section h2 {
            font-size: 18px;
            margin-bottom: 20px;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            text-align: center;
        }
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #667eea;
        }
        .stat-label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        select, input[type="text"], textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .radio-group {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        .radio-item {
            display: flex;
            align-items: center;
        }
        .radio-item input[type="radio"] {
            width: auto;
            margin-right: 8px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-right: 10px;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .help-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .operation-panel {
            display: none;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            margin-top: 15px;
        }
        .operation-panel.active {
            display: block;
        }
    </style>
    <script>
        function selectOperation(operation) {
            // 隐藏所有操作面板
            document.querySelectorAll('.operation-panel').forEach(panel => {
                panel.classList.remove('active');
            });

            // 显示选中的操作面板
            if (operation) {
                const panel = document.getElementById(operation + 'Panel');
                if (panel) {
                    panel.classList.add('active');
                }
            }
        }

        function validateForm() {
            const selectedMaterialType = document.querySelector('input[name="material_type"]:checked');
            if (!selectedMaterialType) {
                alert('请选择素材类型');
                return false;
            }

            const selectedAction = document.querySelector('input[name="action"]:checked');
            if (!selectedAction) {
                alert('请选择操作类型');
                return false;
            }

            // 批量删除操作需要二次确认
            if (selectedAction.value === 'batch_delete') {
                if (!confirm('确定要删除选中的素材吗？此操作不可恢复！')) {
                    return false;
                }
            }

            return true;
        }
    </script>
</head>
<body>
    <div class="admin-layout">
        <?php include 'common/sidebar.php'; ?>
        <div class="main-content">
            <div class="container">
                <div class="page-header">
                    <h1 class="page-title">批量操作管理</h1>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <!-- 素材统计 -->
                <div class="section">
                    <h2>素材统计</h2>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stats['videos']; ?></div>
                            <div class="stat-label">单视频素材</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stats['image_texts']; ?></div>
                            <div class="stat-label">图片+文案素材</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stats['video_texts']; ?></div>
                            <div class="stat-label">视频+文案素材</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number"><?php echo $stats['texts']; ?></div>
                            <div class="stat-label">纯文案素材</div>
                        </div>
                    </div>
                </div>

                <!-- 批量操作表单 -->
                <div class="section">
                    <h2>批量操作</h2>
                    <form method="POST" onsubmit="return validateForm()">
                        <div class="form-group">
                            <label>选择素材类型 *</label>
                            <div class="radio-group">
                                <div class="radio-item">
                                    <input type="radio" name="material_type" value="1" id="type1">
                                    <label for="type1">单视频</label>
                                </div>
                                <div class="radio-item">
                                    <input type="radio" name="material_type" value="2" id="type2">
                                    <label for="type2">图片+文案</label>
                                </div>
                                <div class="radio-item">
                                    <input type="radio" name="material_type" value="3" id="type3">
                                    <label for="type3">视频+文案</label>
                                </div>
                                <div class="radio-item">
                                    <input type="radio" name="material_type" value="4" id="type4">
                                    <label for="type4">纯文案</label>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>选择操作类型 *</label>
                            <div class="radio-group">
                                <div class="radio-item">
                                    <input type="radio" name="action" value="batch_status" id="action1" onclick="selectOperation('batch_status')">
                                    <label for="action1">批量修改状态</label>
                                </div>
                                <div class="radio-item">
                                    <input type="radio" name="action" value="batch_category" id="action2" onclick="selectOperation('batch_category')">
                                    <label for="action2">批量移动分类</label>
                                </div>
                                <div class="radio-item">
                                    <input type="radio" name="action" value="batch_delete" id="action3" onclick="selectOperation('batch_delete')">
                                    <label for="action3">批量删除</label>
                                </div>
                            </div>
                        </div>

                        <!-- 批量修改状态面板 -->
                        <div id="batch_statusPanel" class="operation-panel">
                            <div class="form-group">
                                <label>新状态</label>
                                <select name="new_status">
                                    <option value="1">上架</option>
                                    <option value="0">下架</option>
                                </select>
                            </div>
                        </div>

                        <!-- 批量移动分类面板 -->
                        <div id="batch_categoryPanel" class="operation-panel">
                            <div class="form-group">
                                <label>选择新分类</label>
                                <select name="new_category_id">
                                    <option value="">请选择分类</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['category_id']; ?>">
                                            <?php echo htmlspecialchars($category['name']); ?> (<?php echo $typeNames[$category['type']]; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- 批量删除面板 -->
                        <div id="batch_deletePanel" class="operation-panel">
                            <div class="help-text">
                                ⚠️ 注意：删除操作将同时删除该素材的所有分类关联、收藏记录和下载记录，且不可恢复！
                            </div>
                        </div>

                        <div class="form-group">
                            <label>素材ID列表 *</label>
                            <textarea name="selected_ids" rows="10" placeholder="请输入要操作的素材ID，每行一个&#10;例如：&#10;vm1234567890abcdef&#10;vm0987654321fedcba" required></textarea>
                            <div class="help-text">
                                请输入要操作的素材ID，每行一个。可以从各个素材列表页面复制ID。
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">执行批量操作</button>
                        <button type="button" class="btn btn-secondary" onclick="window.location.reload()">重置</button>
                    </form>
                </div>

                <!-- 操作说明 -->
                <div class="section">
                    <h2>操作说明</h2>
                    <div style="line-height: 1.6; color: #666;">
                        <p><strong>1. 批量修改状态：</strong>可以批量将素材设置为上架或下架状态</p>
                        <p><strong>2. 批量移动分类：</strong>将选中的素材移动到指定分类，会覆盖原有的分类设置</p>
                        <p><strong>3. 批量删除：</strong>彻底删除选中的素材及其相关数据，包括分类关联、用户收藏、下载记录等</p>
                        <p><strong>4. 获取素材ID：</strong>在各素材列表页面，可以勾选多个素材后复制ID，或直接查看素材详情获取ID</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
