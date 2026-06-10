<?php
/**
 * 分类数据恢复脚本
 * 访问此脚本恢复默认分类数据
 */

require_once __DIR__ . '/common/session.php';
require_once __DIR__ . '/../common/db.php';
require_once __DIR__ . '/../common/functions.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>";
echo "<html><head><meta charset='UTF-8'><title>分类数据恢复</title>";
echo "<style>body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;padding:30px;background:#f5f5f5;}";
echo ".container{max-width:800px;margin:0 auto;background:white;padding:30px;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,0.1);}";
echo ".success{color:#16a34a;background:#dcfce7;padding:12px 16px;border-radius:4px;margin:15px 0;}";
echo ".error{color:#dc2626;background:#fee2e2;padding:12px 16px;border-radius:4px;margin:15px 0;}";
echo ".info{color:#2563eb;background:#dbeafe;padding:12px 16px;border-radius:4px;margin:15px 0;}";
echo ".warning{color:#d97706;background:#fef3c7;padding:12px 16px;border-radius:4px;margin:15px 0;}";
echo "table{width:100%;border-collapse:collapse;margin:20px 0;}";
echo "th,td{padding:12px;text-align:left;border-bottom:1px solid #e5e7eb;}";
echo "th{background:#f9fafb;font-weight:600;}";
echo "h1{color:#1f2937;margin-bottom:20px;}";
echo "h2{color:#374151;margin:15px 0 10px;}";
echo ".btn{display:inline-block;padding:10px 20px;background:#3b82f6;color:white;text-decoration:none;border-radius:6px;margin-right:10px;}";
echo ".btn:hover{background:#2563eb;}";
echo ".btn-danger{background:#ef4444;}";
echo ".btn-danger:hover{background:#dc2626;}</style></head><body>";

echo "<div class='container'>";

// 检查是否已登录
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo "<h1>⚠️ 需要登录</h1>";
    echo "<p class='error'>请先访问 <a href='/admin/'>后台登录页面</a> 登录后再执行此脚本</p>";
    echo "</div></body></html>";
    exit;
}

// 检查是否有恢复请求
if (isset($_GET['restore']) && $_GET['restore'] === 'confirm') {
    try {
        global $pdo;

        echo "<h1>🔄 开始恢复分类数据...</h1>";

        // 默认分类数据
        $defaultCategories = [
            // 单视频类型
            ['name' => '豪车', 'type' => 1, 'sort' => 1],
            ['name' => '奢侈品', 'type' => 1, 'sort' => 2],
            ['name' => '旅行', 'type' => 1, 'sort' => 3],
            ['name' => '美食', 'type' => 1, 'sort' => 4],
            ['name' => '宠物', 'type' => 1, 'sort' => 5],

            // 图片+文案类型
            ['name' => '励志语录', 'type' => 2, 'sort' => 1],
            ['name' => '搞笑段子', 'type' => 2, 'sort' => 2],
            ['name' => '情感文案', 'type' => 2, 'sort' => 3],
            ['name' => '早安晚安', 'type' => 2, 'sort' => 4],

            // 视频+文案类型
            ['name' => '热门视频', 'type' => 3, 'sort' => 1],
            ['name' => '创意短片', 'type' => 3, 'sort' => 2],
            ['name' => '生活Vlog', 'type' => 3, 'sort' => 3],

            // 纯文案类型
            ['name' => '金句名言', 'type' => 4, 'sort' => 1],
            ['name' => '正能量', 'type' => 4, 'sort' => 2],
            ['name' => '情感语录', 'type' => 4, 'sort' => 3],
        ];

        $successCount = 0;
        $existsCount = 0;

        echo "<p class='info'>准备恢复 " . count($defaultCategories) . " 个分类...</p>";

        $stmt = $pdo->prepare("INSERT INTO `categories` (`category_id`, `name`, `type`, `sort`, `is_top`, `status`, `created_at`)
                               VALUES (?, ?, ?, ?, 0, 1, NOW())");

        foreach ($defaultCategories as $cat) {
            // 检查是否已存在同名同类型的分类
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM `categories` WHERE `name` = ? AND `type` = ?");
            $checkStmt->execute([$cat['name'], $cat['type']]);
            $exists = $checkStmt->fetchColumn();

            if ($exists > 0) {
                echo "<p class='warning'>⏭️  跳过已存在的分类: {$cat['name']}</p>";
                $existsCount++;
            } else {
                // 生成短ID（001、002、003...）
                $categoryId = generateIncrementalCategoryId();
                $stmt->execute([$categoryId, $cat['name'], $cat['type'], $cat['sort']]);
                $successCount++;
                echo "<p class='success'>✓ 添加分类: {$cat['name']} (ID: {$categoryId})</p>";
            }
        }

        echo "<hr>";
        echo "<h2>✅ 恢复完成！</h2>";
        echo "<p class='success'>成功添加 {$successCount} 个分类，跳过 {$existsCount} 个已存在分类</p>";

        echo "<p class='info'><a href='list.php' class='btn'>查看分类列表</a></p>";

    } catch (Exception $e) {
        echo "<hr>";
        echo "<h2 class='error'>❌ 恢复失败</h2>";
        echo "<p class='error'>错误信息: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
} elseif (isset($_GET['clear']) && $_GET['clear'] === 'confirm') {
    try {
        global $pdo;

        echo "<h1>🗑️ 清空分类数据...</h1>";

        // 清空分类关联表
        $pdo->exec("DELETE FROM `category_relations`");
        echo "<p class='warning'>✓ 已清空分类关联表</p>";

        // 清空分类表
        $deleteCount = $pdo->exec("DELETE FROM `categories`");
        echo "<p class='warning'>✓ 已清空分类表，共删除 {$deleteCount} 个分类</p>";

        echo "<hr>";
        echo "<h2 class='success'>✅ 清空完成！</h2>";

        echo "<p class='info'><a href='restore_categories.php' class='btn'>返回恢复页面</a></p>";

    } catch (Exception $e) {
        echo "<hr>";
        echo "<h2 class='error'>❌ 清空失败</h2>";
        echo "<p class='error'>错误信息: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else {
    // 显示当前分类状态
    try {
        global $pdo;

        echo "<h1>📊 分类数据状态</h1>";

        $stmt = $pdo->query("SELECT COUNT(*) FROM `categories`");
        $totalCategories = $stmt->fetchColumn();

        echo "<p class='info'>当前数据库中共有 <strong>{$totalCategories}</strong> 个分类</p>";

        if ($totalCategories > 0) {
            echo "<h2>现有分类列表：</h2>";
            echo "<table>";
            echo "<thead><tr><th>分类ID</th><th>分类名称</th><th>类型</th><th>排序</th><th>状态</th></tr></thead>";
            echo "<tbody>";

            $stmt = $pdo->query("SELECT `category_id`, `name`, `type`, `sort`, `status`
                                FROM `categories`
                                ORDER BY `type` ASC, `sort` ASC, `created_at` ASC");

            $typeNames = [1 => '单视频', 2 => '图片+文案', 3 => '视频+文案', 4 => '纯文案'];

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['category_id']) . "</td>";
                echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                echo "<td>" . htmlspecialchars($typeNames[$row['type']] ?? $row['type']) . "</td>";
                echo "<td>" . htmlspecialchars($row['sort']) . "</td>";
                echo "<td>" . ($row['status'] ? '<span style="color:green">启用</span>' : '<span style="color:red">禁用</span>') . "</td>";
                echo "</tr>";
            }

            echo "</tbody></table>";
        }

        echo "<hr>";
        echo "<h2>操作选项：</h2>";

        if ($totalCategories > 0) {
            echo "<p class='warning'>⚠️ 检测到已有分类数据，恢复操作将跳过已存在的分类。</p>";
            echo "<p><a href='?restore=confirm' class='btn'>📥 恢复默认分类数据</a></p>";
            echo "<p><a href='?clear=confirm' class='btn btn-danger'>🗑️ 清空所有分类</a></p>";
        } else {
            echo "<p class='info'>ℹ️ 数据库中没有分类数据，可以开始恢复。</p>";
            echo "<p><a href='?restore=confirm' class='btn'>📥 恢复默认分类数据</a></p>";
        }

        echo "<hr>";
        echo "<p class='info'><a href='list.php' class='btn'>返回分类列表</a></p>";

    } catch (Exception $e) {
        echo "<h2 class='error'>❌ 查询失败</h2>";
        echo "<p class='error'>错误信息: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}

echo "</div></body></html>";
?>
