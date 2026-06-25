<?php
require 'config/config.php';
$sql = file_get_contents('sql/whatsapp_custom_routes.sql');
$conn->exec($sql);
echo 'Migrated successfully!';
