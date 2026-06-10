<?php
/**
 * 获取图片+文案列表（用于上下滑动）
 */

require_once '../../common/db.php';
require_once '../../common/functions.php';
require_once '../../common/response.php';

setCorsHeaders();
header('Content-Type: application/json; charset=utf-8');

$userId = $_GET['user_id'] ?? '';
$page = intval($_GET['page'] ?? 1);
$pageSize = intval($_GET['page_size'] ?? 20);

global $pdo;

// 获取隐藏的素材ID
$hiddenCondition = '';
$hiddenParams = [];
if ($userId) {
    $stmt = $pdo->prepare("SELECT `material_id` FROM `user_hidden_materials` 
                           WHERE `user_id` = ? AND `material_type` = 2");
    $stmt->execute([$userId]);
    $hiddenIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($hiddenIds)) {
        $placeholders = implode(',', array_fill(0, count($hiddenIds), '?'));
        $hiddenCondition = " AND itm.material_id NOT IN ($placeholders)";
        $hiddenParams = $hiddenIds;
    }
}

$sql = "SELECT itm.material_id, itm.download_count, itm.like_count, itm.created_at
        FROM `image_text_materials` itm
        INNER JOIN `category_relations` cr ON itm.material_id = cr.material_id AND cr.material_type = 2
        INNER JOIN `categories` c ON cr.category_id = c.category_id
        WHERE itm.status = 1 AND c.status = 1
        $hiddenCondition
        GROUP BY itm.material_id
        ORDER BY itm.created_at DESC
        LIMIT ? OFFSET ?";

$params = array_merge($hiddenParams, [$pageSize, ($page - 1) * $pageSize]);
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取每个素材的图片和文案
foreach ($materials as &$material) {
    // 获取图片列表
    $stmt = $pdo->prepare("SELECT `image_url`, `sort` 
                          FROM `image_text_images` 
                          WHERE `material_id` = ? 
                          ORDER BY `sort` ASC");
    $stmt->execute([$material['material_id']]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 对图片URL进行绝对路径转换
    foreach ($images as &$img) {
        $img['image_url'] = absoluteMediaUrl($img['image_url']);
    }
    $material['images'] = $images;
    
    // 获取文案列表
    $stmt = $pdo->prepare("SELECT `id`, `content`, `sort` 
                          FROM `image_text_contents` 
                          WHERE `material_id` = ? 
                          ORDER BY `sort` ASC");
    $stmt->execute([$material['material_id']]);
    $contents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $material['contents'] = $contents;
    
    // 默认返回排序最靠前的一条文案（客户端可以请求随机文案接口刷新）
    if (!empty($contents)) {
        $firstContent = $contents[0];
        $material['current_content'] = $firstContent['content'];
        $material['content_id'] = $firstContent['id'];
    }
    $material['all_contents'] = array_column($contents, 'content');
    
    // 检查是否收藏
    $isFavorite = false;
    if ($userId) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `user_favorites` 
                               WHERE `user_id` = ? AND `material_id` = ? AND `material_type` = 2");
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
