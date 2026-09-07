<?php
namespace App\Domain\Tkj;
class Terminal {
    public static function missions(): array {
        return [
            ['slug' => 'fix-www', 'title' => 'Perbaiki /var/www', 'skill' => 'Linux', 'xp' => 15, 'goal' => 'chown ke www-data lalu chmod 755 sehingga upload bisa.', 'done' => ['chowned' => true, 'mod_ok' => true]],
            ['slug' => 'kunci-ssh', 'title' => 'Kunci SSH', 'skill' => 'Linux', 'xp' => 15, 'goal' => 'Matikan PasswordAuthentication dan restart sshd.', 'done' => ['key_only' => true, 'restarted' => true]],
            ['slug' => 'cek-port', 'title' => 'Buka port web', 'skill' => 'Networking', 'xp' => 10, 'goal' => 'Allow 80,443 dan deny 3306 dari publik.', 'done' => ['web_open' => true, 'db_closed' => true]],
        ];
    }
    public static function find(string $slug): ?array {
        foreach (self::missions() as $m) if ($m['slug'] === $slug) return $m;
        return null;
    }
    public static function exec(array $st, string $cmd): array {
        $cmd = trim($cmd);
        $st = array_merge(['pwd' => '/home/siswa', 'chowned' => false, 'mod_ok' => false, 'key_only' => false, 'restarted' => false, 'web_open' => false, 'db_closed' => false, 'log' => []], $st);
        if ($cmd === '') return ['state' => $st, 'out' => ''];
        $parts = preg_split('/\s+/', $cmd);
        $bin = strtolower($parts[0] ?? '');
        $arg = implode(' ', array_slice($parts, 1));
        $out = '';
        switch ($bin) {
            case 'pwd': $out = $st['pwd']; break;
            case 'ls': $out = $st['pwd'] === '/var/www' ? 'index.php  Order.php  uploads/' : 'catatan.txt  lab/  app/'; break;
            case 'cd': $st['pwd'] = $arg === '/var/www' ? '/var/www' : ($arg === '..' ? '/home' : $st['pwd']); $out = $st['pwd']; break;
            case 'cat': $out = strpos($arg, 'sshd_config') !== false ? ($st['key_only'] ? 'PasswordAuthentication no' : 'PasswordAuthentication yes') : 'isi file: ' . $arg; break;
            case 'chown': if (strpos($arg, 'www-data') !== false && strpos($arg, '/var/www') !== false) { $st['chowned'] = true; $out = 'owner → www-data'; } else $out = 'contoh: chown -R www-data:www-data /var/www'; break;
            case 'chmod': if (strpos($arg, '755') !== false) { $st['mod_ok'] = $st['chowned'] ? true : false; $out = $st['chowned'] ? 'mode 755 ok' : 'chown dulu ke www-data'; } else $out = 'jangan 777. pakai 755.'; break;
            case 'sed': if (strpos($arg, 'PasswordAuthentication no') !== false) { $st['key_only'] = true; $out = 'sshd_config diperbarui'; } else $out = 'contoh: sed -i s/yes/no/ /etc/ssh/sshd_config'; break;
            case 'systemctl': if (strpos($arg, 'restart sshd') !== false || strpos($arg, 'restart ssh') !== false) { $st['restarted'] = $st['key_only']; $out = $st['key_only'] ? 'sshd restart ok' : 'kunci dulu PasswordAuthentication'; } else $out = 'systemctl restart sshd'; break;
            case 'ufw': $a = strtolower($arg); if (strpos($a, 'allow 80') !== false || strpos($a, 'allow 443') !== false) { $st['web_open'] = true; $out = 'port web dibuka'; } elseif (strpos($a, 'deny 3306') !== false) { $st['db_closed'] = true; $out = 'db ditutup dari publik'; } else $out = 'contoh: ufw allow 80/tcp'; break;
            case 'ping': $out = '64 bytes from 8.8.8.8: time=12ms'; break;
            case 'ip': $out = 'eth0: 192.168.1.20/24'; break;
            case 'whoami': $out = 'siswa'; break;
            case 'help': $out = 'pwd ls cd cat chown chmod sed systemctl ufw ping ip whoami'; break;
            default: $out = 'unknown: ' . $bin . ' (ketik help)';
        }
        $st['log'][] = ['$ ' . $cmd, $out];
        if (count($st['log']) > 40) $st['log'] = array_slice($st['log'], -40);
        return ['state' => $st, 'out' => $out];
    }
    public static function missionDone(array $st, array $m): bool {
        foreach ((array)($m['done'] ?? []) as $k => $v) if (empty($st[$k])) return false;
        return true;
    }
}
