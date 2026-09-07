<?php
namespace App\Domain;
class Playground {
    public static function tasks(): array {
        return [
            ['slug' => 'php-diskon', 'lang' => 'php', 'title' => 'Hitung diskon', 'skill' => 'PHP', 'xp' => 15, 'starter' => '$harga = 100000;' . "\n" . '$diskon = 10;' . "\n" . 'echo $harga - ($harga * $diskon / 100);', 'expected' => '90000', 'hint' => 'Output = harga - (harga * diskon / 100).'],
            ['slug' => 'php-ongkir', 'lang' => 'php', 'title' => 'Ongkir gratis', 'skill' => 'PHP', 'xp' => 15, 'starter' => '$total = 150000;' . "\n" . 'echo $total >= 100000 ? "GRATIS" : "BAYAR";', 'expected' => 'GRATIS', 'hint' => 'Ternary: kondisi ? A : B.'],
            ['slug' => 'js-loop', 'lang' => 'js', 'title' => 'Loop total', 'skill' => 'PHP', 'xp' => 10, 'starter' => 'let t = 0;' . "\n" . 'for (let i = 1; i <= 5; i++) t += i;' . "\n" . 'console.log(t);', 'expected' => '15', 'hint' => 'Jalankan di browser, tempel output.'],
            ['slug' => 'sql-stok', 'lang' => 'sql', 'title' => 'Filter produk', 'skill' => 'MySQL', 'xp' => 15, 'starter' => 'stok > 10', 'expected' => '2,3', 'hint' => 'Dataset: id1 stok5, id2 stok20, id3 stok15. Tulis kondisi WHERE tanpa kata WHERE.'],
        ];
    }
    public static function find(string $slug): ?array {
        foreach (self::tasks() as $t) if ($t['slug'] === $slug) return $t;
        return null;
    }
    public static function safePhpOutput(string $code): array {
        $c = trim($code);
        if ($c === '') return ['ok' => false, 'output' => '', 'msg' => 'Kode kosong.'];
        if (strlen($c) > 2000) return ['ok' => false, 'output' => '', 'msg' => 'Kode terlalu panjang.'];
        $low = strtolower($c);
        $banned = ['function', 'class', 'eval', 'exec', 'system', 'shell', 'passthru', 'popen', 'proc_', 'include', 'require', 'namespace', 'use ', 'echo(', '`', '->', '::', '$this', '$_', '$GLOBALS', 'define', 'declare'];
        foreach ($banned as $b) if (strpos($low, $b) !== false) return ['ok' => false, 'output' => '', 'msg' => 'Perintah tidak diizinkan: ' . $b];
        if (preg_match('/[a-zA-Z_][a-zA-Z0-9_]*\s*\(/', $c, $m)) {
            $allowedFn = ['intval', 'floatval', 'strval'];
            $fn = strtolower(trim(explode('(', $m[0])[0]));
            if (!in_array($fn, $allowedFn, true)) return ['ok' => false, 'output' => '', 'msg' => 'Fungsi tidak diizinkan.'];
        }
        if (!preg_match('/^[\$\w\s\.\+\-\*\/\%\(\)\=\;\,\?\:\>\<\!\&\|\'\"\[\]]+$/', $c)) return ['ok' => false, 'output' => '', 'msg' => 'Karakter tidak diizinkan.'];
        $vars = [];
        $out = '';
        $stmts = array_filter(array_map('trim', explode(';', $c)));
        if (count($stmts) > 12) return ['ok' => false, 'output' => '', 'msg' => 'Maks 12 pernyataan.'];
        foreach ($stmts as $st) {
            if (preg_match('/^(echo|print)\s+(.+)$/is', $st, $m)) {
                $v = self::evalExpr($m[2], $vars);
                if (!$v['ok']) return $v;
                $out .= (string)$v['value'];
            } elseif (preg_match('/^(\$[a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*(.+)$/s', $st, $m)) {
                $v = self::evalExpr($m[2], $vars);
                if (!$v['ok']) return $v;
                $vars[$m[1]] = $v['value'];
            } else {
                return ['ok' => false, 'output' => '', 'msg' => 'Hanya $var = ... dan echo didukung.'];
            }
        }
        return ['ok' => true, 'output' => $out, 'msg' => 'ok'];
    }
    private static function evalExpr(string $e, array $vars): array {
        $e = trim($e);
        if (preg_match('/^"(.*)"$/s', $e, $m) || preg_match("/^'(.*)'$/s", $e, $m)) return ['ok' => true, 'value' => $m[1]];
        if (is_numeric($e)) return ['ok' => true, 'value' => $e + 0];
        if (preg_match('/^\$[a-zA-Z_][a-zA-Z0-9_]*$/', $e)) return ['ok' => true, 'value' => $vars[$e] ?? 0];
        if (preg_match('/^(.+)\?(.+):(.+)$/s', $e, $m)) {
            $c = self::evalCond($m[1], $vars);
            if (!$c['ok']) return $c;
            return self::evalExpr($c['value'] ? $m[2] : $m[3], $vars);
        }
        $tokens = self::tokenize($e, $vars);
        if (!$tokens['ok']) return $tokens;
        $rpn = self::toRpn($tokens['tokens']);
        if (!$rpn['ok']) return $rpn;
        return self::runRpn($rpn['rpn']);
    }
    private static function evalCond(string $e, array $vars): array {
        foreach (['>=', '<=', '==', '!=', '>', '<'] as $op) {
            $p = strpos($e, $op);
            if ($p !== false) {
                $l = self::evalExpr(substr($e, 0, $p), $vars);
                $r = self::evalExpr(substr($e, $p + strlen($op)), $vars);
                if (!$l['ok']) return $l;
                if (!$r['ok']) return $r;
                $a = $l['value']; $b = $r['value'];
                $res = $op === '>=' ? $a >= $b : ($op === '<=' ? $a <= $b : ($op === '==' ? $a == $b : ($op === '!=' ? $a != $b : ($op === '>' ? $a > $b : $a < $b))));
                return ['ok' => true, 'value' => $res];
            }
        }
        $v = self::evalExpr($e, $vars);
        if (!$v['ok']) return $v;
        return ['ok' => true, 'value' => (bool)$v['value']];
    }
    private static function tokenize(string $e, array $vars): array {
        $out = [];
        $i = 0; $n = strlen($e);
        while ($i < $n) {
            $ch = $e[$i];
            if (ctype_space($ch)) { $i++; continue; }
            if ($ch === '$') {
                $j = $i + 1;
                while ($j < $n && preg_match('/[a-zA-Z0-9_]/', $e[$j])) $j++;
                $name = substr($e, $i, $j - $i);
                $out[] = ['t' => 'num', 'v' => (float)($vars[$name] ?? 0)];
                $i = $j; continue;
            }
            if (ctype_digit($ch) || ($ch === '.' && $i + 1 < $n && ctype_digit($e[$i + 1]))) {
                $j = $i;
                while ($j < $n && (ctype_digit($e[$j]) || $e[$j] === '.')) $j++;
                $out[] = ['t' => 'num', 'v' => (float)substr($e, $i, $j - $i)];
                $i = $j; continue;
            }
            if ($ch === '"' || $ch === "'") {
                $q = $ch; $j = $i + 1; $s = '';
                while ($j < $n && $e[$j] !== $q) { $s .= $e[$j]; $j++; }
                $out[] = ['t' => 'str', 'v' => $s];
                $i = $j + 1; continue;
            }
            if (strpos('+-*/%().', $ch) !== false) { $out[] = ['t' => 'op', 'v' => $ch]; $i++; continue; }
            return ['ok' => false, 'output' => '', 'msg' => 'Ekspresi tidak didukung di: ' . $ch];
        }
        return ['ok' => true, 'tokens' => $out];
    }
    private static function toRpn(array $toks): array {
        $out = []; $st = [];
        $prec = ['+' => 1, '-' => 1, '*' => 2, '/' => 2, '%' => 2, '.' => 2];
        foreach ($toks as $t) {
            if ($t['t'] !== 'op') { $out[] = $t; continue; }
            $o = $t['v'];
            if ($o === '(') { $st[] = $o; continue; }
            if ($o === ')') {
                while ($st && end($st) !== '(') $out[] = ['t' => 'op', 'v' => array_pop($st)];
                if (!$st) return ['ok' => false, 'output' => '', 'msg' => 'Kurung tidak seimbang.'];
                array_pop($st); continue;
            }
            while ($st && end($st) !== '(' && ($prec[end($st)] ?? 0) >= ($prec[$o] ?? 0)) $out[] = ['t' => 'op', 'v' => array_pop($st)];
            $st[] = $o;
        }
        while ($st) { $o = array_pop($st); if ($o === '(') return ['ok' => false, 'output' => '', 'msg' => 'Kurung tidak seimbang.']; $out[] = ['t' => 'op', 'v' => $o]; }
        return ['ok' => true, 'rpn' => $out];
    }
    private static function runRpn(array $rpn): array {
        $st = [];
        foreach ($rpn as $t) {
            if ($t['t'] !== 'op') { $st[] = $t['v']; continue; }
            if (count($st) < 2) return ['ok' => false, 'output' => '', 'msg' => 'Ekspresi tidak lengkap.'];
            $b = array_pop($st); $a = array_pop($st);
            switch ($t['v']) {
                case '+': $st[] = is_numeric($a) && is_numeric($b) ? $a + $b : $a . $b; break;
                case '-': $st[] = $a - $b; break;
                case '*': $st[] = $a * $b; break;
                case '/': if ((float)$b == 0.0) return ['ok' => false, 'output' => '', 'msg' => 'Bagi nol.']; $st[] = $a / $b; break;
                case '%': $st[] = (int)$a % (int)$b; break;
                case '.': $st[] = (string)$a . (string)$b; break;
                default: return ['ok' => false, 'output' => '', 'msg' => 'Operator tak dikenal.'];
            }
        }
        if (count($st) !== 1) return ['ok' => false, 'output' => '', 'msg' => 'Ekspresi tidak valid.'];
        $v = $st[0];
        if (is_float($v) && floor($v) == $v) $v = (int)$v;
        return ['ok' => true, 'value' => $v];
    }
    public static function dataset(): array {
        return [
            ['id' => 1, 'nama' => 'Kabel UTP', 'stok' => 5, 'harga' => 15000],
            ['id' => 2, 'nama' => 'Switch 8p', 'stok' => 20, 'harga' => 250000],
            ['id' => 3, 'nama' => 'Router', 'stok' => 15, 'harga' => 45000],
        ];
    }
    public static function filterIds(string $where): array {
        $rows = self::dataset();
        $out = [];
        foreach ($rows as $r) {
            $ok = self::rowMatch($where, $r);
            if ($ok) $out[] = (int)$r['id'];
        }
        sort($out);
        return $out;
    }
    private static function rowMatch(string $where, array $row): bool {
        $w = trim($where);
        if ($w === '') return true;
        $w = preg_replace('/^\s*where\s+/i', '', $w);
        $ors = preg_split('/\s+or\s+/i', $w);
        foreach ($ors as $o) {
            $ands = preg_split('/\s+and\s+/i', $o);
            $all = true;
            foreach ($ands as $a) {
                if (!self::condMatch(trim($a), $row)) { $all = false; break; }
            }
            if ($all) return true;
        }
        return false;
    }
    private static function condMatch(string $c, array $row): bool {
        if (!preg_match('/^(stok|harga|id)\s*(>=|<=|==|!=|>|<|=)\s*(\d+)$/i', $c, $m)) return false;
        $col = strtolower($m[1]); $op = $m[2]; $val = (int)$m[3];
        if ($op === '=') $op = '==';
        $a = (int)($row[$col] ?? 0);
        return $op === '>=' ? $a >= $val : ($op === '<=' ? $a <= $val : ($op === '==' ? $a == $val : ($op === '!=' ? $a != $val : ($op === '>' ? $a > $val : $a < $val))));
    }
    public static function grade(string $slug, string $input): bool {
        $t = self::find($slug);
        if (!$t) return false;
        $input = trim($input);
        if (($t['lang'] ?? '') === 'php') {
            $r = self::safePhpOutput($input);
            if (!$r['ok']) return false;
            return trim((string)$r['output']) === trim((string)$t['expected']);
        }
        if (($t['lang'] ?? '') === 'sql') {
            return implode(',', self::filterIds($input)) === (string)$t['expected'];
        }
        return $input === (string)$t['expected'];
    }
}
