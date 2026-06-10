<?php
/**
 * 编辑视频+文案素材
 */
require_once '../common/session.php';
require_once '../../common/db.php';
require_once '../../common/functions.php';

checkAdminLogin();

global $pdo;

$materialId = $_GET['id'] ?? '';

if (empty($materialId)) {
    header('Location: list.php');
    exit;
}

// 获取素材信息
$stmt = $pdo->prepare("SELECT * FROM `video_text_materials` WHERE `material_id` = ?");
$stmt->execute([$materialId]);
$material = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$material) {
    header('Location: list.php');
    exit;
}

// 获取文案列表
$stmt = $pdo->prepare("SELECT `content`, `sort` FROM `video_text_contents`
                     WHERE `material_id` = ? ORDER BY `sort` ASC");
$stmt->execute([$materialId]);
$contents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取已选分类
$stmt = $pdo->prepare("SELECT `category_id` FROM `category_relations`
                      WHERE `material_id` = ? AND `material_type` = 3");
$stmt->execute([$materialId]);
$selectedCategories = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'category_id');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 从POST获取material_id，如果不存在则使用GET参数（向后兼容）
    $postMaterialId = $_POST['material_id'] ?? $_GET['id'] ?? '';
    if (!empty($postMaterialId)) {
        $materialId = $postMaterialId;
    }

    $videoUrl = trim($_POST['video_url'] ?? '');
    $thumbnailUrl = trim($_POST['thumbnail_url'] ?? '');
    $contents = array_filter(array_map('trim', $_POST['contents'] ?? []));
    $categoryIds = $_POST['category_ids'] ?? [];
    $status = intval($_POST['status'] ?? 1);

    if (empty($materialId)) {
        $error = '素材ID不能为空';
    } elseif (empty($videoUrl)) {
        $error = '视频URL不能为空';
    } elseif (empty($contents)) {
        $error = '至少需要一条文案';
    } elseif (count($contents) > 30) {
        $error = '文案数量不能超过30条';
    } else {
        try {
            $pdo->beginTransaction();

            // 先验证记录是否存在
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM `video_text_materials` WHERE `material_id` = ?");
            $checkStmt->execute([$materialId]);
            if ($checkStmt->fetchColumn() == 0) {
                throw new Exception('未找到要更新的记录，请确认素材ID是否正确');
            }

            // 更新素材（确保只更新指定的material_id）
            $stmt = $pdo->prepare("UPDATE `video_text_materials`
                                  SET `video_url` = ?, `thumbnail_url` = ?, `status` = ?
                                  WHERE `material_id` = ?");
            $stmt->execute([$videoUrl, $thumbnailUrl, $status, $materialId]);

            // 删除旧文案
            $stmt = $pdo->prepare("DELETE FROM `video_text_contents` WHERE `material_id` = ?");
            $stmt->execute([$materialId]);

            // 插入新文案
            $stmt = $pdo->prepare("INSERT INTO `video_text_contents` (`material_id`, `content`, `sort`) VALUES (?, ?, ?)");
            foreach ($contents as $index => $content) {
                $stmt->execute([$materialId, $content, $index]);
            }

            // 删除旧分类关联
            $stmt = $pdo->prepare("DELETE FROM `category_relations`
                                  WHERE `material_id` = ? AND `material_type` = 3");
            $stmt->execute([$materialId]);

            // 添加新分类关联
            if (!empty($categoryIds)) {
                $stmt = $pdo->prepare("INSERT INTO `category_relations`
                                      (`category_id`, `material_id`, `material_type`)
                                      VALUES (?, ?, 3)");
                foreach ($categoryIds as $categoryId) {
                    $stmt->execute([$categoryId, $materialId]);
                }
            }

            $pdo->commit();
            $success = '更新成功！';

            // 重新获取数据
            $stmt = $pdo->prepare("SELECT * FROM `video_text_materials` WHERE `material_id` = ?");
            $stmt->execute([$materialId]);
            $material = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("SELECT `content`, `sort` FROM `video_text_contents`
                                 WHERE `material_id` = ? ORDER BY `sort` ASC");
            $stmt->execute([$materialId]);
            $contents = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("SELECT `category_id` FROM `category_relations`
                                  WHERE `material_id` = ? AND `material_type` = 3");
            $stmt->execute([$materialId]);
            $selectedCategories = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'category_id');

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = '更新失败：' . $e->getMessage();
        }
    }
}

// 获取分类列表
$stmt = $pdo->prepare("SELECT `category_id`, `name` FROM `categories`
                      WHERE `type` = 3 AND `status` = 1
                      ORDER BY `sort` ASC");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>编辑视频+文案素材</title>
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
    </script>
</head>
<body>
    <div class="container">
        <a href="list.php" class="back-link">← 返回列表</a>
        <h1>编辑视频+文案素材</h1>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="material_id" value="<?php echo htmlspecialchars($materialId); ?>">
            <div class="form-group">
                <label>视频URL *</label>
                <input type="url" name="video_url" required value="<?php echo htmlspecialchars($material['video_url']); ?>">
            </div>

            <div class="form-group">
                <label>缩略图URL</label>
                <input type="url" name="thumbnail_url" value="<?php echo htmlspecialchars($material['thumbnail_url'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>文案（1-30条）*</label>
                <button type="button" class="add-btn" onclick="addContentField()">+ 添加文案</button>
                <div id="contents-container">
                    <?php foreach ($contents as $content): ?>
                        <div class="item-group">
                            <textarea name="contents[]" required><?php echo htmlspecialchars($content['content']); ?></textarea>
                            <button type="button" class="remove-btn" onclick="this.parentElement.remove()">删除</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="hint">至少需要1条文案，最多30条</div>
            </div>

            <div class="form-group">
                <label>分类（可多选）</label>
                <div class="checkbox-group">
                    <?php foreach ($categories as $category): ?>
                        <label>
                            <input type="checkbox" name="category_ids[]" value="<?php echo $category['category_id']; ?>"
                                   <?php echo in_array($category['category_id'], $selectedCategories) ? 'checked' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label>状态</label>
                <select name="status">
                    <option value="1" <?php echo $material['status'] == 1 ? 'selected' : ''; ?>>上架</option>
                    <option value="0" <?php echo $material['status'] == 0 ? 'selected' : ''; ?>>下架</option>
                </select>
            </div>

            <button type="submit">更新</button>
        </form>
    </div>
</body>
</html>
