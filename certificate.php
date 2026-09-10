<?php
require_once 'config.php';
$code = trim($_GET['code'] ?? '');
redirect('profile.php' . ($code !== '' ? '#badges' : ''));
