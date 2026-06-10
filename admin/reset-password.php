<?php
/**
 * 密码重置脚本
 * 访问此文件来重置 admin 用户的密码
 */

require_once __DIR__ . '/common/session.php';
require_once __DIR__ . '/../common/db.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($newPassword)) {
        $message = '请输入新密码';
    } elseif (strlen($newPassword) < 6) {
        $message = '密码长度不能少于6位';
    } elseif ($newPassword !== $confirmPassword) {
        $message = '两次输入的密码不一致';
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        global $pdo;
        $adminConfigFile = __DIR__ . '/common/admin_config.php';
        $success = false;

        try {
            $checkTable = $pdo->query("SHOW TABLES LIKE 'admins'");
            $tableExists = $checkTable && $checkTable->rowCount() > 0;

            if ($tableExists) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM `admins` WHERE `username` = 'admin'");
                $stmt->execute();
                if ($stmt->fetchColumn() > 0) {
                    $stmt = $pdo->prepare("UPDATE `admins` SET `password` = ?, `updated_at` = NOW() WHERE `username` = 'admin'");
                    $success = $stmt->execute([$hashedPassword]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO `admins` (`username`, `password`, `status`) VALUES ('admin', ?, 1)");
                    $success = $stmt->execute([$hashedPassword]);
                }
            }
        } catch (Exception $e) {
            $message = '数据库错误: ' . $e->getMessage();
        }

        if (file_exists($adminConfigFile)) {
            $config = [
                'admin' => $hashedPassword,
            ];
            $content = "<?php\n";
            $content .= "/**\n";
            $content .= " * 管理员配置文件（自动生成，请勿手动编辑）\n";
            $content .= " */\n\n";
            $content .= "return [\n";
            $content .= "    'admin' => '" . $hashedPassword . "',\n";
            $content .= "];\n";
            file_put_contents($adminConfigFile, $content);
        }

        if ($success || !isset($message)) {
            $message = '密码重置成功！请使用新密码登录';
            echo "<script>alert('密码重置成功！');</script>";
        }
    }
}

$adminConfigFile = __DIR__ . '/common/admin_config.php';
if (file_exists($adminConfigFile)) {
    $config = include $adminConfigFile;
    $configHash = $config['admin'] ?? null;
} else {
    $configHash = null;
}

global $pdo;
$dbHash = null;
try {
    $checkTable = $pdo->query("SHOW TABLES LIKE 'admins'");
    $tableExists = $checkTable && $checkTable->rowCount() > 0;
    if ($tableExists) {
        $stmt = $pdo->query("SELECT `password` FROM `admins` WHERE `username` = 'admin'");
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($admin) {
            $dbHash = $admin['password'];
        }
    }
} catch (Exception $e) {
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>密码重置 - 好素材后台</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            max-width: 500px;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 { margin-bottom: 20px; color: #333; }
        .info {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            color: #0066cc;
        }
        .warning {
            background: #fff3cd;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            color: #856404;
        }
        .error { background: #fee; color: #c33; padding: 12px; border-radius: 4px; margin-bottom: 20px; }
        .success { background: #efe; color: #3c3; padding: 12px; border-radius: 4px; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 600; color: #333; }
        input[type="password"] {
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
        button:hover { background: #5568d3; }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #667eea;
            text-decoration: none;
        }
        .hash-info {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            font-size: 12px;
            word-break: break-all;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>密码重置</h1>

        <?php if ($message): ?>
            <div class="<?php echo strpos($message, '成功') !== false ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="info">
            <strong>当前密码状态：</strong><br>
            配置文件: <?php echo $configHash ? '有记录' : '无记录'; ?><br>
            数据库: <?php echo $dbHash ? '有记录' : '无记录'; ?>
        </div>

        <div class="warning">
            ⚠️ 重要：请重新设置一个不同于旧密码的新密码。这将同时更新数据库和配置文件。
        </div>

        <form method="POST">
            <div class="form-group">
                <label>新密码 *</label>
                <input type="password" name="password" required placeholder="请输入新密码（至少6位）" minlength="6">
            </div>

            <div class="form-group">
                <label>确认新密码 *</label>
                <input type="password" name="confirm_password" required placeholder="请再次输入新密码" minlength="6">
            </div>

            <button type="submit" name="reset_password">重置密码</button>
        </form>

        <a href="index.php" class="back-link">← 返回后台首页</a>
    </div>
</body>
</html>
