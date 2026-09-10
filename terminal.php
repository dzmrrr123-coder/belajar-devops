<?php
require_once 'config.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    http_response_code(307);
    header('Location: lab.php?tab=praktik&alat=terminal');
    exit();
}
$q = ['tab' => 'praktik', 'alat' => 'terminal'];
if (isset($_GET['m'])) $q['tm_m'] = $_GET['m'];
redirect('lab.php?' . http_build_query($q));
