<?php
/**
 * 获取视频详情
 */

require_once '../../common/db.php';
require_once '../../common/functions.php';
require_once '../../common/response.php';

setCorsHeaders();
header('Content-Type: application/json; charset=utf-8');

$materialId = $_GET['material_id'] ?? '';
$userId = $_GET['user_id'] ?? '';

if (empty($materialId)) {
    echo jsonResponse(400, '素材ID不能为空', null);
    exit;
}

global $pdo;

// 检查是否隐藏
if ($userId && isMaterialHidden($userId, $materialId, MATERIAL_TYPE_VIDEO)) {
    echo jsonResponse(403, '该素材已对您隐藏', null);
    exit;
}

// 获取视频信息（关联用户表获取发布者）
$stmt = $pdo->prepare("SELECT vm.`material_id`, vm.`name`, vm.`video_url`, vm.`thumbnail_url`, 
                              vm.`download_count`, vm.`like_count`, vm.`created_at`,
                              u.`user_id` as author_id, u.`username` as author_name, u.`avatar` as author_avatar
                       FROM `video_materials` vm
                       LEFT JOIN `users` u ON vm.`author_id` = u.`user_id`
                       WHERE vm.`material_id` = ? AND vm.`status` = 1");
$stmt->execute([$materialId]);
$video = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$video) {
    echo jsonResponse(404, '视频不存在', null);
    exit;
}

// 获取分类
$stmt = $pdo->prepare("SELECT c.category_id, c.name 
                      FROM `categories` c
                      INNER JOIN `category_relations` cr ON c.category_id = cr.category_id
                      WHERE cr.material_id = ? AND cr.material_type = 1
                      ORDER BY c.is_top DESC, c.sort ASC");
$stmt->execute([$materialId]);
$video['categories'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 检查是否收藏
$isFavorite = false;
if ($userId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `user_favorites` 
                           WHERE `user_id` = ? AND `material_id` = ? AND `material_type` = 1");
    $stmt->execute([$userId, $materialId]);
    $isFavorite = $stmt->fetchColumn() > 0;
}
$video['is_favorite'] = $isFavorite;

$video['video_url'] = absoluteMediaUrl($video['video_url'] ?? null);
$video['thumbnail_url'] = absoluteMediaUrl($video['thumbnail_url'] ?? null);
$video['author_avatar'] = absoluteMediaUrl($video['author_avatar'] ?? null);

echo jsonResponse(200, '获取成功', $video);
