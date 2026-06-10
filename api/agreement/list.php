<?php
/**
 * 获取协议列表
 */

require_once '../../common/db.php';
require_once '../../common/functions.php';
require_once '../../common/response.php';

setCorsHeaders();
header('Content-Type: application/json; charset=utf-8');

$agreements = getAgreementsList();
echo jsonResponse(200, '获取成功', $agreements);
