<?php
/**
 * 应用配置文件
 */

// 时区设置
date_default_timezone_set('Asia/Shanghai');

// 错误报告（生产环境请关闭）
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 应用基础配置
define('APP_NAME', '好素材');
define('APP_VERSION', '1.0.0');

// 文件上传配置
define('UPLOAD_PATH', dirname(__DIR__) . '/uploads/');
define('UPLOAD_URL', '/uploads/');
define('MAX_FILE_SIZE', 100 * 1024 * 1024); // 100MB

// 允许的文件类型
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_VIDEO_TYPES', ['video/mp4', 'video/mov', 'video/avi']);

// 举报类型配置
define('REPORT_TYPES', [
    'porn' => '色情低俗',
    'violence' => '暴力血腥',
    'ad' => '广告骚扰',
    'illegal' => '违法违规',
    'other' => '其他'
]);

// 素材类型常量
define('MATERIAL_TYPE_VIDEO', 1);           // 单视频
define('MATERIAL_TYPE_IMAGE_TEXT', 2);       // 图片+文案
define('MATERIAL_TYPE_VIDEO_TEXT', 3);       // 视频+文案
define('MATERIAL_TYPE_TEXT', 4);            // 纯文案

// 协议类型常量
define('AGREEMENT_TYPE_USER', 'user_agreement');        // 用户协议
define('AGREEMENT_TYPE_PRIVACY', 'privacy_policy');     // 隐私政策
define('AGREEMENT_TYPE_AUTO_RENEWAL', 'auto_renewal');  // 自动续费协议
