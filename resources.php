<?php
require_once 'config.php';
$q = ['tab' => 'materi'];
if (isset($_GET['track'])) $q['track'] = $_GET['track'];
if (isset($_GET['week'])) $q['mweek'] = (int)$_GET['week'];
redirect('quests.php?' . http_build_query($q));
