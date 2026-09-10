CREATE INDEX `idx_challenge_joins_cid` ON `challenge_joins` (`challenge_id`);
CREATE INDEX `idx_challenge_joins_uid` ON `challenge_joins` (`user_id`);
CREATE INDEX `idx_reactions_target` ON `reactions` (`target_type`, `target_id`);
CREATE INDEX `idx_reactions_user_target` ON `reactions` (`user_id`, `target_type`, `target_id`);
CREATE INDEX `idx_topo_saves_user` ON `topo_saves` (`user_id`, `id`);
CREATE INDEX `idx_evidence_user` ON `evidence_submissions` (`user_id`);
CREATE INDEX `idx_submission_owner` ON `submission_files` (`owner_id`, `created_at`);
CREATE INDEX `idx_xp_events_user_created` ON `xp_events` (`user_id`, `created_at`);
