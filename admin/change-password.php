<?php
/**
 * 修改管理员密码
 */
require_once __DIR__ . '/common/session.php';

checkAdminLogin();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = '所有字段不能为空';
    } elseif ($newPassword !== $confirmPassword) {
        $error = '新密码两次输入不一致';
    } elseif (strlen($newPassword) < 6) {
        $error = '新密码长度不能少于6位';
    } else {
        $username = $_SESSION['admin_username'] ?? 'admin';

        // 验证当前密码
        $isValid = verifyAdminPassword($username, $currentPassword);

        if ($isValid) {
            // 更新密码
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            if (updateAdminPassword($username, $hashedPassword)) {
                $success = '密码修改成功！请使用新密码登录';
            } else {
                $error = '密码更新失败，请重试';
            }
        } else {
            $error = '当前密码错误';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>修改密码</title>
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
            max-width: 500px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            margin-bottom: 30px;
            color: #333;
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
        input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        button {
            width: 100%;
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
        .tip {
            background: #e7f3ff;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #0066cc;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include 'common/sidebar.php'; ?>
        <div class="main-content">
    <div class="container">
        <a href="index.php" class="back-link">← 返回首页</a>
        <h1>修改密码</h1>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="tip">
            ℹ️ 提示：新密码长度不能少于6位，建议使用字母、数字和符号组合
        </div>

        <form method="POST">
            <div class="form-group">
                <label>当前密码 *</label>
                <input type="password" name="current_password" required placeholder="请输入当前密码">
            </div>

            <div class="form-group">
                <label>新密码 *</label>
                <input type="password" name="new_password" required placeholder="请输入新密码（至少6位）">
            </div>

            <div class="form-group">
                <label>确认新密码 *</label>
                <input type="password" name="confirm_password" required placeholder="请再次输入新密码">
            </div>

            <button type="submit">修改密码</button>
        </form>
            </div>
        </div>
    </div>
</body>
</html>
