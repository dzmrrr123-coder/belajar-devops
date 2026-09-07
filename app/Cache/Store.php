<?php
namespace App\Cache;
class Store {
    private static array $memory = [];
    private static $redis = null;
    private static ?bool $redisTried = null;
    public static function enabled(): bool {
        $v = getenv('CACHE_DISABLED');
        return !($v === '1' || strtolower((string)$v) === 'true');
    }
    public static function remember(string $key, int $ttl, callable $fn) {
        if (!self::enabled()) return $fn();
        $hit = self::get($key);
        if ($hit['hit']) return $hit['value'];
        $val = $fn();
        self::set($key, $val, $ttl);
        return $val;
    }
    public static function get(string $key): array {
        try {
            $r = self::redis();
            if ($r) {
                $raw = $r->get('lt:' . $key);
                if ($raw === false || $raw === null) return ['hit' => false];
                $val = json_decode($raw, true);
                return ['hit' => true, 'value' => $val['v'] ?? null];
            }
        } catch (\Throwable $e) {}
        if (isset(self::$memory[$key])) {
            $e = self::$memory[$key];
            if ($e['exp'] > time()) return ['hit' => true, 'value' => $e['v']];
            unset(self::$memory[$key]);
        }
        $f = self::file($key);
        if (is_file($f)) {
            try {
                $raw = json_decode(@file_get_contents($f) ?: '', true);
                if (is_array($raw) && ($raw['exp'] ?? 0) > time()) return ['hit' => true, 'value' => $raw['v'] ?? null];
                @unlink($f);
            } catch (\Throwable $e) {}
        }
        return ['hit' => false];
    }
    public static function set(string $key, $val, int $ttl): void {
        $ttl = max(1, $ttl);
        try {
            $r = self::redis();
            if ($r) { $r->setex('lt:' . $key, $ttl, json_encode(['v' => $val])); return; }
        } catch (\Throwable $e) {}
        self::$memory[$key] = ['v' => $val, 'exp' => time() + $ttl];
        try {
            $f = self::file($key);
            @file_put_contents($f, json_encode(['v' => $val, 'exp' => time() + $ttl]), LOCK_EX);
        } catch (\Throwable $e) {}
    }
    public static function forget(string $key): void {
        unset(self::$memory[$key]);
        try {
            $r = self::redis();
            if ($r) $r->del('lt:' . $key);
        } catch (\Throwable $e) {}
        try { @unlink(self::file($key)); } catch (\Throwable $e) {}
    }
    public static function forgetPrefix(string $prefix): void {
        foreach (array_keys(self::$memory) as $k) {
            if (strpos($k, $prefix) === 0) unset(self::$memory[$k]);
        }
        try {
            $r = self::redis();
            if ($r) {
                $it = null; $pat = 'lt:' . $prefix . '*';
                do {
                    $keys = $r->scan($it, $pat, 100);
                    if ($keys) $r->del($keys);
                } while ($it > 0);
                return;
            }
        } catch (\Throwable $e) {}
        try {
            foreach (glob(self::dir() . '/' . self::safe($prefix) . '*.cache') ?: [] as $f) @unlink($f);
        } catch (\Throwable $e) {}
    }
    private static function redis() {
        if (self::$redisTried !== null) return self::$redis;
        self::$redisTried = true;
        try {
            if (!extension_loaded('redis')) return null;
            $url = getenv('REDIS_URL') ?: '';
            $host = getenv('REDIS_HOST') ?: '';
            $port = (int)(getenv('REDIS_PORT') ?: 6379);
            $r = new \Redis();
            if ($url !== '' && ($p = parse_url($url))) {
                $host = $p['host'] ?? '127.0.0.1'; $port = (int)($p['port'] ?? 6379);
                $ok = @$r->connect($host, $port, 1.5);
                if ($ok && isset($p['pass'])) $r->auth($p['pass']);
                elseif ($ok && isset($p['user'])) $r->auth($p['user']);
                if (!$ok) return null;
            } elseif ($host !== '') {
                if (!@$r->connect($host, $port, 1.5)) return null;
                $pass = getenv('REDIS_PASSWORD') ?: '';
                if ($pass !== '') $r->auth($pass);
            } else {
                return null;
            }
            $db = (int)(getenv('REDIS_DB') ?: 0);
            if ($db > 0) $r->select($db);
            self::$redis = $r;
            return $r;
        } catch (\Throwable $e) { return null; }
    }
    private static function dir(): string {
        $d = dirname(__DIR__, 2) . '/storage/cache';
        if (!is_dir($d)) @mkdir($d, 0775, true);
        return $d;
    }
    private static function safe(string $key): string {
        return preg_replace('/[^a-zA-Z0-9_\-:]/', '_', $key);
    }
    private static function file(string $key): string {
        return self::dir() . '/' . self::safe($key) . '.cache';
    }
}
