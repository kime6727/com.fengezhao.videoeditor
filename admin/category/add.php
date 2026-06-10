<?php
/**
 * 添加分类
 */
require_once '../common/session.php';
require_once '../../common/db.php';
require_once '../../common/functions.php';

checkAdminLogin();

global $pdo;

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $names = trim($_POST['names'] ?? '');
    $type = intval($_POST['type'] ?? 0);
    $sort = intval($_POST['sort'] ?? 0);
    $isTop = intval($_POST['is_top'] ?? 0);
    $status = intval($_POST['status'] ?? 1);

    if (empty($names) || $type == 0) {
        $error = '分类名称和类型不能为空';
    } else {
        try {
            // 按换行符或逗号分割名称
            $nameArray = preg_split('/[\r\n,，]+/', $names);
            $nameArray = array_filter(array_map('trim', $nameArray));

            if (empty($nameArray)) {
                $error = '请输入有效的分类名称';
            } else {
                $successCount = 0;
                foreach ($nameArray as $name) {
                    $categoryId = generateUniqueIdWithCheck('categories', 'category_id');

                    $stmt = $pdo->prepare("INSERT INTO `categories`
                                          (`category_id`, `name`, `type`, `sort`, `is_top`, `status`)
                                          VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$categoryId, $name, $type, $sort, $isTop, $status]);
                    $successCount++;
                }

                $success = "成功添加 {$successCount} 个分类！";
            }

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
    <title>批量添加分类</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            padding: 20px;
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
        input[type="text"], input[type="number"], select, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        textarea {
            min-height: 120px;
            font-family: inherit;
            resize: vertical;
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
        <h1>批量添加分类</h1>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>分类名称（支持批量） *</label>
                <textarea name="names" required placeholder="每行一个分类名称，或用逗号分隔\n例如：\n豪车\n奢侈品\n旅行"><?php echo htmlspecialchars($_POST['names'] ?? ''); ?></textarea>
                <small style="color: #666; font-size: 12px; display: block; margin-top: 5px;">支持批量添加，每行一个分类或用逗号分隔</small>
            </div>

            <div class="form-group">
                <label>素材类型 *</label>
                <select name="type" required>
                    <option value="0">请选择</option>
                    <option value="1" <?php echo (($_POST['type'] ?? 0) == 1) ? 'selected' : ''; ?>>单视频</option>
                    <option value="2" <?php echo (($_POST['type'] ?? 0) == 2) ? 'selected' : ''; ?>>图片+文案</option>
                    <option value="3" <?php echo (($_POST['type'] ?? 0) == 3) ? 'selected' : ''; ?>>视频+文案</option>
                    <option value="4" <?php echo (($_POST['type'] ?? 0) == 4) ? 'selected' : ''; ?>>纯文案</option>
                </select>
            </div>

            <div class="form-group">
                <label>排序（数字越小越靠前）</label>
                <input type="number" name="sort" value="<?php echo $_POST['sort'] ?? 0; ?>" min="0">
            </div>

            <div class="form-group">
                <label>是否置顶</label>
                <select name="is_top">
                    <option value="0" <?php echo (($_POST['is_top'] ?? 0) == 0) ? 'selected' : ''; ?>>否</option>
                    <option value="1" <?php echo (($_POST['is_top'] ?? 0) == 1) ? 'selected' : ''; ?>>是</option>
                </select>
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
