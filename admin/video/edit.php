<?php
/**
 * 编辑单视频素材
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

$error = '';
$success = '';

// 获取视频信息
$stmt = $pdo->prepare("SELECT * FROM `video_materials` WHERE `material_id` = ?");
$stmt->execute([$materialId]);
$video = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$video) {
    header('Location: list.php');
    exit;
}

// 获取已选分类
$stmt = $pdo->prepare("SELECT `category_id` FROM `category_relations`
                      WHERE `material_id` = ? AND `material_type` = 1");
$stmt->execute([$materialId]);
$selectedCategories = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'category_id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 从POST获取material_id，如果不存在则使用GET参数（向后兼容）
    $postMaterialId = $_POST['material_id'] ?? $_GET['id'] ?? '';
    if (!empty($postMaterialId)) {
        $materialId = $postMaterialId;
    }

    $name = trim($_POST['name'] ?? '');
    $videoUrl = trim($_POST['video_url'] ?? '');
    $thumbnailUrl = trim($_POST['thumbnail_url'] ?? '');
    $categoryIds = $_POST['category_ids'] ?? [];
    $status = intval($_POST['status'] ?? 1);

    if (empty($materialId)) {
        $error = '素材ID不能为空';
    } elseif (empty($name) || empty($videoUrl)) {
        $error = '名称和视频URL不能为空';
    } else {
        try {
            $pdo->beginTransaction();

            // 先验证记录是否存在
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM `video_materials` WHERE `material_id` = ?");
            $checkStmt->execute([$materialId]);
            if ($checkStmt->fetchColumn() == 0) {
                throw new Exception('未找到要更新的记录，请确认素材ID是否正确');
            }

            // 更新视频素材（确保只更新指定的material_id）
            $stmt = $pdo->prepare("UPDATE `video_materials`
                                   SET `name` = ?, `video_url` = ?, `thumbnail_url` = ?, `status` = ?
                                   WHERE `material_id` = ?");
            $stmt->execute([$name, $videoUrl, $thumbnailUrl, $status, $materialId]);

            // 删除旧分类关联
            $stmt = $pdo->prepare("DELETE FROM `category_relations`
                                  WHERE `material_id` = ? AND `material_type` = 1");
            $stmt->execute([$materialId]);

            // 添加新分类关联（使用INSERT IGNORE避免重复）
            if (!empty($categoryIds)) {
                $stmt = $pdo->prepare("INSERT IGNORE INTO `category_relations`
                                      (`category_id`, `material_id`, `material_type`)
                                      VALUES (?, ?, 1)");
                foreach ($categoryIds as $categoryId) {
                    if (!empty($categoryId)) {
                        $stmt->execute([$categoryId, $materialId]);
                    }
                }
            }

            $pdo->commit();
            $success = '更新成功！';

            // 重新获取数据
            $stmt = $pdo->prepare("SELECT * FROM `video_materials` WHERE `material_id` = ?");
            $stmt->execute([$materialId]);
            $video = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("SELECT `category_id` FROM `category_relations`
                                  WHERE `material_id` = ? AND `material_type` = 1");
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
                      WHERE `type` = 1 AND `status` = 1
                      ORDER BY `sort` ASC");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>编辑单视频素材</title>
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
        input[type="text"], input[type="url"], select {
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
</head>
<body>
    <div class="admin-layout">
        <?php include '../common/sidebar.php'; ?>
        <div class="main-content">
    <div class="container">
        <a href="list.php" class="back-link">← 返回列表</a>
        <h1>编辑单视频素材</h1>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="material_id" value="<?php echo htmlspecialchars($materialId); ?>">
            <div class="form-group">
                <label>视频名称 *</label>
                <input type="text" name="name" required value="<?php echo htmlspecialchars($video['name']); ?>">
            </div>

            <div class="form-group">
                <label>视频URL *</label>
                <input type="url" name="video_url" required value="<?php echo htmlspecialchars($video['video_url']); ?>">
            </div>

            <div class="form-group">
                <label>缩略图URL</label>
                <input type="url" name="thumbnail_url" value="<?php echo htmlspecialchars($video['thumbnail_url'] ?? ''); ?>">
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
                    <option value="1" <?php echo $video['status'] == 1 ? 'selected' : ''; ?>>上架</option>
                    <option value="0" <?php echo $video['status'] == 0 ? 'selected' : ''; ?>>下架</option>
                </select>
            </div>

            <button type="submit">更新</button>
        </form>
    </div>
</body>
</html>
