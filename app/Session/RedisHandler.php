<?php
namespace App\Session;
class RedisHandler implements \SessionHandlerInterface {
    private $redis = null;
    private int $ttl = 7200;
    public function open($path, $name): bool {
        try {
            if (!extension_loaded('redis')) return false;
            $url = getenv('REDIS_URL') ?: '';
            $host = getenv('REDIS_HOST') ?: '';
            $port = (int)(getenv('REDIS_PORT') ?: 6379);
            $r = new \Redis();
            if ($url !== '' && ($p = parse_url($url))) {
                $host = $p['host'] ?? '127.0.0.1'; $port = (int)($p['port'] ?? 6379);
                if (!@$r->connect($host, $port, 1.5)) return false;
                if (isset($p['pass'])) $r->auth($p['pass']);
            } else {
                if ($host === '' || !@$r->connect($host, $port, 1.5)) return false;
                $pass = getenv('REDIS_PASSWORD') ?: '';
                if ($pass !== '') $r->auth($pass);
            }
            $cfg = is_file(dirname(__DIR__, 2) . '/config/session.php') ? require dirname(__DIR__, 2) . '/config/session.php' : [];
            $this->ttl = max(60, ((int)($cfg['lifetime_minutes'] ?? 120)) * 60);
            $this->redis = $r;
            return true;
        } catch (\Throwable $e) { return false; }
    }
    public function close(): bool { return true; }
    public function read($id): string|false {
        try {
            $v = $this->redis ? $this->redis->get('sess:' . $id) : false;
            return is_string($v) ? $v : '';
        } catch (\Throwable $e) { return ''; }
    }
    public function write($id, $data): bool {
        try {
            return $this->redis ? (bool)$this->redis->setex('sess:' . $id, $this->ttl, $data) : false;
        } catch (\Throwable $e) { return false; }
    }
    public function destroy($id): bool {
        try { if ($this->redis) $this->redis->del('sess:' . $id); } catch (\Throwable $e) {}
        return true;
    }
    public function gc($max_lifetime): int|false { return 0; }
}
