<?php
/**
 * 获取视频列表（瀑布流，过滤隐藏素材）
 * SFTP 连接测试成功 - 2025-12-22
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

$videos = getVideoListForWaterfall($userId, $categoryId, $page, $pageSize);

echo jsonResponse(200, '获取成功', [
    'list' => $videos,
    'page' => $page,
    'page_size' => $pageSize,
    'has_more' => count($videos) >= $pageSize
]);