<?php
require_once 'config.php';
$conn = db_connect();
$fid = (int)($_GET['id'] ?? 0);
$viewer = (int)($_SESSION['user_id'] ?? 0);
$row = null;
try {
    $s = $conn->prepare("SELECT owner_id, path, mime FROM submission_files WHERE id = ?");
    if ($s) { $s->bind_param("i", $fid); $s->execute(); $row = $s->get_result()->fetch_assoc(); $s->close(); }
} catch (Throwable $e) {}
if (!$row || !\App\Domain\Dkv\Karya::canView($conn, $viewer, (int)$row['owner_id'])) { $conn->close(); http_response_code(404); exit(); }
$conn->close();
$file = \App\Domain\Dkv\Karya::dir() . '/' . basename((string)$row['path']);
if (!is_file($file)) { http_response_code(404); exit(); }
header('Content-Type: ' . (string)$row['mime']);
header('Content-Length: ' . filesize($file));
header('Cache-Control: public, max-age=86400');
readfile($file);
exit();
