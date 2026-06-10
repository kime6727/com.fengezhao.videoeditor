-- 修复 user_id 字段长度的迁移脚本
-- 问题：user_id 字段定义为 varchar(8)，但实际应用中使用的是UUID格式（36-40字符）
-- 解决：将所有相关的 user_id 字段长度修改为 varchar(64)

SET NAMES utf8mb4;

-- 1. 修改 users 表的 user_id 字段
ALTER TABLE `users`
MODIFY COLUMN `user_id` varchar(64) NOT NULL COMMENT '用户唯一ID（UUID格式）';

-- 2. 修改 user_favorites 表的 user_id 字段
ALTER TABLE `user_favorites`
MODIFY COLUMN `user_id` varchar(64) NOT NULL COMMENT '用户唯一ID（UUID格式）';

-- 3. 修改 download_logs 表的 user_id 字段
ALTER TABLE `download_logs`
MODIFY COLUMN `user_id` varchar(64) NOT NULL COMMENT '用户唯一ID（UUID格式）';

-- 4. 修改 user_hidden_materials 表的 user_id 字段
ALTER TABLE `user_hidden_materials`
MODIFY COLUMN `user_id` varchar(64) NOT NULL COMMENT '用户唯一ID（UUID格式）';

-- 5. 修改 material_reports 表的 user_id 字段
ALTER TABLE `material_reports`
MODIFY COLUMN `user_id` varchar(64) NOT NULL COMMENT '举报用户ID（UUID格式）';

-- 6. 修改 user_download_limits 表的 user_id 字段
ALTER TABLE `user_download_limits`
MODIFY COLUMN `user_id` varchar(64) NOT NULL COMMENT '用户ID（UUID格式）';

-- 7. 修改 payment_records 表的 user_id 字段
ALTER TABLE `payment_records`
MODIFY COLUMN `user_id` varchar(64) NOT NULL COMMENT '用户唯一ID（UUID格式）';

-- 8. 修改 subscription_records 表的 user_id 字段
ALTER TABLE `subscription_records`
MODIFY COLUMN `user_id` varchar(64) NOT NULL COMMENT '用户唯一ID（UUID格式）';

-- 迁移完成
SELECT 'Migration completed: All user_id fields updated to varchar(64)' as status;
