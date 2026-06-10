<?php
// 测试文件 - 检查PHP是否正常工作
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>";
echo "<html><head><meta charset='UTF-8'><title>测试</title></head><body>";
echo "<h1>PHP 测试页面</h1>";
echo "<p>当前时间: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>PHP 版本: " . phpversion() . "</p>";

// 测试数据库连接
try {
    require_once '../../common/db.php';
    echo "<p style='color: green;'>✓ 数据库连接成功</p>";

    global $pdo;
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM agreements");
    $result = $stmt->fetch();
    echo "<p>agreements 表记录数: " . $result['cnt'] . "</p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>✗ 数据库连接失败: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// 测试 session
try {
    require_once '../common/session.php';
    echo "<p style='color: green;'>✓ Session 文件加载成功</p>";
    echo "<p>当前登录状态: " . (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] ? '已登录' : '未登录') . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Session 加载失败: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</body></html>";
?>
