<?php
/**
 * 获取协议详情
 */

require_once '../../common/db.php';
require_once '../../common/functions.php';
require_once '../../common/response.php';

setCorsHeaders();
header('Content-Type: application/json; charset=utf-8');

$agreementId = $_GET['agreement_id'] ?? '';

if (empty($agreementId)) {
    echo jsonResponse(400, '协议ID不能为空', null);
    exit;
}

$agreement = getAgreementDetail($agreementId);

if ($agreement) {
    echo jsonResponse(200, '获取成功', $agreement);
} else {
    echo jsonResponse(404, '协议不存在', null);
}
