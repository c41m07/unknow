<?php
require_once "../includes/auth.php";
require_once "../config.php";
logoutUser();
header("Location: {$BASE_PATH}/index.php");
exit;
