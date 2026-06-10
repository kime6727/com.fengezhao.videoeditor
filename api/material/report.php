<?php
/**
 * 提交举报
 */

require_once '../../common/db.php';
require_once '../../common/functions.php';
require_once '../../common/response.php';

setCorsHeaders();
header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);

$userId = $data['user_id'] ?? '';
$materialId = $data['material_id'] ?? '';
$materialType = intval($data['material_type'] ?? 0);
$reportType = $data['report_type'] ?? '';
$reportContent = $data['report_content'] ?? '';

if (empty($userId) || empty($materialId) || empty($materialType) || empty($reportType)) {
    echo jsonResponse(400, '参数不完整', null);
    exit;
}

$result = submitReport($userId, $materialId, $materialType, $reportType, $reportContent);

if (isset($result['error'])) {
    echo jsonResponse(400, $result['error'], null);
} else {
    echo jsonResponse(200, $result['message'], null);
}
