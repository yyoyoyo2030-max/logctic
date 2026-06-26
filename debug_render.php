<?php
header('Content-Type: text/plain; charset=utf-8');
echo "GIT LOG:\n";
system("git log -n 5 --oneline");
echo "\n\nCONFIG.PHP TOP:\n";
system("head -n 50 config/config.php");
echo "\n\nPHP VERSION:\n" . phpversion();
echo "\n\nFUNCTION EXISTS: " . (function_exists('getPosthogSetting') ? 'YES' : 'NO');
