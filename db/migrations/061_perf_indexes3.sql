CREATE INDEX `idx_evidence_user_created` ON `evidence_submissions` (`user_id`, `created_at`);
CREATE INDEX `idx_xp_events_user_ref_created` ON `xp_events` (`user_id`, `ref_type`, `created_at`);
