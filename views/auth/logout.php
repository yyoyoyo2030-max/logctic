<?php
require_once '../../config/config.php';

session_destroy();
redirect('views/auth/login.php');
?>
