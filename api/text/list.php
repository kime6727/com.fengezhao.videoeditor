<?php
/**
 * 获取纯文案列表（支持分类筛选）
 */

require_once '../../common/db.php';
require_once '../../common/functions.php';
require_once '../../common/response.php';

setCorsHeaders();
header('Content-Type: application/json; charset=utf-8');

$userId = $_GET['user_id'] ?? '';
$categoryId = $_GET['category_id'] ?? null;
$page = intval($_GET['page'] ?? 1);
$pageSize = intval($_GET['page_size'] ?? 20);

global $pdo;

// 获取隐藏的素材ID
$hiddenCondition = '';
$hiddenParams = [];
if ($userId) {
    $stmt = $pdo->prepare("SELECT `material_id` FROM `user_hidden_materials`
                           WHERE `user_id` = ? AND `material_type` = 4");
    $stmt->execute([$userId]);
    $hiddenIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($hiddenIds)) {
        $placeholders = implode(',', array_fill(0, count($hiddenIds), '?'));
        $hiddenCondition = " AND tm.material_id NOT IN ($placeholders)";
        $hiddenParams = $hiddenIds;
    }
}

$sql = "SELECT tm.material_id, tm.content, tm.copy_count, tm.like_count, tm.created_at
        FROM `text_materials` tm";
$params = [];

if ($categoryId) {
    $sql .= " WHERE tm.status = 1
              AND tm.material_id IN (
                SELECT `material_id` FROM `category_relations`
                WHERE `category_id` = ? AND `material_type` = 4
              )";
    $params[] = $categoryId;
} else {
    $sql .= " INNER JOIN `category_relations` cr
              ON tm.material_id = cr.material_id
              AND cr.material_type = 4
              INNER JOIN `categories` c ON cr.category_id = c.category_id
              WHERE tm.status = 1 AND c.status = 1";
}

$sql .= $hiddenCondition;
$params = array_merge($params, $hiddenParams);

$sql .= " ORDER BY tm.created_at DESC LIMIT ? OFFSET ?";
$params[] = $pageSize;
$params[] = ($page - 1) * $pageSize;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取每个文案的分类和收藏状态
foreach ($materials as &$material) {
    // 确保基本字段存在
    $material['content'] = $material['content'] ?? '';
    $material['copy_count'] = $material['copy_count'] ?? 0;
    $material['like_count'] = $material['like_count'] ?? 0;
    $material['created_at'] = $material['created_at'] ?? date('Y-m-d H:i:s');

    // 获取分类
    $stmt = $pdo->prepare("SELECT c.category_id, c.name
                          FROM `categories` c
                          INNER JOIN `category_relations` cr ON c.category_id = cr.category_id
                          WHERE cr.material_id = ? AND cr.material_type = 4
                          ORDER BY c.is_top DESC, c.sort ASC");
    $stmt->execute([$material['material_id']]);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $material['categories'] = $categories ? $categories : [];

    // 检查是否收藏
    $isFavorite = false;
    if ($userId) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `user_favorites`
                               WHERE `user_id` = ? AND `material_id` = ? AND `material_type` = 4");
        $stmt->execute([$userId, $material['material_id']]);
        $isFavorite = $stmt->fetchColumn() > 0;
    }
    $material['is_favorite'] = $isFavorite;
}

echo jsonResponse(200, '获取成功', [
    'list' => $materials,
    'page' => $page,
    'page_size' => $pageSize,
    'has_more' => count($materials) >= $pageSize
]);
