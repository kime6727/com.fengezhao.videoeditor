<?php
/**
 * 获取视频+文案列表（用于上下滑动）
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
                           WHERE `user_id` = ? AND `material_type` = 3");
    $stmt->execute([$userId]);
    $hiddenIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($hiddenIds)) {
        $placeholders = implode(',', array_fill(0, count($hiddenIds), '?'));
        $hiddenCondition = " AND vtm.material_id NOT IN ($placeholders)";
        $hiddenParams = $hiddenIds;
    }
}

$sql = "SELECT vtm.material_id, vtm.video_url, vtm.thumbnail_url,
               vtm.download_count, vtm.like_count, vtm.created_at
        FROM `video_text_materials` vtm
        INNER JOIN `category_relations` cr ON vtm.material_id = cr.material_id AND cr.material_type = 3
        INNER JOIN `categories` c ON cr.category_id = c.category_id
        WHERE vtm.status = 1 AND c.status = 1
        $hiddenCondition
        GROUP BY vtm.material_id
        ORDER BY RAND()
        LIMIT ? OFFSET ?";

$params = array_merge($hiddenParams, [$pageSize, ($page - 1) * $pageSize]);
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取每个素材的文案
foreach ($materials as &$material) {
    // 对URL进行绝对路径转换
    if (isset($material['video_url'])) {
        $material['video_url'] = absoluteMediaUrl($material['video_url']);
    }
    if (isset($material['thumbnail_url'])) {
        $material['thumbnail_url'] = absoluteMediaUrl($material['thumbnail_url']);
    }
    
    // 获取文案列表
    $stmt = $pdo->prepare("SELECT `id`, `content`, `sort`
                          FROM `video_text_contents`
                          WHERE `material_id` = ?
                          ORDER BY `sort` ASC");
    $stmt->execute([$material['material_id']]);
    $contents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $material['contents'] = $contents;

    // 默认返回排序最靠前的一条文案（客户端可调用随机接口刷新）
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
                               WHERE `user_id` = ? AND `material_id` = ? AND `material_type` = 3");
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
