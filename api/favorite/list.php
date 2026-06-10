<?php
/**
 * 获取我的收藏列表
 * 改造：添加认证支持，优先从 Token 获取 user_id
 */

require_once '../../common/db.php';
require_once '../../common/functions.php';
require_once '../../common/response.php';
require_once '../../common/auth.php';

setCorsHeaders();
header('Content-Type: application/json; charset=utf-8');

// 认证用户（兼容 device_id）
$user = authenticateUser();
if (!$user) {
    echo jsonResponse(401, '未授权，请先登录', null);
    exit;
}

// 优先使用认证用户的 user_id，也兼容 GET 传入（渐进迁移）
$userId = $user['user_id'];
$materialType = intval($_GET['material_type'] ?? 0); // 可选，筛选类型
$page = intval($_GET['page'] ?? 1);
$pageSize = intval($_GET['page_size'] ?? 20);

global $pdo;

$sql = "SELECT `material_id`, `material_type`, `created_at`
        FROM `user_favorites`
        WHERE `user_id` = ?";

$params = [$userId];

if ($materialType > 0) {
    $sql .= " AND `material_type` = ?";
    $params[] = $materialType;
}

$sql .= " ORDER BY `created_at` DESC LIMIT ? OFFSET ?";
$params[] = $pageSize;
$params[] = ($page - 1) * $pageSize;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取每个收藏的详细信息
foreach ($favorites as &$favorite) {
    switch ($favorite['material_type']) {
        case 1: // 单视频
            $stmt = $pdo->prepare("SELECT `material_id`, `name`, `video_url`, `thumbnail_url`, `download_count`, `like_count`, `created_at`, `status`
                                 FROM `video_materials` WHERE `material_id` = ?");
            $stmt->execute([$favorite['material_id']]);
            $detail = $stmt->fetch(PDO::FETCH_ASSOC);
            // 确保所有字段都存在，即使为null或素材不存在
            if ($detail) {
                $detail['name'] = $detail['name'] ?? null;
                $detail['video_url'] = absoluteMediaUrl($detail['video_url'] ?? null);
                $detail['thumbnail_url'] = absoluteMediaUrl($detail['thumbnail_url'] ?? null);
                $detail['download_count'] = $detail['download_count'] ?? 0;
                $detail['like_count'] = $detail['like_count'] ?? 0;
                $favorite['detail'] = $detail;
            } else {
                // 素材不存在时，返回基本信息
                $favorite['detail'] = [
                    'material_id' => $favorite['material_id'],
                    'name' => null,
                    'video_url' => null,
                    'thumbnail_url' => null,
                    'download_count' => 0,
                    'like_count' => 0,
                    'status' => 0
                ];
            }
            break;
        case 2: // 图片+文案
            $stmt = $pdo->prepare("SELECT `material_id`, `download_count`, `like_count`, `created_at`, `status`
                                 FROM `image_text_materials` WHERE `material_id` = ?");
            $stmt->execute([$favorite['material_id']]);
            $detail = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($detail) {
                // 确保基本字段存在
                $detail['download_count'] = $detail['download_count'] ?? 0;
                $detail['like_count'] = $detail['like_count'] ?? 0;

                // 获取第一张图片
                $stmt = $pdo->prepare("SELECT `image_url` FROM `image_text_images`
                                      WHERE `material_id` = ? ORDER BY `sort` ASC LIMIT 1");
                $stmt->execute([$favorite['material_id']]);
                $img = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($img) {
                    $detail['thumbnail_url'] = absoluteMediaUrl($img['image_url']);
                } else {
                    $detail['thumbnail_url'] = null;
                }

                // 获取第一条文案
                $stmt = $pdo->prepare("SELECT `content` FROM `image_text_contents`
                                      WHERE `material_id` = ? ORDER BY `sort` ASC LIMIT 1");
                $stmt->execute([$favorite['material_id']]);
                $content = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($content) {
                    $detail['content'] = $content['content'];
                } else {
                    $detail['content'] = null;
                }

                $favorite['detail'] = $detail;
            } else {
                // 素材不存在时，返回基本信息
                $favorite['detail'] = [
                    'material_id' => $favorite['material_id'],
                    'thumbnail_url' => null,
                    'content' => null,
                    'download_count' => 0,
                    'like_count' => 0,
                    'status' => 0
                ];
            }
            break;
        case 3: // 视频+文案
            $stmt = $pdo->prepare("SELECT `material_id`, `video_url`, `thumbnail_url`, `download_count`, `like_count`, `created_at`, `status`
                                 FROM `video_text_materials` WHERE `material_id` = ?");
            $stmt->execute([$favorite['material_id']]);
            $detail = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($detail) {
                // 确保基本字段存在
                $detail['video_url'] = absoluteMediaUrl($detail['video_url'] ?? null);
                $detail['thumbnail_url'] = absoluteMediaUrl($detail['thumbnail_url'] ?? null);
                $detail['download_count'] = $detail['download_count'] ?? 0;
                $detail['like_count'] = $detail['like_count'] ?? 0;

                // 获取第一条文案
                $stmt = $pdo->prepare("SELECT `content` FROM `video_text_contents`
                                      WHERE `material_id` = ? ORDER BY `sort` ASC LIMIT 1");
                $stmt->execute([$favorite['material_id']]);
                $content = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($content) {
                    $detail['content'] = $content['content'];
                } else {
                    $detail['content'] = null;
                }

                $favorite['detail'] = $detail;
            } else {
                // 素材不存在时，返回基本信息
                $favorite['detail'] = [
                    'material_id' => $favorite['material_id'],
                    'video_url' => null,
                    'thumbnail_url' => null,
                    'content' => null,
                    'download_count' => 0,
                    'like_count' => 0,
                    'status' => 0
                ];
            }
            break;
        case 4: // 纯文案
            $stmt = $pdo->prepare("SELECT `material_id`, `content`, `copy_count`, `like_count`, `created_at`, `status`
                                 FROM `text_materials` WHERE `material_id` = ?");
            $stmt->execute([$favorite['material_id']]);
            $detail = $stmt->fetch(PDO::FETCH_ASSOC);
            // 确保所有字段都存在
            if ($detail) {
                $detail['content'] = $detail['content'] ?? null;
                $detail['copy_count'] = $detail['copy_count'] ?? 0;
                $detail['like_count'] = $detail['like_count'] ?? 0;
                $favorite['detail'] = $detail;
            } else {
                // 素材不存在时，返回基本信息
                $favorite['detail'] = [
                    'material_id' => $favorite['material_id'],
                    'content' => null,
                    'copy_count' => 0,
                    'like_count' => 0,
                    'status' => 0
                ];
            }
            break;
        default:
            $favorite['detail'] = null;
            break;
    }
}

echo jsonResponse(200, '获取成功', [
    'list' => $favorites,
    'page' => $page,
    'page_size' => $pageSize,
    'has_more' => count($favorites) >= $pageSize
]);
