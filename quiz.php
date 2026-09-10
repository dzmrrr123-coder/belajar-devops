<?php
require_once 'config.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    http_response_code(307);
    header('Location: lab.php?tab=kuis');
    exit();
}
$q = ['tab' => 'kuis'];
foreach (['mode', 'ids', 'i', 'done', 'topic'] as $k) if (isset($_GET[$k])) $q[$k] = $_GET[$k];
redirect('lab.php?' . http_build_query($q));
