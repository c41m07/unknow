<?php
require_once __DIR__ . "/auth.php";
if (!isset($_SESSION["user_id"])) {
    require_once __DIR__ . "/../config.php";
    header("Location: {$BASE_PATH}/login.php");
    exit;
}
