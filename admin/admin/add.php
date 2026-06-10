<?php
/**
 * 添加管理员
 */
require_once __DIR__ . '/../common/session.php';
require_once __DIR__ . '/../../common/db.php';

checkAdminLogin();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $realName = trim($_POST['real_name'] ?? '');

    if (empty($username)) {
        $error = '用户名不能为空';
    } elseif (strlen($username) < 2 || strlen($username) > 50) {
        $error = '用户名长度必须在2-50个字符之间';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = '用户名只能包含字母、数字和下划线';
    } elseif (empty($password)) {
        $error = '密码不能为空';
    } elseif (strlen($password) < 6) {
        $error = '密码长度不能少于6位';
    } elseif ($password !== $confirmPassword) {
        $error = '两次输入的密码不一致';
    } else {
        global $pdo;

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `admins` WHERE `username` = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() > 0) {
            $error = '用户名已存在';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO `admins` (`username`, `password`, `email`, `real_name`, `status`) VALUES (?, ?, ?, ?, 1)");
            $result = $stmt->execute([$username, $hashedPassword, $email, $realName]);

            if ($result) {
                $success = '管理员添加成功！';
                $_POST = [];
            } else {
                $error = '添加失败，请重试';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>添加管理员</title>
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
        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        input:focus {
            outline: none;
            border-color: #667eea;
        }
        .btn {
            padding: 12px 24px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            background: #5568d3;
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-secondary:hover {
            background: #5a6268;
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
        .back-link:hover {
            text-decoration: underline;
        }
        .tip {
            background: #e7f3ff;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #0066cc;
        }
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include '../common/sidebar.php'; ?>
        <div class="main-content">
            <div class="container">
                <a href="list.php" class="back-link">← 返回管理员列表</a>
                <h1>添加管理员</h1>

                <?php if ($error): ?>
                    <div class="error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <div class="tip">
                    ℹ️ 提示：新管理员将拥有所有管理权限。用户名只能包含字母、数字和下划线。
                </div>

                <form method="POST">
                    <div class="form-group">
                        <label>用户名 *</label>
                        <input type="text" name="username" required placeholder="请输入用户名（2-50个字符）" maxlength="50" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>密码 *</label>
                        <input type="password" name="password" required placeholder="请输入密码（至少6位）" minlength="6">
                    </div>

                    <div class="form-group">
                        <label>确认密码 *</label>
                        <input type="password" name="confirm_password" required placeholder="请再次输入密码" minlength="6">
                    </div>

                    <div class="form-group">
                        <label>真实姓名</label>
                        <input type="text" name="real_name" placeholder="选填" maxlength="50" value="<?php echo htmlspecialchars($_POST['real_name'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>邮箱</label>
                        <input type="email" name="email" placeholder="选填" maxlength="100" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn">添加管理员</button>
                        <a href="list.php" class="btn btn-secondary">取消</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
