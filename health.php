<?php
header('Content-Type: text/plain');
echo "PHP Version: " . PHP_VERSION . "\n";
echo "mysqli extension loaded: " . (extension_loaded('mysqli') ? 'YES' : 'NO') . "\n";
echo "session extension loaded: " . (extension_loaded('session') ? 'YES' : 'NO') . "\n";
echo "PORT env: " . (getenv('PORT') ?: 'NOT SET') . "\n";
echo "MYSQLHOST: " . (getenv('MYSQLHOST') ?: 'NOT SET') . "\n";
echo "MYSQLUSER: " . (getenv('MYSQLUSER') ?: 'NOT SET') . "\n";
echo "MYSQLDATABASE: " . (getenv('MYSQLDATABASE') ?: 'NOT SET') . "\n";
echo "MYSQLPORT: " . (getenv('MYSQLPORT') ?: 'NOT SET') . "\n";
echo "MYSQL_URL: " . (getenv('MYSQL_URL') ? 'IS SET' : 'NOT SET') . "\n";
echo "MYSQL_PRIVATE_URL: " . (getenv('MYSQL_PRIVATE_URL') ? 'IS SET' : 'NOT SET') . "\n";
