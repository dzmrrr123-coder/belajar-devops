ALTER TABLE `users` ADD COLUMN `track` VARCHAR(16) NOT NULL DEFAULT 'devops';
ALTER TABLE `quests` ADD COLUMN `track` VARCHAR(16) NOT NULL DEFAULT 'devops';
INSERT IGNORE INTO `skill_nodes` (`slug`, `name`, `icon`, `sort`) VALUES ('testing', 'Testing & QA', 'fas fa-vial', 11);
