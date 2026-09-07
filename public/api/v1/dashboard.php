<?php
require_once dirname(__DIR__,3).'/config.php';
require_login();
header('Content-Type: application/json');
$conn = db_connect();
$uid = (int)$_SESSION['user_id'];
$week = max(1, min(12, (int)($_GET['week'] ?? 1)));
$svc = new App\Domain\Quest\QuestService($conn);
echo json_encode(['status'=>'success','week'=>$week,'data'=>$svc->dashboard($uid,$week)]);
