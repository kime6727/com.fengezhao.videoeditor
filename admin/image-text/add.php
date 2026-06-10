<?php
/**
 * 添加图片+文案素材
 */
require_once '../common/session.php';
require_once '../../common/db.php';
require_once '../../common/functions.php';

checkAdminLogin();

global $pdo;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $imageUrls = array_filter(array_map('trim', $_POST['image_urls'] ?? []));
    $contents = array_filter(array_map('trim', $_POST['contents'] ?? []));
    $categoryIds = $_POST['category_ids'] ?? [];
    $status = intval($_POST['status'] ?? 1);

    if (empty($imageUrls)) {
        $error = '至少需要一张图片';
    } elseif (count($imageUrls) > 9) {
        $error = '图片数量不能超过9张';
    } elseif (empty($contents)) {
        $error = '至少需要一条文案';
    } elseif (count($contents) > 30) {
        $error = '文案数量不能超过30条';
    } else {
        try {
            $pdo->beginTransaction();

            // 生成素材ID
            $materialId = generateUniqueIdWithCheck('image_text_materials', 'material_id');

            // 插入主表
            $stmt = $pdo->prepare("INSERT INTO `image_text_materials` (`material_id`, `status`) VALUES (?, ?)");
            $stmt->execute([$materialId, $status]);

            // 插入图片
            $stmt = $pdo->prepare("INSERT INTO `image_text_images` (`material_id`, `image_url`, `sort`) VALUES (?, ?, ?)");
            foreach ($imageUrls as $index => $url) {
                $stmt->execute([$materialId, $url, $index]);
            }

            // 插入文案
            $stmt = $pdo->prepare("INSERT INTO `image_text_contents` (`material_id`, `content`, `sort`) VALUES (?, ?, ?)");
            foreach ($contents as $index => $content) {
                $stmt->execute([$materialId, $content, $index]);
            }

            // 添加分类关联
            if (!empty($categoryIds)) {
                $stmt = $pdo->prepare("INSERT INTO `category_relations`
                                      (`category_id`, `material_id`, `material_type`)
                                      VALUES (?, ?, 2)");
                foreach ($categoryIds as $categoryId) {
                    $stmt->execute([$categoryId, $materialId]);
                }
            }

            $pdo->commit();
            $success = '添加成功！';

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = '添加失败：' . $e->getMessage();
        }
    }
}

// 获取分类列表（图片+文案类型）
$stmt = $pdo->prepare("SELECT `category_id`, `name` FROM `categories`
                      WHERE `type` = 2 AND `status` = 1
                      ORDER BY `sort` ASC");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>添加图片+文案素材</title>
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
            max-width: 1000px;
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
        input[type="text"], input[type="url"], textarea, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            margin-bottom: 10px;
        }
        textarea {
            min-height: 80px;
        }
        .item-group {
            border: 1px solid #eee;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 4px;
            position: relative;
        }
        .item-group .remove-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 10px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .add-btn {
            padding: 10px 20px;
            background: #27ae60;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-bottom: 20px;
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
        button[type="submit"] {
            padding: 12px 24px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            opacity: 0.9;
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
        .hint {
            color: #999;
            font-size: 12px;
            margin-top: 5px;
        }
    </style>
    <script>
        function addImageField() {
            const container = document.getElementById('images-container');
            const count = container.children.length;
            if (count >= 9) {
                alert('最多只能添加9张图片');
                return;
            }
            const div = document.createElement('div');
            div.className = 'item-group';
            div.innerHTML = `
                <input type="url" name="image_urls[]" placeholder="图片URL" required>
                <button type="button" class="remove-btn" onclick="this.parentElement.remove()">删除</button>
            `;
            container.appendChild(div);
        }

        function addContentField() {
            const container = document.getElementById('contents-container');
            const count = container.children.length;
            if (count >= 30) {
                alert('最多只能添加30条文案');
                return;
            }
            const div = document.createElement('div');
            div.className = 'item-group';
            div.innerHTML = `
                <textarea name="contents[]" placeholder="文案内容" required></textarea>
                <button type="button" class="remove-btn" onclick="this.parentElement.remove()">删除</button>
            `;
            container.appendChild(div);
        }

        function showBatchImageInput() {
            document.getElementById('batch-image-input').style.display = 'block';
        }

        function hideBatchImageInput() {
            document.getElementById('batch-image-input').style.display = 'none';
            document.getElementById('batch-image-urls').value = '';
        }

        function parseBatchImages() {
            const text = document.getElementById('batch-image-urls').value.trim();
            if (!text) {
                alert('请输入图片URL');
                return;
            }

            const urls = text.split('\n').map(url => url.trim()).filter(url => url);
            if (urls.length === 0) {
                alert('没有有效的URL');
                return;
            }

            if (urls.length > 9) {
                alert('最多只能添加9张图片，将只添加前9张');
                urls.splice(9);
            }

            const container = document.getElementById('images-container');
            container.innerHTML = ''; // 清空现有内容

            urls.forEach(url => {
                const div = document.createElement('div');
                div.className = 'item-group';
                div.innerHTML = `
                    <input type="url" name="image_urls[]" value="${url.replace(/"/g, '&quot;')}" required>
                    <button type="button" class="remove-btn" onclick="this.parentElement.remove()">删除</button>
                `;
                container.appendChild(div);
            });

            hideBatchImageInput();
        }

        function showBatchContentInput() {
            document.getElementById('batch-content-input').style.display = 'block';
        }

        function hideBatchContentInput() {
            document.getElementById('batch-content-input').style.display = 'none';
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

            if (contents.length > 30) {
                alert('最多只能添加30条文案，将只添加前30条');
                contents.splice(30);
            }

            const container = document.getElementById('contents-container');
            container.innerHTML = ''; // 清空现有内容

            contents.forEach(content => {
                const div = document.createElement('div');
                div.className = 'item-group';
                div.innerHTML = `
                    <textarea name="contents[]" required>${content.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</textarea>
                    <button type="button" class="remove-btn" onclick="this.parentElement.remove()">删除</button>
                `;
                container.appendChild(div);
            });

            hideBatchContentInput();
        }
    </script>
</head>
<body>
    <div class="admin-layout">
        <?php include '../common/sidebar.php'; ?>
        <div class="main-content">
    <div class="container">
        <a href="list.php" class="back-link">← 返回列表</a>
        <h1>添加图片+文案素材</h1>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>图片（1-9张）*</label>
                <button type="button" class="add-btn" onclick="addImageField()">+ 添加图片</button>
                <button type="button" class="add-btn" onclick="showBatchImageInput()" style="background: #3498db;">批量粘贴图片URL</button>
                <div id="images-container">
                    <div class="item-group">
                        <input type="url" name="image_urls[]" placeholder="图片URL" required>
                    </div>
                </div>
                <div id="batch-image-input" style="display: none; margin-top: 10px;">
                    <textarea id="batch-image-urls" placeholder="请粘贴图片URL，每行一个，最多9个&#10;例如：&#10;https://example.com/image1.jpg&#10;https://example.com/image2.jpg" style="min-height: 150px; width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
                    <button type="button" onclick="parseBatchImages()" style="margin-top: 10px; padding: 8px 16px; background: #27ae60; color: white; border: none; border-radius: 4px; cursor: pointer;">解析并添加</button>
                    <button type="button" onclick="hideBatchImageInput()" style="margin-top: 10px; padding: 8px 16px; background: #95a5a6; color: white; border: none; border-radius: 4px; cursor: pointer;">取消</button>
                </div>
                <div class="hint">至少需要1张图片，最多9张。支持批量粘贴，每行一个URL</div>
            </div>

            <div class="form-group">
                <label>文案（1-30条）*</label>
                <button type="button" class="add-btn" onclick="addContentField()">+ 添加文案</button>
                <button type="button" class="add-btn" onclick="showBatchContentInput()" style="background: #3498db;">批量粘贴文案</button>
                <div id="contents-container">
                    <div class="item-group">
                        <textarea name="contents[]" placeholder="文案内容" required></textarea>
                    </div>
                </div>
                <div id="batch-content-input" style="display: none; margin-top: 10px;">
                    <textarea id="batch-contents" placeholder="请粘贴文案，每行一条，最多30条&#10;例如：&#10;文案1&#10;文案2&#10;文案3" style="min-height: 200px; width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;"></textarea>
                    <button type="button" onclick="parseBatchContents()" style="margin-top: 10px; padding: 8px 16px; background: #27ae60; color: white; border: none; border-radius: 4px; cursor: pointer;">解析并添加</button>
                    <button type="button" onclick="hideBatchContentInput()" style="margin-top: 10px; padding: 8px 16px; background: #95a5a6; color: white; border: none; border-radius: 4px; cursor: pointer;">取消</button>
                </div>
                <div class="hint">至少需要1条文案，最多30条。支持批量粘贴，每行一条</div>
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
        </div>
    </div>
</body>
</html>
