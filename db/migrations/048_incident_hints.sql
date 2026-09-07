ALTER TABLE `incident_challenges` ADD COLUMN `objective` VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE `incident_challenges` ADD COLUMN `est_minutes` INT NOT NULL DEFAULT 10;
ALTER TABLE `incident_challenges` ADD COLUMN `hint_lvl1` TEXT NOT NULL;
ALTER TABLE `incident_challenges` ADD COLUMN `hint_lvl2` TEXT NOT NULL;
ALTER TABLE `incident_challenges` ADD COLUMN `explanation` TEXT NOT NULL;
