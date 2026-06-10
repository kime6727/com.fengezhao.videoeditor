-- UGC 模式迁移脚本
-- 为素材表添加 author_id 字段，建立发布者关联
-- 创建时间: 2026-04-24

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- 1. 为单视频素材表添加 author_id 字段
-- ----------------------------
ALTER TABLE `video_materials`
ADD COLUMN `author_id` varchar(64) DEFAULT NULL COMMENT '发布者用户ID（关联users表）' AFTER `material_id`,
ADD KEY `author_id` (`author_id`);

-- ----------------------------
-- 2. 为图片+文案素材表添加 author_id 字段
-- ----------------------------
ALTER TABLE `image_text_materials`
ADD COLUMN `author_id` varchar(64) DEFAULT NULL COMMENT '发布者用户ID（关联users表）' AFTER `material_id`,
ADD KEY `author_id` (`author_id`);

-- ----------------------------
-- 3. 为视频+文案素材表添加 author_id 字段
-- ----------------------------
ALTER TABLE `video_text_materials`
ADD COLUMN `author_id` varchar(64) DEFAULT NULL COMMENT '发布者用户ID（关联users表）' AFTER `material_id`,
ADD KEY `author_id` (`author_id`);

-- ----------------------------
-- 4. 为纯文案素材表添加 author_id 字段
-- ----------------------------
ALTER TABLE `text_materials`
ADD COLUMN `author_id` varchar(64) DEFAULT NULL COMMENT '发布者用户ID（关联users表）' AFTER `material_id`,
ADD KEY `author_id` (`author_id`);

-- ----------------------------
-- 5. 创建 admin 默认发布者账户（用于无发布者素材的默认归属）
--    使用固定的 user_id: 00000000 作为系统/官方账户
-- ----------------------------
INSERT IGNORE INTO `users` (`user_id`, `username`, `password`, `device_id`, `phone`, `email`, `avatar`, `is_vip`, `user_type`, `platform`, `created_at`, `updated_at`)
VALUES ('00000000', '好素材官方', '', NULL, NULL, NULL, NULL, 0, 1, 'system', NOW(), NOW());

-- ----------------------------
-- 6. 将现有无发布者的素材全部归到 admin 账户
-- ----------------------------
UPDATE `video_materials` SET `author_id` = '00000000' WHERE `author_id` IS NULL;
UPDATE `image_text_materials` SET `author_id` = '00000000' WHERE `author_id` IS NULL;
UPDATE `video_text_materials` SET `author_id` = '00000000' WHERE `author_id` IS NULL;
UPDATE `text_materials` SET `author_id` = '00000000' WHERE `author_id` IS NULL;

SET FOREIGN_KEY_CHECKS = 1;
