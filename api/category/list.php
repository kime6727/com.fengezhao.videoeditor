<?php
/**
 * 获取分类列表（按类型，支持排序和置顶）
 */

require_once '../../common/db.php';
require_once '../../common/functions.php';
require_once '../../common/response.php';

setCorsHeaders();
header('Content-Type: application/json; charset=utf-8');

$type = intval($_GET['type'] ?? 0); // 素材类型 1-单视频 2-图片+文案 3-视频+文案 4-纯文案

global $pdo;

$sql = "SELECT `category_id`, `name`, `type`, `sort`, `is_top`
        FROM `categories`
        WHERE `status` = 1";

$params = [];

if ($type > 0) {
    $sql .= " AND `type` = ?";
    $params[] = $type;
}

$sql .= " ORDER BY `is_top` DESC, `sort` ASC, `created_at` ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo jsonResponse(200, '获取成功', [
    'list' => $categories
]);