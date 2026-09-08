CREATE INDEX `idx_quests_week_track_user` ON `quests` (`week`, `track`, `user_id`);
CREATE INDEX `idx_user_quests_quest_user` ON `user_quests` (`quest_id`, `user_id`);
