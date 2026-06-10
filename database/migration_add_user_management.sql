-- 用户管理功能数据库迁移脚本
-- 执行时间: 请在执行前备份数据库

SET NAMES utf8mb4;

-- ----------------------------
-- 1. 修改 users 表，添加新字段
-- ----------------------------

-- 添加 Apple ID 字段
ALTER TABLE `users`
ADD COLUMN `apple_id` varchar(100) DEFAULT NULL COMMENT 'Apple ID登录标识' AFTER `user_type`;

-- 添加微信 OpenID 字段
ALTER TABLE `users`
ADD COLUMN `wechat_openid` varchar(100) DEFAULT NULL COMMENT '微信OpenID' AFTER `apple_id`;

-- 添加平台字段
ALTER TABLE `users`
ADD COLUMN `platform` varchar(20) DEFAULT NULL COMMENT '用户平台（ios/android）' AFTER `wechat_openid`;

-- 添加最后登录平台字段
ALTER TABLE `users`
ADD COLUMN `last_login_platform` varchar(20) DEFAULT NULL COMMENT '最后登录平台' AFTER `platform`;

-- 添加索引
ALTER TABLE `users`
ADD INDEX `apple_id` (`apple_id`),
ADD INDEX `wechat_openid` (`wechat_openid`);

-- ----------------------------
-- 2. 创建 Android支付记录表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `payment_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` varchar(64) NOT NULL COMMENT '订单ID',
  `user_id` varchar(64) NOT NULL COMMENT '用户唯一ID（UUID格式）',
  `product_id` varchar(50) NOT NULL COMMENT '产品ID',
  `payment_method` varchar(20) NOT NULL COMMENT '支付方式（alipay/wechat）',
  `amount` decimal(10,2) NOT NULL COMMENT '支付金额',
  `order_status` varchar(20) DEFAULT 'pending' COMMENT '订单状态（pending/paid/failed/refunded）',
  `transaction_id` varchar(100) DEFAULT NULL COMMENT '第三方交易ID',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_id` (`order_id`),
  KEY `user_id` (`user_id`),
  KEY `order_status` (`order_status`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Android支付记录表';

-- ----------------------------
-- 3. 创建 iOS订阅记录表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `subscription_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subscription_id` varchar(64) NOT NULL COMMENT '订阅ID',
  `user_id` varchar(64) NOT NULL COMMENT '用户唯一ID（UUID格式）',
  `product_id` varchar(50) NOT NULL COMMENT '产品ID',
  `transaction_id` varchar(100) NOT NULL COMMENT '交易ID',
  `subscription_status` varchar(20) DEFAULT 'active' COMMENT '订阅状态（active/expired/cancelled/refunded）',
  `start_time` datetime NOT NULL COMMENT '订阅开始时间',
  `expire_time` datetime NOT NULL COMMENT '订阅到期时间',
  `original_transaction_id` varchar(100) DEFAULT NULL COMMENT '原始交易ID（用于续费）',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `subscription_id` (`subscription_id`),
  KEY `user_id` (`user_id`),
  KEY `subscription_status` (`subscription_status`),
  KEY `expire_time` (`expire_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='iOS订阅记录表';

-- 迁移完成
