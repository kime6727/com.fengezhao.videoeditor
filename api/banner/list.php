<?php
/**
 * 获取首页Banner列表
 */

require_once '../../common/db.php';
require_once '../../common/functions.php';
require_once '../../common/response.php';

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
setCorsHeaders();

// 如果是OPTIONS请求，直接返回
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

global $pdo;

// 获取当前时间用于时间筛选
$now = date('Y-m-d H:i:s');

// 从数据库查询启用的Banner数据
$stmt = $pdo->prepare("SELECT `banner_id`, `title`, `image_url`, `link_url`, `link_type`
                     FROM `banners`
                     WHERE `status` = 1
                     AND (`start_time` IS NULL OR `start_time` <= ?)
                     AND (`end_time` IS NULL OR `end_time` >= ?)
                     ORDER BY `sort` ASC, `created_at` DESC");
$stmt->execute([$now, $now]);
$banners = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 对URL进行绝对路径转换
foreach ($banners as &$banner) {
    if (isset($banner['image_url'])) {
        $banner['image_url'] = absoluteMediaUrl($banner['image_url']);
    }
    if (isset($banner['link_url'])) {
        $banner['link_url'] = absoluteMediaUrl($banner['link_url']);
    }
}

echo jsonResponse(200, '获取成功', ['list' => $banners]);