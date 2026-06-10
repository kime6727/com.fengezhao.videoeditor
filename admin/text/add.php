<?php
/**
 * 添加纯文案素材
 */
require_once '../common/session.php';
require_once '../../common/db.php';
require_once '../../common/functions.php';

checkAdminLogin();

global $pdo;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = trim($_POST['content'] ?? '');
    $categoryIds = $_POST['category_ids'] ?? [];
    $status = intval($_POST['status'] ?? 1);
    $batchMode = isset($_POST['batch_mode']) && $_POST['batch_mode'] == '1';

    if (empty($content)) {
        $error = '文案内容不能为空';
    } else {
        try {
            $pdo->beginTransaction();

            // 如果是批量模式，按换行分割成多条
            $contents = $batchMode ? array_filter(array_map('trim', explode("\n", $content))) : [$content];

            $successCount = 0;
            foreach ($contents as $singleContent) {
                if (empty($singleContent)) continue;

                // 生成素材ID
                $materialId = generateUniqueIdWithCheck('text_materials', 'material_id');

                // 插入文案素材
                $stmt = $pdo->prepare("INSERT INTO `text_materials` (`material_id`, `content`, `status`) VALUES (?, ?, ?)");
                $stmt->execute([$materialId, $singleContent, $status]);

                // 添加分类关联
                if (!empty($categoryIds)) {
                    $stmt = $pdo->prepare("INSERT INTO `category_relations`
                                          (`category_id`, `material_id`, `material_type`)
                                          VALUES (?, ?, 4)");
                    foreach ($categoryIds as $categoryId) {
                        $stmt->execute([$categoryId, $materialId]);
                    }
                }

                $successCount++;
            }

            $pdo->commit();
            $success = $batchMode ? "批量添加成功！共添加 {$successCount} 条文案" : '添加成功！';

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = '添加失败：' . $e->getMessage();
        }
    }
}

// 获取分类列表（纯文案类型）
$stmt = $pdo->prepare("SELECT `category_id`, `name` FROM `categories`
                      WHERE `type` = 4 AND `status` = 1
                      ORDER BY `sort` ASC");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>添加纯文案素材</title>
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
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            min-height: 150px;
            font-family: inherit;
        }
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .checkbox-group label {
            display: flex;
            align-items: center;
            font-weight: normal;
        }
        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin-right: 5px;
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
    <script>
        function showBatchInput() {
            document.getElementById('batch-input').style.display = 'block';
        }

        function hideBatchInput() {
            document.getElementById('batch-input').style.display = 'none';
            document.getElementById('batch-contents').value = '';
        }

        function parseBatchContents() {
            const text = document.getElementById('batch-contents').value.trim();
            if (!text) {
                alert('请输入文案内容');
                return;
            }

            const contents = text.split('\n').map(content => content.trim()).filter(content => content);
            if (contents.length === 0) {
                alert('没有有效的文案');
                return;
            }

            // 将多条文案合并，用换行分隔
            document.getElementById('content').value = contents.join('\n');
            // 添加隐藏字段标记批量模式
            if (!document.getElementById('batch_mode')) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'batch_mode';
                input.id = 'batch_mode';
                input.value = '1';
                document.getElementById('batchForm').appendChild(input);
            }
            hideBatchInput();
            alert('已解析 ' + contents.length + ' 条文案，点击"添加"按钮批量提交');
        }
    </script>
</head>
<body>
    <div class="container">
        <a href="list.php" class="back-link">← 返回列表</a>
        <h1>添加纯文案素材</h1>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" id="batchForm">
            <div class="form-group">
                <label>文案内容 *</label>
                <button type="button" onclick="showBatchInput()" style="padding: 8px 16px; background: #3498db; color: white; border: none; border-radius: 4px; cursor: pointer; margin-bottom: 10px;">批量粘贴文案</button>
                <div id="batch-input" style="display: none; margin-bottom: 10px;">
                    <textarea id="batch-contents" placeholder="请粘贴文案，每行一条&#10;例如：&#10;文案1&#10;文案2&#10;文案3" style="min-height: 200px; width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
                    <button type="button" onclick="parseBatchContents()" style="margin-top: 10px; padding: 8px 16px; background: #27ae60; color: white; border: none; border-radius: 4px; cursor: pointer;">解析并添加</button>
                    <button type="button" onclick="hideBatchInput()" style="margin-top: 10px; padding: 8px 16px; background: #95a5a6; color: white; border: none; border-radius: 4px; cursor: pointer;">取消</button>
                </div>
                <textarea name="content" id="content" required placeholder="请输入文案内容"><?php echo htmlspecialchars($_POST['content'] ?? ''); ?></textarea>
                <div style="color: #999; font-size: 12px; margin-top: 5px;">支持批量粘贴，每行一条文案</div>
            </div>

            <div class="form-group">
                <label>分类（可多选）</label>
                <div class="checkbox-group">
                    <?php foreach ($categories as $category): ?>
                        <label>
                            <input type="checkbox" name="category_ids[]" value="<?php echo $category['category_id']; ?>">
                            <?php echo htmlspecialchars($category['name']); ?>
                        </label>
                    <?php endforeach; ?>
                    <?php if (empty($categories)): ?>
                        <span style="color: #999;">暂无分类，请先<a href="../category/list.php">创建分类</a></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-group">
                <label>状态</label>
                <select name="status">
                    <option value="1" <?php echo (($_POST['status'] ?? 1) == 1) ? 'selected' : ''; ?>>上架</option>
                    <option value="0" <?php echo (($_POST['status'] ?? 1) == 0) ? 'selected' : ''; ?>>下架</option>
                </select>
            </div>

            <button type="submit">添加</button>
        </form>
    </div>
</body>
</html>
