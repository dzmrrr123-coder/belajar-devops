<?php
namespace App\Http;
use App\Cache\Store;
class RateLimit {
    /** Ambil IP klien yang bisa dipercaya.
     *  X-Forwarded-For HANYA dipercaya jika REMOTE_ADDR termasuk proxy tepercaya
     *  (env TRUSTED_PROXIES, daftar IP/CIDR dipisah koma). Kalau tidak, pakai REMOTE_ADDR. */
    private static function clientIp(): string {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '';
        $trusted = self::trustedProxies();
        if ($remote !== '' && in_array($remote, $trusted, true)) {
            $fwd = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
            if ($fwd !== '') {
                $ips = array_map('trim', explode(',', $fwd));
                // Ambil dari kanan (paling dekat proxy) yang bukan proxy tepercaya → tak bisa dipalsukan klien.
                for ($i = count($ips) - 1; $i >= 0; $i--) {
                    if (filter_var($ips[$i], FILTER_VALIDATE_IP) && !in_array($ips[$i], $trusted, true)) {
                        return $ips[$i];
                    }
                }
            }
            if (!empty($_SERVER['HTTP_X_REAL_IP']) && filter_var($_SERVER['HTTP_X_REAL_IP'], FILTER_VALIDATE_IP)) {
                return $_SERVER['HTTP_X_REAL_IP'];
            }
        }
        if (filter_var($remote, FILTER_VALIDATE_IP)) return $remote;
        return 'unknown';
    }

    private static function trustedProxies(): array {
        $raw = (string)(getenv('TRUSTED_PROXIES') ?: '');
        if ($raw === '') return [];
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    private static function slug(string $s): string {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '_', $s) ?? '';
    }

    /** Hit rate limiter.
     *  - $identifier null  → dibatasi per sesi (perilaku lama, dipakai semua rate_limit_hit()).
     *  - $identifier diisi → dibatasi per IP + identifier (mis. IP + username untuk login).
     *  Return true = sudah melewati batas (harus diblokir). */
    public static function hit(string $key, int $max, int $window, ?string $identifier = null): bool {
        $max = max(1, $max); $window = max(1, $window);
        $ip = self::clientIp();
        if ($identifier === null) {
            $id = session_id() ?: 'cli';
            $ck = 'rl:' . self::slug($key) . ':s:' . self::slug($id);
        } else {
            // Per IP + identifier (username), supaya brute-force per akun dan per IP sama-sama tertutup.
            $ck = 'rl:' . self::slug($key) . ':i:' . self::slug($ip) . ':' . self::slug($identifier);
        }
        $now = time();
        try {
            $got = Store::get($ck);
            if (!$got['hit'] || !is_array($got['value']) || ($now - (int)($got['value']['t'] ?? 0)) >= $window) {
                Store::set($ck, ['t' => $now, 'c' => 1], $window);
                return false;
            }
            $e = $got['value'];
            $e['c'] = (int)($e['c'] ?? 0) + 1;
            Store::set($ck, $e, $window);
            return $e['c'] > $max;
        } catch (\Throwable $e2) {
            return false;
        }
    }
}
