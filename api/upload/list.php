<?php
/**
 * 获取用户上传的素材列表
 */
require_once '../../common/db.php';
require_once '../../common/functions.php';
require_once '../../common/response.php';

setCorsHeaders();
header('Content-Type: application/json; charset=utf-8');

$userId = $_GET['user_id'] ?? '';
$materialType = intval($_GET['material_type'] ?? 0);
$page = intval($_GET['page'] ?? 1);
$pageSize = intval($_GET['page_size'] ?? 20);

if (empty($userId)) {
    echo jsonResponse(400, '用户ID不能为空', null);
    exit;
}

try {
    global $pdo;

    $result = [
        'list' => [],
        'page' => $page,
        'page_size' => $pageSize,
        'has_more' => false
    ];

    $offset = ($page - 1) * $pageSize;

    switch ($materialType) {
        case 1: // 视频
            $stmt = $pdo->prepare("SELECT `material_id`, `name`, `video_url`, `thumbnail_url`,
                                          `download_count`, `like_count`, `status`, `created_at`
                                   FROM `video_materials`
                                   WHERE `author_id` = ?
                                   ORDER BY `created_at` DESC
                                   LIMIT ? OFFSET ?");
            $stmt->execute([$userId, $pageSize, $offset]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($items as &$item) {
                $item['material_type'] = 1;
                $item['video_url'] = absoluteMediaUrl($item['video_url']);
                $item['thumbnail_url'] = absoluteMediaUrl($item['thumbnail_url'] ?? '');
            }
            $result['list'] = $items;

            // 检查是否还有更多
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `video_materials` WHERE `author_id` = ?");
            $stmt->execute([$userId]);
            $total = $stmt->fetchColumn();
            $result['has_more'] = $total > $page * $pageSize;
            break;

        case 2: // 图文
            $stmt = $pdo->prepare("SELECT m.`material_id`, m.`download_count`, m.`like_count`, m.`status`, m.`created_at`,
                                          i.`image_url`
                                   FROM `image_text_materials` m
                                   LEFT JOIN `image_text_images` i ON m.`material_id` = i.`material_id`
                                   WHERE m.`author_id` = ?
                                   GROUP BY m.`material_id`
                                   ORDER BY m.`created_at` DESC
                                   LIMIT ? OFFSET ?");
            $stmt->execute([$userId, $pageSize, $offset]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($items as &$item) {
                $item['material_type'] = 2;
                $item['image_url'] = absoluteMediaUrl($item['image_url'] ?? '');
            }
            $result['list'] = $items;

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `image_text_materials` WHERE `author_id` = ?");
            $stmt->execute([$userId]);
            $total = $stmt->fetchColumn();
            $result['has_more'] = $total > $page * $pageSize;
            break;

        case 4: // 文案
            $stmt = $pdo->prepare("SELECT `material_id`, `content`, `copy_count`, `like_count`, `status`, `created_at`
                                   FROM `text_materials`
                                   WHERE `author_id` = ?
                                   ORDER BY `created_at` DESC
                                   LIMIT ? OFFSET ?");
            $stmt->execute([$userId, $pageSize, $offset]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($items as &$item) {
                $item['material_type'] = 4;
            }
            $result['list'] = $items;

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `text_materials` WHERE `author_id` = ?");
            $stmt->execute([$userId]);
            $total = $stmt->fetchColumn();
            $result['has_more'] = $total > $page * $pageSize;
            break;

        default: // 全部类型
            $allItems = [];

            // 视频
            $stmt = $pdo->prepare("SELECT `material_id`, `name`, `video_url`, `thumbnail_url`,
                                          `download_count`, `like_count`, `status`, `created_at`, 1 as material_type
                                   FROM `video_materials`
                                   WHERE `author_id` = ?");
            $stmt->execute([$userId]);
            $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($videos as &$v) {
                $v['video_url'] = absoluteMediaUrl($v['video_url']);
                $v['thumbnail_url'] = absoluteMediaUrl($v['thumbnail_url'] ?? '');
            }
            $allItems = array_merge($allItems, $videos);

            // 图文
            $stmt = $pdo->prepare("SELECT m.`material_id`, m.`download_count`, m.`like_count`, m.`status`, m.`created_at`,
                                          2 as material_type, i.`image_url`
                                   FROM `image_text_materials` m
                                   LEFT JOIN `image_text_images` i ON m.`material_id` = i.`material_id`
                                   WHERE m.`author_id` = ?
                                   GROUP BY m.`material_id`");
            $stmt->execute([$userId]);
            $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($images as &$img) {
                $img['image_url'] = absoluteMediaUrl($img['image_url'] ?? '');
            }
            $allItems = array_merge($allItems, $images);

            // 文案
            $stmt = $pdo->prepare("SELECT `material_id`, `content`, `copy_count`, `like_count`, `status`, `created_at`, 4 as material_type
                                   FROM `text_materials`
                                   WHERE `author_id` = ?");
            $stmt->execute([$userId]);
            $texts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $allItems = array_merge($allItems, $texts);

            // 按时间排序
            usort($allItems, function($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });

            $total = count($allItems);
            $result['list'] = array_slice($allItems, $offset, $pageSize);
            $result['has_more'] = $total > $page * $pageSize;
            break;
    }

    echo jsonResponse(200, '获取成功', $result);

} catch (Exception $e) {
    echo jsonResponse(500, '获取失败：' . $e->getMessage(), null);
}
