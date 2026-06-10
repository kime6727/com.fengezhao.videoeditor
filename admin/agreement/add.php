<?php
/**
 * 添加协议
 */
require_once '../common/session.php';
require_once '../../common/db.php';
require_once '../../common/functions.php';

checkAdminLogin();

global $pdo;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $sort = intval($_POST['sort'] ?? 0);
    $status = intval($_POST['status'] ?? 1);

    if (empty($title) || empty($content) || empty($type)) {
        $error = '链接名称、URL地址和类型不能为空';
    } else {
        try {
            $linkId = generateUniqueIdWithCheck('agreements', 'agreement_id');

            $stmt = $pdo->prepare("INSERT INTO `agreements`
                                  (`agreement_id`, `title`, `content`, `type`, `version`, `sort`, `status`)
                                  VALUES (?, ?, ?, ?, '1.0', ?, ?)");
            $stmt->execute([$linkId, $title, $content, $type, $sort, $status]);

            $success = '添加成功！';

        } catch (Exception $e) {
            $error = '添加失败：' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>添加链接</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            padding: 20px;
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
        input[type="text"], textarea, select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        textarea {
            min-height: 300px;
            font-family: monospace;
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
        <h1>添加链接</h1>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>链接名称 *</label>
                <input type="text" name="title" required value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" placeholder="如：用户协议">
            </div>

            <div class="form-group">
                <label>链接类型 *</label>
                <select name="type" required>
                    <option value="">请选择</option>
                    <option value="user_agreement" <?php echo (($_POST['type'] ?? '') == 'user_agreement') ? 'selected' : ''; ?>>用户协议</option>
                    <option value="privacy_policy" <?php echo (($_POST['type'] ?? '') == 'privacy_policy') ? 'selected' : ''; ?>>隐私政策</option>
                    <option value="auto_renewal" <?php echo (($_POST['type'] ?? '') == 'auto_renewal') ? 'selected' : ''; ?>>自动续费协议</option>
                    <option value="help_center" <?php echo (($_POST['type'] ?? '') == 'help_center') ? 'selected' : ''; ?>>帮助中心</option>
                    <option value="feedback" <?php echo (($_POST['type'] ?? '') == 'feedback') ? 'selected' : ''; ?>>客服反馈</option>
                    <option value="about" <?php echo (($_POST['type'] ?? '') == 'about') ? 'selected' : ''; ?>>关于我们</option>
                </select>
            </div>

            <div class="form-group">
                <label>URL地址 *</label>
                <input type="url" name="content" required placeholder="输入跳转URL地址（如：https://www.example.com/agreement.html）" value="<?php echo htmlspecialchars($_POST['content'] ?? ''); ?>" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">请输入完整的URL地址，客户端点击后将跳转到该URL</small>
            </div>

            <div class="form-group">
                <label>排序</label>
                <input type="number" name="sort" value="<?php echo $_POST['sort'] ?? 0; ?>" min="0">
            </div>

            <div class="form-group">
                <label>状态</label>
                <select name="status">
                    <option value="1" <?php echo (($_POST['status'] ?? 1) == 1) ? 'selected' : ''; ?>>启用</option>
                    <option value="0" <?php echo (($_POST['status'] ?? 1) == 0) ? 'selected' : ''; ?>>禁用</option>
                </select>
            </div>

            <button type="submit">添加</button>
        </form>
            </div>
        </div>
    </div>
</body>
</html>
