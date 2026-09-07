<?php
namespace App\Domain\Dkv;
class Karya {
    public const MAX_BYTES = 3145728;
    public const ALLOWED = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    public static function dir(): string {
        $d = dirname(__DIR__, 2) . '/storage/uploads';
        if (!is_dir($d)) @mkdir($d, 0775, true);
        return $d;
    }
    public static function validate(array $file): array {
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) return ['ok' => false, 'msg' => 'File tidak terbaca.'];
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) return ['ok' => false, 'msg' => 'Upload gagal. Coba file lebih kecil.'];
        if ((int)($file['size'] ?? 0) > self::MAX_BYTES) return ['ok' => false, 'msg' => 'Maksimal 3 MB.'];
        $info = @getimagesize($file['tmp_name']);
        if (!$info || !isset(self::ALLOWED[$info['mime']])) return ['ok' => false, 'msg' => 'Hanya gambar JPG/PNG/WebP/GIF.'];
        return ['ok' => true, 'mime' => $info['mime'], 'ext' => self::ALLOWED[$info['mime']]];
    }
    public static function store(\mysqli $conn, int $ownerId, int $questId, array $file): array {
        $v = self::validate($file);
        if (!$v['ok']) return $v;
        try {
            $chk = $conn->prepare("SELECT q.id FROM quests q LEFT JOIN user_quests uq ON uq.quest_id = q.id AND uq.user_id = ? WHERE q.id = ? AND (q.user_id IS NULL OR q.user_id = ?)");
            if ($chk) {
                $chk->bind_param("iii", $ownerId, $questId, $ownerId); $chk->execute();
                if (!$chk->get_result()->fetch_assoc()) { $chk->close(); return ['ok' => false, 'msg' => 'Quest tidak ditemukan.']; }
                $chk->close();
            }
            $name = bin2hex(random_bytes(16)) . '.' . $v['ext'];
            $dest = self::dir() . '/' . $name;
            if (!@move_uploaded_file($file['tmp_name'], $dest)) return ['ok' => false, 'msg' => 'Gagal menyimpan file.'];
            try {
                $old = $conn->prepare("SELECT path FROM submission_files WHERE owner_id = ? AND quest_id = ?");
                if ($old) { $old->bind_param("ii", $ownerId, $questId); $old->execute(); $pr = $old->get_result()->fetch_assoc(); $old->close(); if ($pr) @unlink(self::dir() . '/' . basename((string)$pr['path'])); }
            } catch (\Throwable $e) {}
            $ins = $conn->prepare("INSERT INTO submission_files (owner_id, quest_id, path, mime, size) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE path = VALUES(path), mime = VALUES(mime), size = VALUES(size)");
            if (!$ins) { @unlink($dest); return ['ok' => false, 'msg' => 'Gagal mencatat.']; }
            $size = (int)($file['size'] ?? 0); $mime = $v['mime'];
            $ins->bind_param("iissi", $ownerId, $questId, $name, $mime, $size);
            if (!$ins->execute()) { $ins->close(); @unlink($dest); return ['ok' => false, 'msg' => 'Gagal mencatat.']; }
            $ins->close();
            return ['ok' => true, 'id' => (int)$conn->insert_id];
        } catch (\Throwable $e) { return ['ok' => false, 'msg' => 'Gagal menyimpan.']; }
    }
    public static function forQuests(\mysqli $conn, int $ownerId, array $questIds): array {
        $out = [];
        $questIds = array_values(array_unique(array_map('intval', $questIds)));
        if (!$questIds) return $out;
        try {
            $in = implode(',', $questIds);
            $r = $conn->query("SELECT id, quest_id, path, mime FROM submission_files WHERE owner_id = " . (int)$ownerId . " AND quest_id IN ($in)");
            if ($r) { foreach ($r->fetch_all(MYSQLI_ASSOC) as $row) $out[(int)$row['quest_id']] = $row; $r->free(); }
        } catch (\Throwable $e) {}
        return $out;
    }
    public static function gallery(\mysqli $conn, int $ownerId, int $limit = 12): array {
        $out = [];
        try {
            $s = $conn->prepare("SELECT f.id, f.quest_id, f.path, f.mime, f.created_at, q.title FROM submission_files f JOIN quests q ON q.id = f.quest_id WHERE f.owner_id = ? ORDER BY f.created_at DESC LIMIT ?");
            if (!$s) return $out;
            $s->bind_param("ii", $ownerId, $limit); $s->execute();
            $out = $s->get_result()->fetch_all(MYSQLI_ASSOC); $s->close();
        } catch (\Throwable $e) {}
        return $out;
    }
    public static function canView(\mysqli $conn, int $viewerId, int $ownerId): bool {
        if ($viewerId === $ownerId) return true;
        try {
            $s = $conn->prepare("SELECT public_profile FROM users WHERE id = ?");
            if (!$s) return false;
            $s->bind_param("i", $ownerId); $s->execute();
            $row = $s->get_result()->fetch_assoc(); $s->close();
            if (!empty($row['public_profile'])) return true;
            if ($viewerId > 0 && (\App\Domain\Auth\Roles::isAdmin($conn, $viewerId) || \App\Domain\Auth\Roles::isGuru($conn, $viewerId))) return true;
        } catch (\Throwable $e) {}
        return false;
    }
}
