<?php
namespace App\Support;
class Sanitize {
    public static function clean($data): string {
        if ($data === null || is_array($data)) return '';
        return mb_substr(trim(stripslashes((string)$data)), 0, 5000);
    }
    public static function esc($data): string {
        return htmlspecialchars((string)($data ?? ''), ENT_QUOTES, 'UTF-8');
    }
    public static function validUrl($url): string {
        $url = trim((string)$url);
        if ($url === '' || !preg_match('#^https?://#i', $url) || strlen($url) > 500) return '';
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
    }
}
