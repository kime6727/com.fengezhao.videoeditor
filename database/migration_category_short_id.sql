-- 分类ID改为短ID（001、002、003...）
-- 执行前请先备份数据库！

-- 1. 修改 categories 表的 category_id 字段类型
ALTER TABLE `categories` MODIFY COLUMN `category_id` varchar(10) NOT NULL COMMENT '分类唯一ID（001、002、003递增）';

-- 2. 修改 category_relations 表的 category_id 字段类型
ALTER TABLE `category_relations` MODIFY COLUMN `category_id` varchar(10) NOT NULL COMMENT '分类唯一ID';

-- 注意：如果已有分类数据，需要手动迁移现有的长ID到短ID
-- 可选：清空现有分类并重新添加（如果数据不重要）
-- TRUNCATE TABLE `category_relations`;
-- TRUNCATE TABLE `categories`;
