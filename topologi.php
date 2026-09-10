<?php
require_once 'config.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    http_response_code(307);
    header('Location: lab.php?tab=praktik&alat=topologi');
    exit();
}
$q = ['tab' => 'praktik', 'alat' => 'topologi'];
if (isset($_GET['cidr'])) $q['cidr'] = $_GET['cidr'];
redirect('lab.php?' . http_build_query($q));
