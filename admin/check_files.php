<?php
/**
 * 文件检查脚本 - 用于诊断文件是否存在
 */
echo "<!DOCTYPE html>";
echo "<html><head><meta charset='UTF-8'><title>文件检查</title>";
echo "<style>body{font-family:monospace;padding:20px;} .ok{color:green;} .error{color:red;}</style>";
echo "</head><body>";
echo "<h1>文件检查</h1>";

$files = [
    __DIR__ . '/common/session.php' => 'common/session.php',
    __DIR__ . '/../common/db.php' => '../common/db.php',
    __DIR__ . '/../common/functions.php' => '../common/functions.php',
    __DIR__ . '/index.php' => 'index.php',
];

foreach ($files as $fullPath => $relativePath) {
    $exists = file_exists($fullPath);
    $status = $exists ? '<span class="ok">✓ 存在</span>' : '<span class="error">✗ 不存在</span>';
    echo "<p>{$relativePath}: {$status}</p>";
    if ($exists) {
        echo "<p style='margin-left:20px;color:#666;'>完整路径: {$fullPath}</p>";
    }
}

echo "<hr>";
echo "<h2>当前脚本路径</h2>";
echo "<p>__DIR__: " . __DIR__ . "</p>";
echo "<p>__FILE__: " . __FILE__ . "</p>";
echo "<p>SCRIPT_NAME: " . $_SERVER['SCRIPT_NAME'] . "</p>";

echo "</body></html>";
?>

