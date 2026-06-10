-- 为协议表添加 url 字段
-- 执行时间: 2025-12-25

ALTER TABLE `agreements` ADD COLUMN `url` varchar(500) DEFAULT NULL COMMENT '协议URL（可选，如果配置了URL则跳转到URL，否则显示content内容）' AFTER `content`;

-- 更新已有数据示例（可选）
-- UPDATE `agreements` SET `url` = 'https://www.example.com/terms' WHERE `type` = 'user_agreement';
-- UPDATE `agreements` SET `url` = 'https://www.example.com/privacy' WHERE `type` = 'privacy_policy';
