<?php
/**
 * 编辑Banner
 */
require_once '../common/session.php';
require_once '../../common/db.php';
require_once '../../common/functions.php';

checkAdminLogin();

global $pdo;

$bannerId = $_GET['id'] ?? '';

if (empty($bannerId)) {
    header('Location: list.php');
    exit;
}

// 获取Banner信息
$stmt = $pdo->prepare("SELECT * FROM `banners` WHERE `banner_id` = ?");
$stmt->execute([$bannerId]);
$banner = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$banner) {
    header('Location: list.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $imageUrl = trim($_POST['image_url'] ?? '');
    $linkUrl = trim($_POST['link_url'] ?? '');
    $linkType = intval($_POST['link_type'] ?? 0);
    $sort = intval($_POST['sort'] ?? 0);
    $status = intval($_POST['status'] ?? 1);
    $startTime = $_POST['start_time'] ?: null;
    $endTime = $_POST['end_time'] ?: null;

    if (empty($imageUrl)) {
        $error = '图片URL不能为空';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE `banners`
                                  SET `title` = ?, `image_url` = ?, `link_url` = ?, `link_type` = ?,
                                      `sort` = ?, `status` = ?, `start_time` = ?, `end_time` = ?,
                                      `updated_at` = NOW()
                                  WHERE `banner_id` = ?");
            $stmt->execute([$title, $imageUrl, $linkUrl, $linkType,
                           $sort, $status, $startTime, $endTime, $bannerId]);

            $success = '更新成功！';

            // 重新获取数据
            $stmt = $pdo->prepare("SELECT * FROM `banners` WHERE `banner_id` = ?");
            $stmt->execute([$bannerId]);
            $banner = $stmt->fetch(PDO::FETCH_ASSOC);

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
    <title>编辑Banner</title>
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
        input[type="text"], input[type="url"], input[type="datetime-local"], select {
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
    <div class="container">
        <a href="list.php" class="back-link">← 返回列表</a>
        <h1>编辑Banner</h1>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>标题</label>
                <input type="text" name="title" value="<?php echo htmlspecialchars($banner['title'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>图片URL *</label>
                <input type="url" name="image_url" required value="<?php echo htmlspecialchars($banner['image_url']); ?>">
            </div>

            <div class="form-group">
                <label>链接URL</label>
                <input type="url" name="link_url" value="<?php echo htmlspecialchars($banner['link_url'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>链接类型</label>
                <select name="link_type">
                    <option value="0" <?php echo $banner['link_type'] == 0 ? 'selected' : ''; ?>>无链接</option>
                    <option value="1" <?php echo $banner['link_type'] == 1 ? 'selected' : ''; ?>>内部页面</option>
                    <option value="2" <?php echo $banner['link_type'] == 2 ? 'selected' : ''; ?>>外部链接</option>
                </select>
            </div>

            <div class="form-group">
                <label>排序（数字越小越靠前）</label>
                <input type="number" name="sort" value="<?php echo $banner['sort']; ?>" min="0">
            </div>

            <div class="form-group">
                <label>开始时间（可选）</label>
                <input type="datetime-local" name="start_time"
                       value="<?php echo $banner['start_time'] ? date('Y-m-d\TH:i', strtotime($banner['start_time'])) : ''; ?>">
            </div>

            <div class="form-group">
                <label>结束时间（可选）</label>
                <input type="datetime-local" name="end_time"
                       value="<?php echo $banner['end_time'] ? date('Y-m-d\TH:i', strtotime($banner['end_time'])) : ''; ?>">
            </div>

            <div class="form-group">
                <label>状态</label>
                <select name="status">
                    <option value="1" <?php echo $banner['status'] == 1 ? 'selected' : ''; ?>>启用</option>
                    <option value="0" <?php echo $banner['status'] == 0 ? 'selected' : ''; ?>>禁用</option>
                </select>
            </div>

            <button type="submit">更新</button>
        </form>
    </div>
</body>
</html>
