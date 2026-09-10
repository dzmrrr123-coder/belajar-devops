<?php
require_once 'config.php';
$q = [];
if (isset($_GET['week'])) $q['week'] = max(1, min(12, (int)$_GET['week']));
redirect('quests.php' . ($q ? '?' . http_build_query($q) : ''));
