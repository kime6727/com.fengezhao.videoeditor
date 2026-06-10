<?php
/**
 * 批量创建用户（用于UGC发布者）
 */
require_once '../common/session.php';
require_once '../../common/db.php';
require_once '../../common/functions.php';

checkAdminLogin();

global $pdo;

$success = '';
$error = '';
$createdUsers = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $count = intval($_POST['count'] ?? 0);
    $prefix = trim($_POST['prefix'] ?? '用户');
    $avatarType = $_POST['avatar_type'] ?? 'random';

    if ($count < 1 || $count > 100) {
        $error = '每次最多批量创建100个用户';
    } else {
        $created = 0;
        $failed = 0;

        for ($i = 0; $i < $count; $i++) {
            $userId = generateUniqueUserId();
            $username = $prefix . '_' . generateRandomString(6);

            // 确保用户名唯一
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE `username` = ?");
            $stmt->execute([$username]);
            while ($stmt->fetchColumn() > 0) {
                $username = $prefix . '_' . generateRandomString(6);
                $stmt->execute([$username]);
            }

            $password = bin2hex(random_bytes(8));
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // 生成随机头像
            $avatar = null;
            if ($avatarType === 'random') {
                $avatar = generateRandomAvatar();
            }

            $sql = "INSERT INTO `users` (`user_id`, `username`, `password`, `avatar`, `user_type`, `platform`, `created_at`, `updated_at`)
                    VALUES (?, ?, ?, ?, 1, 'ios', NOW(), NOW())";

            $stmt = $pdo->prepare($sql);
            $result = $stmt->execute([$userId, $username, $hashedPassword, $avatar]);

            if ($result) {
                $created++;
                $createdUsers[] = [
                    'user_id' => $userId,
                    'username' => $username,
                    'avatar' => $avatar
                ];
            } else {
                $failed++;
            }
        }

        if ($created > 0) {
            $success = "成功创建 {$created} 个用户" . ($failed > 0 ? "，{$failed} 个失败" : '');
        } else {
            $error = '创建失败，请重试';
        }
    }
}

/**
 * 生成随机字符串
 */
function generateRandomString($length = 6) {
    $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $string = '';
    for ($i = 0; $i < $length; $i++) {
        $string .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $string;
}

/**
 * 生成随机头像URL（使用 DiceBear API）
 */
function generateRandomAvatar() {
    $styles = ['adventurer', 'avataaars', 'big-ears', 'bottts', 'fun-emoji', 'lorelei', 'notionists', 'open-peeps'];
    $style = $styles[array_rand($styles)];
    $seed = bin2hex(random_bytes(8));
    return "https://api.dicebear.com/7.x/{$style}/svg?seed={$seed}";
}

// 获取已创建的用户数量
$totalUsers = $pdo->query("SELECT COUNT(*) FROM `users` WHERE `platform` = 'ios' OR `platform` = 'android' OR `platform` = 'system'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>批量创建用户</title>
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
            margin-bottom: 10px;
            font-size: 24px;
        }
        .subtitle {
            color: #666;
            font-size: 14px;
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
        input[type="number"], input[type="text"], select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .hint {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
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
        .alert {
            padding: 12px 16px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .created-users {
            margin-top: 20px;
        }
        .created-users h3 {
            margin-bottom: 15px;
            font-size: 16px;
        }
        .user-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        .user-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        .user-card img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 10px;
        }
        .user-card .username {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 5px;
            word-break: break-all;
        }
        .user-card .user-id {
            font-size: 12px;
            color: #666;
            font-family: monospace;
        }
        .stats {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .stats strong {
            color: #1976d2;
            font-size: 24px;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include '../common/sidebar.php'; ?>
        <div class="main-content">
            <div class="container">
                <h1>批量创建用户</h1>
                <p class="subtitle">用于创建UGC内容发布者账户，每个账户可作为素材的发布者</p>

                <div class="stats">
                    当前系统总用户数：<strong><?php echo $totalUsers; ?></strong>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="count">创建数量</label>
                        <input type="number" id="count" name="count" min="1" max="100" value="10" required>
                        <p class="hint">每次最多创建100个用户</p>
                    </div>

                    <div class="form-group">
                        <label for="prefix">用户名前缀</label>
                        <input type="text" id="prefix" name="prefix" value="用户" required>
                        <p class="hint">用户名将格式为：前缀_随机字符，例如：用户_a3b5c7</p>
                    </div>

                    <div class="form-group">
                        <label for="avatar_type">头像设置</label>
                        <select id="avatar_type" name="avatar_type">
                            <option value="random">随机生成头像</option>
                            <option value="none">不设置头像</option>
                        </select>
                        <p class="hint">随机头像使用 DiceBear API 生成</p>
                    </div>

                    <button type="submit">开始批量创建</button>
                </form>

                <?php if (!empty($createdUsers)): ?>
                <div class="created-users">
                    <h3>本次创建的用户（<?php echo count($createdUsers); ?>个）</h3>
                    <div class="user-grid">
                        <?php foreach ($createdUsers as $user): ?>
                        <div class="user-card">
                            <?php if ($user['avatar']): ?>
                                <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="头像">
                            <?php else: ?>
                                <div style="width:60px;height:60px;border-radius:50%;background:#ddd;margin:0 auto 10px;display:flex;align-items:center;justify-content:center;color:#999;font-size:12px;">无头像</div>
                            <?php endif; ?>
                            <div class="username"><?php echo htmlspecialchars($user['username']); ?></div>
                            <div class="user-id"><?php echo htmlspecialchars($user['user_id']); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
