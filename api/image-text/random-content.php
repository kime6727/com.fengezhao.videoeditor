<?php
/**
 * 获取随机文案（点击刷新切换）
 * 返回完整的ImageTextMaterial数据结构
 */

require_once '../../common/db.php';
require_once '../../common/functions.php';
require_once '../../common/response.php';

setCorsHeaders();
header('Content-Type: application/json; charset=utf-8');

$materialId = $_GET['material_id'] ?? '';

if (empty($materialId)) {
    echo jsonResponse(400, '素材ID不能为空', null);
    exit;
}

global $pdo;

// 获取素材基本信息
$stmt = $pdo->prepare("SELECT `material_id`, `download_count`, `like_count`, `created_at`
                       FROM `image_text_materials`
                       WHERE `material_id` = ? AND `status` = 1");
$stmt->execute([$materialId]);
$material = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$material) {
    echo jsonResponse(404, '素材不存在', null);
    exit;
}

// 获取所有图片
$stmt = $pdo->prepare("SELECT `image_url`, `sort`
                      FROM `image_text_images`
                      WHERE `material_id` = ?
                      ORDER BY `sort` ASC");
$stmt->execute([$materialId]);
$images = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($images)) {
    echo jsonResponse(404, '该素材没有图片', null);
    exit;
}

// 获取所有文案
$stmt = $pdo->prepare("SELECT `id`, `content`, `sort`
                      FROM `image_text_contents`
                      WHERE `material_id` = ?
                      ORDER BY `sort` ASC");
$stmt->execute([$materialId]);
$contents = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($contents)) {
    echo jsonResponse(404, '该素材没有文案', null);
    exit;
}

// 随机返回一条文案
$randomContent = $contents[array_rand($contents)];

// 获取所有文案内容（用于all_contents字段）
$allContents = array_column($contents, 'content');

// 构建图片数组
$imageArray = [];
foreach ($images as $img) {
    $imageArray[] = [
        'image_url' => absoluteMediaUrl($img['image_url']),
        'sort' => intval($img['sort'])
    ];
}

// 构建完整的ImageTextMaterial数据结构
$result = [
    'material_id' => $material['material_id'],
    'images' => $imageArray,
    'current_content' => $randomContent['content'],
    'content_id' => $randomContent['id'],
    'contents' => $contents,
    'all_contents' => $allContents,
    'download_count' => intval($material['download_count']),
    'like_count' => intval($material['like_count']),
    'created_at' => $material['created_at'],
    'is_favorite' => false  // 如果需要收藏状态，可以从user_favorites表查询
];

// 如果提供了user_id，检查收藏状态
$userId = $_GET['user_id'] ?? '';
if (!empty($userId)) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `user_favorites`
                           WHERE `user_id` = ? AND `material_id` = ? AND `material_type` = 2");
    $stmt->execute([$userId, $materialId]);
    $result['is_favorite'] = $stmt->fetchColumn() > 0;
}

echo jsonResponse(200, '获取成功', $result);
