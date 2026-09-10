<?php
require_once 'config.php';
$week = isset($_GET['week']) ? max(1, min(12, (int)$_GET['week'])) : 0;
if ($week > 0) redirect('quests.php?week=' . $week);
redirect('quests.php');
