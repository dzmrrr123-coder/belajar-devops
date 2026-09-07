<?php
namespace App\Analytics;
class Events {
    public const ONBOARDING_COMPLETED = 'onboarding_completed';
    public const QUEST_COMPLETED = 'quest_completed';
    public const FOCUS_COMPLETED = 'focus_completed';
    public const REVIEW_ANSWERED = 'review_answered';
    public const MISSION_CLAIMED = 'mission_claimed';
    public const LEVEL_UP = 'level_up';
    public const BADGE_UNLOCKED = 'badge_unlocked';
    public const HINT_USED = 'hint_used';
    public const INCIDENT_COMPLETED = 'incident_completed';
    public static function all(): array {
        return [self::ONBOARDING_COMPLETED, self::QUEST_COMPLETED, self::FOCUS_COMPLETED, self::REVIEW_ANSWERED, self::MISSION_CLAIMED, self::LEVEL_UP, self::BADGE_UNLOCKED, self::HINT_USED, self::INCIDENT_COMPLETED];
    }
}
