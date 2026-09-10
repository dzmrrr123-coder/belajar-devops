<?php
require_once 'config.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    http_response_code(307);
    header('Location: lab.php?tab=praktik&alat=playground');
    exit();
}
$q = ['tab' => 'praktik', 'alat' => 'playground'];
if (isset($_GET['slug'])) $q['pg_slug'] = $_GET['slug'];
redirect('lab.php?' . http_build_query($q));
