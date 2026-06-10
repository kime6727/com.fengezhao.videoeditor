<?php
/**
 * 编辑分类
 */
require_once '../common/session.php';
require_once '../../common/db.php';
require_once '../../common/functions.php';

checkAdminLogin();

global $pdo;

$categoryId = $_GET['id'] ?? '';

if (empty($categoryId)) {
    header('Location: list.php');
    exit;
}

// 获取分类信息
$stmt = $pdo->prepare("SELECT * FROM `categories` WHERE `category_id` = ?");
$stmt->execute([$categoryId]);
$category = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$category) {
    header('Location: list.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $type = intval($_POST['type'] ?? 0);
    $sort = intval($_POST['sort'] ?? 0);
    $isTop = intval($_POST['is_top'] ?? 0);
    $status = intval($_POST['status'] ?? 1);

    if (empty($name) || $type == 0) {
        $error = '名称和类型不能为空';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE `categories`
                                  SET `name` = ?, `type` = ?, `sort` = ?, `is_top` = ?, `status` = ?
                                  WHERE `category_id` = ?");
            $stmt->execute([$name, $type, $sort, $isTop, $status, $categoryId]);

            $success = '更新成功！';

            // 重新获取数据
            $stmt = $pdo->prepare("SELECT * FROM `categories` WHERE `category_id` = ?");
            $stmt->execute([$categoryId]);
            $category = $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            $error = '更新失败：' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>编辑分类</title>
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
            max-width: 600px;
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
        input[type="text"], input[type="number"], select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
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
        <h1>编辑分类</h1>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>分类名称 *</label>
                <input type="text" name="name" required value="<?php echo htmlspecialchars($category['name']); ?>">
            </div>

            <div class="form-group">
                <label>素材类型 *</label>
                <select name="type" required>
                    <option value="1" <?php echo $category['type'] == 1 ? 'selected' : ''; ?>>单视频</option>
                    <option value="2" <?php echo $category['type'] == 2 ? 'selected' : ''; ?>>图片+文案</option>
                    <option value="3" <?php echo $category['type'] == 3 ? 'selected' : ''; ?>>视频+文案</option>
                    <option value="4" <?php echo $category['type'] == 4 ? 'selected' : ''; ?>>纯文案</option>
                </select>
            </div>

            <div class="form-group">
                <label>排序（数字越小越靠前）</label>
                <input type="number" name="sort" value="<?php echo $category['sort']; ?>" min="0">
            </div>

            <div class="form-group">
                <label>是否置顶</label>
                <select name="is_top">
                    <option value="0" <?php echo $category['is_top'] == 0 ? 'selected' : ''; ?>>否</option>
                    <option value="1" <?php echo $category['is_top'] == 1 ? 'selected' : ''; ?>>是</option>
                </select>
            </div>

            <div class="form-group">
                <label>状态</label>
                <select name="status">
                    <option value="1" <?php echo $category['status'] == 1 ? 'selected' : ''; ?>>启用</option>
                    <option value="0" <?php echo $category['status'] == 0 ? 'selected' : ''; ?>>禁用</option>
                </select>
            </div>

            <button type="submit">更新</button>
        </form>
    </div>
</body>
</html>
