<?php
/**
 * 全局搜索（搜索所有类型素材）
 * 改造要点：
 * 1. 添加认证支持（Token 或 device_id）
 * 2. 支持按素材类型筛选（type 参数）
 * 3. 统一返回格式，增加收藏/点赞状态
 * 4. 优化 N+1 查询
 */

require_once '../../common/db.php';
require_once '../../common/functions.php';
require_once '../../common/response.php';
require_once '../../common/auth.php';

setCorsHeaders();
header('Content-Type: application/json; charset=utf-8');

// 认证用户（可选，搜索不需要强制认证）
$user = authenticateUser();
$userId = $user ? $user['user_id'] : '';

$keyword = $_GET['keyword'] ?? '';
$type = intval($_GET['type'] ?? 0); // 0=全部, 1=视频, 2=图文, 3=视频+文案, 4=纯文案
$page = intval($_GET['page'] ?? 1);
$pageSize = intval($_GET['page_size'] ?? 20);

if (empty($keyword)) {
    echo jsonResponse(400, '搜索关键词不能为空', null);
    exit;
}

global $pdo;

$results = [];

// 获取隐藏的素材ID（用于过滤）
$hiddenIds = [];
if ($userId) {
    $stmt = $pdo->prepare("SELECT `material_id`, `material_type` FROM `user_hidden_materials` WHERE `user_id` = ?");
    $stmt->execute([$userId]);
    $hidden = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($hidden as $item) {
        $hiddenIds[$item['material_type']][] = $item['material_id'];
    }
}

// 批量获取收藏状态（优化 N+1）
$userFavorites = [];
if ($userId) {
    $stmt = $pdo->prepare("SELECT `material_id`, `material_type` FROM `user_favorites` WHERE `user_id` = ?");
    $stmt->execute([$userId]);
    $favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($favorites as $fav) {
        $userFavorites[$fav['material_id'] . '_' . $fav['material_type']] = true;
    }
}

// 批量获取点赞状态
$userLikes = [];
if ($userId) {
    $stmt = $pdo->query("SHOW TABLES LIKE 'user_likes'");
    if ($stmt->fetchColumn() !== false) {
        $stmt = $pdo->prepare("SELECT `material_id`, `material_type` FROM `user_likes` WHERE `user_id` = ?");
        $stmt->execute([$userId]);
        $likes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($likes as $like) {
            $userLikes[$like['material_id'] . '_' . $like['material_type']] = true;
        }
    }
}

function buildHiddenCondition($type, $hiddenIds) {
    if (!isset($hiddenIds[$type]) || empty($hiddenIds[$type])) {
        return ['', []];
    }
    $placeholders = implode(',', array_fill(0, count($hiddenIds[$type]), '?'));
    return [" AND material_id NOT IN ($placeholders)", $hiddenIds[$type]];
}

// 搜索单视频（type=1 或 type=0 全部）
if ($type === 0 || $type === 1) {
    $hiddenCond = buildHiddenCondition(1, $hiddenIds);

    $sql = "SELECT `material_id`, `name`, `video_url`, `thumbnail_url`, `download_count`, `like_count`, 1 as `type`
            FROM `video_materials`
            WHERE `status` = 1 AND (`name` LIKE ?)" . $hiddenCond[0] .
            " ORDER BY `created_at` DESC LIMIT ? OFFSET ?";

    $params = array_merge(['%' . $keyword . '%'], $hiddenCond[1], [$pageSize, ($page - 1) * $pageSize]);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($videos as &$video) {
        $video['video_url'] = absoluteMediaUrl($video['video_url'] ?? null);
        $video['thumbnail_url'] = absoluteMediaUrl($video['thumbnail_url'] ?? null);
        $video['is_favorite'] = isset($userFavorites[$video['material_id'] . '_1']);
        $video['is_liked'] = isset($userLikes[$video['material_id'] . '_1']);
        $results[] = $video;
    }
}

// 搜索图片+文案（type=2 或 type=0 全部）
if ($type === 0 || $type === 2) {
    $hiddenCond = buildHiddenCondition(2, $hiddenIds);

    $sql = "SELECT DISTINCT itm.material_id, itm.download_count, itm.like_count, 2 as `type`
            FROM `image_text_materials` itm
            INNER JOIN `image_text_contents` itc ON itm.material_id = itc.material_id
            WHERE itm.status = 1 AND itc.content LIKE ?" . $hiddenCond[0] .
            " ORDER BY itm.created_at DESC LIMIT ? OFFSET ?";

    $params = array_merge(['%' . $keyword . '%'], $hiddenCond[1], [$pageSize, ($page - 1) * $pageSize]);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $imageTexts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($imageTexts as $item) {
        $item['is_favorite'] = isset($userFavorites[$item['material_id'] . '_2']);
        $item['is_liked'] = isset($userLikes[$item['material_id'] . '_2']);
        $results[] = $item;
    }
}

// 搜索视频+文案（type=3 或 type=0 全部）
if ($type === 0 || $type === 3) {
    $hiddenCond = buildHiddenCondition(3, $hiddenIds);

    $sql = "SELECT DISTINCT vtm.material_id, vtm.video_url, vtm.thumbnail_url, vtm.download_count, vtm.like_count, 3 as `type`
            FROM `video_text_materials` vtm
            INNER JOIN `video_text_contents` vtc ON vtm.material_id = vtc.material_id
            WHERE vtm.status = 1 AND vtc.content LIKE ?" . $hiddenCond[0] .
            " ORDER BY vtm.created_at DESC LIMIT ? OFFSET ?";

    $params = array_merge(['%' . $keyword . '%'], $hiddenCond[1], [$pageSize, ($page - 1) * $pageSize]);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $videoTexts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($videoTexts as &$item) {
        $item['video_url'] = absoluteMediaUrl($item['video_url'] ?? null);
        $item['thumbnail_url'] = absoluteMediaUrl($item['thumbnail_url'] ?? null);
        $item['is_favorite'] = isset($userFavorites[$item['material_id'] . '_3']);
        $item['is_liked'] = isset($userLikes[$item['material_id'] . '_3']);
        $results[] = $item;
    }
}

// 搜索纯文案（type=4 或 type=0 全部）
if ($type === 0 || $type === 4) {
    $hiddenCond = buildHiddenCondition(4, $hiddenIds);

    $sql = "SELECT `material_id`, `content`, `copy_count`, `like_count`, 4 as `type`
            FROM `text_materials`
            WHERE `status` = 1 AND `content` LIKE ?" . $hiddenCond[0] .
            " ORDER BY `created_at` DESC LIMIT ? OFFSET ?";

    $params = array_merge(['%' . $keyword . '%'], $hiddenCond[1], [$pageSize, ($page - 1) * $pageSize]);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $texts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($texts as $text) {
        $text['is_favorite'] = isset($userFavorites[$text['material_id'] . '_4']);
        $text['is_liked'] = isset($userLikes[$text['material_id'] . '_4']);
        $results[] = $text;
    }
}

echo jsonResponse(200, '搜索成功', [
    'list' => $results,
    'keyword' => $keyword,
    'type' => $type,
    'page' => $page,
    'page_size' => $pageSize,
    'total' => count($results),
    'has_more' => count($results) >= $pageSize
]);