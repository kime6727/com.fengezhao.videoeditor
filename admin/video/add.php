<?php
/**
 * 添加单视频素材
 */
require_once '../common/session.php';
require_once '../../common/db.php';
require_once '../../common/functions.php';

checkAdminLogin();

global $pdo;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $videoUrl = trim($_POST['video_url'] ?? '');
    $thumbnailUrl = trim($_POST['thumbnail_url'] ?? '');
    $categoryIds = $_POST['category_ids'] ?? [];
    $status = intval($_POST['status'] ?? 1);

    if (empty($name) || empty($videoUrl)) {
        $error = '名称和视频URL不能为空';
    } else {
        try {
            $pdo->beginTransaction();

            // 生成素材ID
            $materialId = generateUniqueIdWithCheck('video_materials', 'material_id');

            // 获取发布者（默认使用官方账号）
            $authorId = !empty($_POST['author_id']) ? $_POST['author_id'] : '00000000';

            // 插入视频素材
            $stmt = $pdo->prepare("INSERT INTO `video_materials`
                                   (`material_id`, `author_id`, `name`, `video_url`, `thumbnail_url`, `status`)
                                   VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$materialId, $authorId, $name, $videoUrl, $thumbnailUrl, $status]);

            // 添加分类关联
            if (!empty($categoryIds)) {
                $stmt = $pdo->prepare("INSERT INTO `category_relations`
                                      (`category_id`, `material_id`, `material_type`)
                                      VALUES (?, ?, 1)");
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

// 获取分类列表（单视频类型）
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
    <title>添加单视频素材</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            padding: 20px;
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
        input[type="text"], input[type="url"], textarea, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        textarea {
            min-height: 100px;
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
        <h1>添加单视频素材</h1>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>视频名称 *</label>
                <input type="text" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>发布者</label>
                <select name="author_id">
                    <option value="00000000">好素材官方 (默认)</option>
                    <?php
                    $users = $pdo->query("SELECT `user_id`, `username` FROM `users` WHERE `user_type` = 1 ORDER BY `username` ASC")->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($users as $user):
                        if ($user['user_id'] === '00000000') continue;
                    ?>
                        <option value="<?php echo $user['user_id']; ?>"><?php echo htmlspecialchars($user['username'] ?? $user['user_id']); ?> (<?php echo $user['user_id']; ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>视频URL *</label>
                <input type="url" name="video_url" required value="<?php echo htmlspecialchars($_POST['video_url'] ?? ''); ?>" placeholder="https://example.com/video.mp4">
            </div>

            <div class="form-group">
                <label>缩略图URL</label>
                <input type="url" name="thumbnail_url" value="<?php echo htmlspecialchars($_POST['thumbnail_url'] ?? ''); ?>" placeholder="https://example.com/thumbnail.jpg">
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
