<?php
/**
 * 编辑协议
 */
require_once '../common/session.php';
require_once '../../common/db.php';
require_once '../../common/functions.php';

checkAdminLogin();

global $pdo;

$agreementId = $_GET['id'] ?? '';

if (empty($agreementId)) {
    header('Location: list.php');
    exit;
}

// 获取协议信息
$stmt = $pdo->prepare("SELECT * FROM `agreements` WHERE `agreement_id` = ?");
$stmt->execute([$agreementId]);
$agreement = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$agreement) {
    header('Location: list.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $type = trim($_POST['type'] ?? '');
    $version = trim($_POST['version'] ?? '1.0');
    $sort = intval($_POST['sort'] ?? 0);
    $status = intval($_POST['status'] ?? 1);

    if (empty($title) || empty($content) || empty($type)) {
        $error = '标题、URL地址和类型不能为空';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE `agreements`
                                  SET `title` = ?, `content` = ?, `type` = ?, `version` = ?,
                                      `sort` = ?, `status` = ?, `updated_at` = NOW()
                                  WHERE `agreement_id` = ?");
            $stmt->execute([$title, $content, $type, $version, $sort, $status, $agreementId]);

            $success = '更新成功！';

            // 重新获取数据
            $stmt = $pdo->prepare("SELECT * FROM `agreements` WHERE `agreement_id` = ?");
            $stmt->execute([$agreementId]);
            $agreement = $stmt->fetch(PDO::FETCH_ASSOC);

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
    <title>编辑协议</title>
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
                <h1>编辑链接</h1>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>协议标题 *</label>
                <input type="text" name="title" required value="<?php echo htmlspecialchars($agreement['title']); ?>">
            </div>

            <div class="form-group">
                <label>协议类型 *</label>
                <select name="type" required>
                    <option value="user_agreement" <?php echo $agreement['type'] == 'user_agreement' ? 'selected' : ''; ?>>用户协议</option>
                    <option value="privacy_policy" <?php echo $agreement['type'] == 'privacy_policy' ? 'selected' : ''; ?>>隐私政策</option>
                    <option value="auto_renewal" <?php echo $agreement['type'] == 'auto_renewal' ? 'selected' : ''; ?>>自动续费协议</option>
                </select>
            </div>

            <div class="form-group">
                <label>版本号</label>
                <input type="text" name="version" value="<?php echo htmlspecialchars($agreement['version']); ?>">
            </div>

            <div class="form-group">
                <label>URL地址 *</label>
                <input type="url" name="content" required value="<?php echo htmlspecialchars($agreement['content']); ?>" placeholder="输入跳转URL地址（如：https://www.example.com/agreement.html）" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">请输入完整的URL地址，客户端点击后将跳转到该URL</small>
            </div>

            <div class="form-group">
                <label>排序</label>
                <input type="number" name="sort" value="<?php echo $agreement['sort']; ?>" min="0">
            </div>

            <div class="form-group">
                <label>状态</label>
                <select name="status">
                    <option value="1" <?php echo $agreement['status'] == 1 ? 'selected' : ''; ?>>启用</option>
                    <option value="0" <?php echo $agreement['status'] == 0 ? 'selected' : ''; ?>>禁用</option>
                </select>
            </div>

            <button type="submit">更新</button>
        </form>
            </div>
        </div>
    </div>
</body>
</html>
