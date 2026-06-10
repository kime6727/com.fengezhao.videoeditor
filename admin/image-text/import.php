<?php
/**
 * CSV导入图片+文案素材
 */
require_once '../common/session.php';
require_once '../common/excel_helper.php';

checkAdminLogin();

$action = $_GET['action'] ?? '';

// 下载模板
if ($action === 'template') {
    createImageTextTemplate();
    exit;
}

$error = '';
$success = '';
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = '文件上传失败';
    } elseif (!in_array(strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)), ['csv'])) {
        $error = '只支持CSV文件（.csv）';
    } else {
        $uploadPath = sys_get_temp_dir() . '/' . uniqid() . '_' . $file['name'];
        if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
            $result = importImageTexts($uploadPath);
            unlink($uploadPath);

            if (isset($result['error'])) {
                $error = $result['error'];
            } else {
                $success = "导入成功！成功：{$result['success_count']} 条，失败：{$result['error_count']} 条";
                if (!empty($result['errors'])) {
                    $error = "部分导入失败：\n" . implode("\n", array_slice($result['errors'], 0, 10));
                    if (count($result['errors']) > 10) {
                        $error .= "\n...还有" . (count($result['errors']) - 10) . "条错误";
                    }
                }
            }
        } else {
            $error = '文件保存失败';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>批量导入图片+文案素材（CSV格式）</title>
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
        input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 2px dashed #ddd;
            border-radius: 4px;
        }
        button {
            padding: 12px 24px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
        }
        button:hover {
            background: #5568d3;
        }
        .btn-template {
            background: #27ae60;
        }
        .btn-template:hover {
            background: #229954;
        }
        .error {
            background: #fee;
            color: #c33;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            white-space: pre-line;
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
        .info-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .info-box h3 {
            margin-bottom: 10px;
            color: #667eea;
        }
        .info-box ul {
            margin-left: 20px;
        }
        .info-box li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="list.php" class="back-link">← 返回列表</a>
        <h1>批量导入图片+文案素材</h1>

        <div class="info-box">
            <h3>使用说明：</h3>
            <ul>
                <li>1. 点击"下载模板"按钮下载CSV模板文件</li>
                <li>2. 使用Excel或文本编辑器打开CSV文件，按照模板格式填写数据</li>
                <li>3. 图片URL多个用 | 分隔（最多9个）</li>
                <li>4. 文案内容多个用 | 分隔（最多30个）</li>
                <li>5. 选择填写好的CSV文件进行上传</li>
            </ul>
            <p style="margin-top: 10px; color: #666;">
                <strong>注意：</strong>
                <br>- CSV格式更简单，无需安装额外软件库
                <br>- 分类ID需要先在分类管理中查看，多个分类用逗号分隔
                <br>- 如果使用Excel编辑，保存时请选择"CSV UTF-8"格式
            </p>
        </div>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>选择CSV文件</label>
                <input type="file" name="excel_file" accept=".csv" required>
            </div>

            <button type="submit">开始导入</button>
            <a href="?action=template" class="btn-template" style="text-decoration: none; display: inline-block; padding: 12px 24px; background: #27ae60; color: white; border-radius: 4px;">下载模板</a>
        </form>
    </div>
</body>
</html>
