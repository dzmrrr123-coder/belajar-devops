<?php
namespace App;
class Db {
    private static ?\mysqli $write = null;
    private static ?\mysqli $read = null;
    public static function config(): array {
        $cfg = is_file(dirname(__DIR__) . '/config/database.php') ? require dirname(__DIR__) . '/config/database.php' : [];
        $host = $cfg['host'] ?? 'localhost'; $port = (int)($cfg['port'] ?? 3306);
        $user = $cfg['user'] ?? 'root'; $pass = $cfg['pass'] ?? ''; $name = $cfg['name'] ?? 'railway';
        // Samakan prioritas dengan config.php: env eksplisit (DB_*) menang;
        // URL koneksi hanya mengisi field yang masih kosong/default.
        $url = getenv('MYSQL_URL') ?: (getenv('MYSQL_PRIVATE_URL') ?: getenv('DATABASE_URL'));
        // Apakah koneksi dikonfigurasi eksplisit lewat env DB_*? Jika ya, URL tidak boleh
        // menimpa apa pun (termasuk password kosong yang sah untuk MySQL lokal).
        $explicit = getenv('DB_HOST') !== false || getenv('MYSQLHOST') !== false;
        if (!$explicit && $url && ($p = parse_url($url))) {
            if (self::isDefault($host, ['localhost', ''])) $host = $p['host'] ?? $host;
            if (isset($p['port']) && (int)($cfg['port'] ?? 0) === 3306) $port = (int)$p['port'];
            if (self::isDefault($user, ['root', ''])) $user = $p['user'] ?? $user;
            if ((string)$pass === '' && isset($p['pass'])) $pass = $p['pass'];
            if (self::isDefault($name, ['railway', '']) && isset($p['path'])) $name = ltrim($p['path'], '/');
        }
        if ($host === 'localhost' && getenv('RAILWAY_TCP_PROXY_DOMAIN')) $host = getenv('RAILWAY_TCP_PROXY_DOMAIN');
        return ['host' => $host, 'port' => $port, 'user' => $user, 'pass' => $pass, 'name' => $name];
    }

    private static function isDefault(string $v, array $defaults): bool {
        return in_array($v, $defaults, true);
    }
    public static function connect(): \mysqli {
        return self::connectWrite();
    }
    public static function connectWrite(): \mysqli {
        if (self::$write instanceof \mysqli) {
            try { if (@self::$write->ping()) return self::$write; } catch (\Throwable $e) {}
            self::$write = null;
        }
        self::$write = self::open(self::config());
        return self::$write;
    }
    public static function connectRead(): \mysqli {
        $readHost = getenv('DB_READ_HOST') ?: '';
        if ($readHost === '') {
            if (self::$read instanceof \mysqli) {
                try { if (@self::$read->ping()) return self::$read; } catch (\Throwable $e) {}
            }
            self::$read = self::connectWrite();
            return self::$read;
        }
        if (self::$read instanceof \mysqli) {
            try { if (@self::$read->ping()) return self::$read; } catch (\Throwable $e) {}
            self::$read = null;
        }
        $cfg = self::config();
        $cfg['host'] = $readHost;
        $cfg['port'] = (int)(getenv('DB_READ_PORT') ?: $cfg['port']);
        self::$read = self::open($cfg);
        return self::$read;
    }
    public static function reset(): void {
        self::$write = null;
        self::$read = null;
    }
    private static function open(array $cfg): \mysqli {
        $c = mysqli_init();
        if (!$c) throw new \Exception("Gagal menginisialisasi MySQLi driver.");
        $c->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
        if (!@$c->real_connect($cfg['host'], $cfg['user'], $cfg['pass'], $cfg['name'], $cfg['port'])) {
            throw new \Exception($c->connect_error ?: "MySQL connect failed");
        }
        $c->set_charset('utf8mb4');
        return $c;
    }
}
