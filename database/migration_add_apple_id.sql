ALTER TABLE `users` ADD COLUMN `apple_id` VARCHAR(255) DEFAULT NULL COMMENT 'Apple User Identifier';
ALTER TABLE `users` ADD UNIQUE INDEX `idx_apple_id` (`apple_id`);
