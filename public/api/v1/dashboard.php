<?php
require_once dirname(__DIR__,3).'/config.php';
require_login();
header('Content-Type: application/json');
$conn = db_connect();
$uid = (int)$_SESSION['user_id'];
$week = max(1, min(12, (int)($_GET['week'] ?? 1)));
$data = \App\Cache\Store::remember("dash:{$uid}:w{$week}", \App\Cache\Keys::DASHBOARD_TTL, function () use ($conn, $uid, $week) {
    $svc = new App\Domain\Quest\QuestService($conn);
    return $svc->dashboard($uid, $week);
});
echo json_encode(['status'=>'success','week'=>$week,'data'=>$data]);
