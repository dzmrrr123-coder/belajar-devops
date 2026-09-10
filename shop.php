<?php
require_once 'config.php';
$q = ($_GET['tab'] ?? 'hadiah') === 'voucher' ? '?shoptab=voucher#shop' : '#shop';
set_flash('info', 'Toko XP pindah ke Profil.');
redirect('profile.php' . $q);
