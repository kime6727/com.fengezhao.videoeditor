-- 好素材APP数据库初始化脚本
-- 创建时间: 2024

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- 用户表
-- ----------------------------
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(64) NOT NULL COMMENT '用户唯一ID（UUID格式）',
  `device_id` varchar(100) DEFAULT NULL COMMENT '设备ID（游客标识）',
  `username` varchar(50) DEFAULT NULL COMMENT '用户名（可选）',
  `phone` varchar(20) DEFAULT NULL COMMENT '手机号',
  `email` varchar(100) DEFAULT NULL COMMENT '邮箱',
  `password` varchar(255) DEFAULT NULL COMMENT '密码(加密)',
  `avatar` varchar(255) DEFAULT NULL COMMENT '头像',
  `is_vip` tinyint(1) DEFAULT 0 COMMENT '是否VIP 0-否 1-是',
  `vip_expire_time` datetime DEFAULT NULL COMMENT 'VIP过期时间',
  `download_count` int(11) DEFAULT 0 COMMENT '已下载视频数量（未订阅用户限制2个）',
  `user_type` tinyint(1) DEFAULT 0 COMMENT '用户类型 0-游客 1-注册用户',
  `apple_id` varchar(100) DEFAULT NULL COMMENT 'Apple ID登录标识',
  `wechat_openid` varchar(100) DEFAULT NULL COMMENT '微信OpenID',
  `platform` varchar(20) DEFAULT NULL COMMENT '用户平台（ios/android）',
  `last_login_platform` varchar(20) DEFAULT NULL COMMENT '最后登录平台',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `device_id` (`device_id`),
  KEY `phone` (`phone`),
  KEY `apple_id` (`apple_id`),
  KEY `wechat_openid` (`wechat_openid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户表';

-- ----------------------------
-- 分类表（所有素材类型共用）
-- ----------------------------
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` varchar(32) NOT NULL COMMENT '分类唯一ID（随机生成）',
  `name` varchar(50) NOT NULL COMMENT '分类名称',
  `type` tinyint(1) NOT NULL COMMENT '素材类型 1-单视频 2-图片+文案 3-视频+文案 4-纯文案',
  `sort` int(11) DEFAULT 0 COMMENT '排序（支持置顶，数值越小越靠前）',
  `is_top` tinyint(1) DEFAULT 0 COMMENT '是否置顶 0-否 1-是',
  `status` tinyint(1) DEFAULT 1 COMMENT '状态 0-禁用 1-启用',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `category_id` (`category_id`),
  KEY `type` (`type`),
  KEY `sort` (`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='分类表';

-- ----------------------------
-- 分类关联表（多对多关系）
-- ----------------------------
CREATE TABLE `category_relations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` varchar(32) NOT NULL COMMENT '分类唯一ID',
  `material_id` varchar(32) NOT NULL COMMENT '素材唯一ID',
  `material_type` tinyint(1) NOT NULL COMMENT '素材类型 1-单视频 2-图片+文案 3-视频+文案 4-纯文案',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  KEY `material` (`material_id`,`material_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='分类关联表';

-- ----------------------------
-- 单视频素材表
-- ----------------------------
CREATE TABLE `video_materials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `material_id` varchar(32) NOT NULL COMMENT '素材唯一ID（随机生成）',
  `name` varchar(200) NOT NULL COMMENT '视频名称',
  `video_url` varchar(500) NOT NULL COMMENT '视频URL',
  `thumbnail_url` varchar(500) DEFAULT NULL COMMENT '缩略图URL',
  `download_count` int(11) DEFAULT 0 COMMENT '下载次数',
  `like_count` int(11) DEFAULT 0 COMMENT '点赞数',
  `status` tinyint(1) DEFAULT 1 COMMENT '状态 0-下架 1-上架',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `material_id` (`material_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='单视频素材表';

-- ----------------------------
-- 图片+文案素材表
-- ----------------------------
CREATE TABLE `image_text_materials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `material_id` varchar(32) NOT NULL COMMENT '素材唯一ID（随机生成）',
  `download_count` int(11) DEFAULT 0 COMMENT '下载次数',
  `like_count` int(11) DEFAULT 0 COMMENT '点赞数',
  `status` tinyint(1) DEFAULT 1 COMMENT '状态 0-下架 1-上架',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `material_id` (`material_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='图片+文案素材表';

-- ----------------------------
-- 图片素材图片表（1-9张）
-- ----------------------------
CREATE TABLE `image_text_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `material_id` varchar(32) NOT NULL COMMENT '素材唯一ID',
  `image_url` varchar(500) NOT NULL COMMENT '图片URL',
  `sort` int(11) DEFAULT 0 COMMENT '排序',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `material_id` (`material_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='图片+文案素材的图片表';

-- ----------------------------
-- 图片+文案素材的文案表（1-30条）
-- ----------------------------
CREATE TABLE `image_text_contents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `material_id` varchar(32) NOT NULL COMMENT '素材唯一ID',
  `content` text NOT NULL COMMENT '文案内容',
  `sort` int(11) DEFAULT 0 COMMENT '排序',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `material_id` (`material_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='图片+文案素材的文案表';

-- ----------------------------
-- 视频+文案素材表
-- ----------------------------
CREATE TABLE `video_text_materials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `material_id` varchar(32) NOT NULL COMMENT '素材唯一ID（随机生成）',
  `video_url` varchar(500) NOT NULL COMMENT '视频URL',
  `thumbnail_url` varchar(500) DEFAULT NULL COMMENT '缩略图URL',
  `download_count` int(11) DEFAULT 0 COMMENT '下载次数',
  `like_count` int(11) DEFAULT 0 COMMENT '点赞数',
  `status` tinyint(1) DEFAULT 1 COMMENT '状态 0-下架 1-上架',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `material_id` (`material_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='视频+文案素材表';

-- ----------------------------
-- 视频+文案素材的文案表（1-30条）
-- ----------------------------
CREATE TABLE `video_text_contents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `material_id` varchar(32) NOT NULL COMMENT '素材唯一ID',
  `content` text NOT NULL COMMENT '文案内容',
  `sort` int(11) DEFAULT 0 COMMENT '排序',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `material_id` (`material_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='视频+文案素材的文案表';

-- ----------------------------
-- 纯文案素材表
-- ----------------------------
CREATE TABLE `text_materials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `material_id` varchar(32) NOT NULL COMMENT '素材唯一ID（随机生成）',
  `content` text NOT NULL COMMENT '文案内容',
  `copy_count` int(11) DEFAULT 0 COMMENT '复制次数',
  `like_count` int(11) DEFAULT 0 COMMENT '点赞数',
  `status` tinyint(1) DEFAULT 1 COMMENT '状态 0-下架 1-上架',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `material_id` (`material_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='纯文案素材表';

-- ----------------------------
-- 用户收藏表（统一收藏表）
-- ----------------------------
CREATE TABLE `user_favorites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(64) NOT NULL COMMENT '用户唯一ID（UUID格式）',
  `material_id` varchar(32) NOT NULL COMMENT '素材唯一ID',
  `material_type` tinyint(1) NOT NULL COMMENT '素材类型 1-单视频 2-图片+文案 3-视频+文案 4-纯文案',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_material` (`user_id`,`material_id`,`material_type`),
  KEY `user_id` (`user_id`),
  KEY `material_id` (`material_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户收藏表';

-- ----------------------------
-- 用户下载记录表
-- ----------------------------
CREATE TABLE `download_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(64) NOT NULL COMMENT '用户唯一ID（UUID格式）',
  `material_id` varchar(32) NOT NULL COMMENT '素材唯一ID',
  `material_type` tinyint(1) NOT NULL COMMENT '素材类型 1-单视频 2-图片+文案 3-视频+文案 4-纯文案',
  `download_type` varchar(20) DEFAULT NULL COMMENT '下载类型 video/image/text',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `material` (`material_id`,`material_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='下载记录表';

-- ----------------------------
-- 文案复制记录表
-- ----------------------------
CREATE TABLE `copy_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(64) NOT NULL COMMENT '用户唯一ID（UUID格式）',
  `material_id` varchar(32) NOT NULL COMMENT '素材唯一ID',
  `material_type` tinyint(1) NOT NULL COMMENT '素材类型 2-图片+文案 3-视频+文案 4-纯文案',
  `content_id` int(11) DEFAULT NULL COMMENT '文案ID（用于图片+文案和视频+文案）',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `material_id` (`material_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='文案复制记录表';

-- ----------------------------
-- 订阅/VIP套餐表
-- ----------------------------
CREATE TABLE `subscription_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `plan_id` varchar(32) NOT NULL COMMENT '套餐唯一ID（随机生成）',
  `name` varchar(50) NOT NULL COMMENT '套餐名称',
  `price` decimal(10,2) NOT NULL COMMENT '价格',
  `duration_days` int(11) NOT NULL COMMENT '时长（天）',
  `description` text COMMENT '描述',
  `sort` int(11) DEFAULT 0 COMMENT '排序',
  `status` tinyint(1) DEFAULT 1 COMMENT '状态 0-禁用 1-启用',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `plan_id` (`plan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='订阅套餐表';

-- ----------------------------
-- 广告Banner表
-- ----------------------------
CREATE TABLE `banners` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `banner_id` varchar(32) NOT NULL COMMENT 'Banner唯一ID',
  `title` varchar(100) DEFAULT NULL COMMENT '标题',
  `image_url` varchar(500) NOT NULL COMMENT 'Banner图片URL',
  `link_url` varchar(500) DEFAULT NULL COMMENT '跳转链接（可选）',
  `link_type` tinyint(1) DEFAULT 0 COMMENT '链接类型 0-无链接 1-内部页面 2-外部链接',
  `sort` int(11) DEFAULT 0 COMMENT '排序',
  `status` tinyint(1) DEFAULT 1 COMMENT '状态 0-禁用 1-启用',
  `start_time` datetime DEFAULT NULL COMMENT '开始时间',
  `end_time` datetime DEFAULT NULL COMMENT '结束时间',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `banner_id` (`banner_id`),
  KEY `status` (`status`),
  KEY `sort` (`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='广告Banner表';

-- ----------------------------
-- 素材举报表
-- ----------------------------
CREATE TABLE `material_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(8) NOT NULL COMMENT '举报用户ID（8位数字）',
  `material_id` varchar(32) NOT NULL COMMENT '素材唯一ID',
  `material_type` tinyint(1) NOT NULL COMMENT '素材类型 1-单视频 2-图片+文案 3-视频+文案 4-纯文案',
  `report_type` varchar(50) DEFAULT NULL COMMENT '举报类型（如：色情、暴力、广告、其他）',
  `report_content` text COMMENT '举报内容描述',
  `status` tinyint(1) DEFAULT 0 COMMENT '处理状态 0-待处理 1-已处理 2-已驳回',
  `admin_remark` text COMMENT '管理员备注',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `material` (`material_id`,`material_type`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='素材举报表';

-- ----------------------------
-- 用户隐藏素材表（举报后对该用户隐藏）
-- ----------------------------
CREATE TABLE `user_hidden_materials` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(8) NOT NULL COMMENT '用户ID（8位数字）',
  `material_id` varchar(32) NOT NULL COMMENT '素材唯一ID',
  `material_type` tinyint(1) NOT NULL COMMENT '素材类型 1-单视频 2-图片+文案 3-视频+文案 4-纯文案',
  `reason` varchar(50) DEFAULT 'report' COMMENT '隐藏原因 report-举报 hidden-手动隐藏',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_material` (`user_id`,`material_id`,`material_type`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户隐藏素材表';

-- ----------------------------
-- 应用协议表
-- ----------------------------
CREATE TABLE `agreements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `agreement_id` varchar(32) NOT NULL COMMENT '协议唯一ID',
  `title` varchar(100) NOT NULL COMMENT '协议标题（如：用户协议、隐私政策、自动续费协议）',
  `content` text NOT NULL COMMENT '协议内容',
  `type` varchar(50) DEFAULT NULL COMMENT '协议类型 user_agreement/privacy_policy/auto_renewal',
  `version` varchar(20) DEFAULT '1.0' COMMENT '版本号',
  `sort` int(11) DEFAULT 0 COMMENT '排序',
  `status` tinyint(1) DEFAULT 1 COMMENT '状态 0-禁用 1-启用',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `agreement_id` (`agreement_id`),
  KEY `type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='应用协议表';

-- ----------------------------
-- Android支付记录表
-- ----------------------------
CREATE TABLE `payment_records` (
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
-- iOS订阅记录表
-- ----------------------------
CREATE TABLE `subscription_records` (
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

SET FOREIGN_KEY_CHECKS = 1;

