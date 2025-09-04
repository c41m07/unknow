<?php
// config.php
ini_set('display_errors', getenv('DISPLAY_ERRORS') ?: 1);
error_reporting(getenv('ERROR_REPORTING') ?: E_ALL);

$DB_HOST = getenv('DB_HOST') ?: "localhost";
$DB_USER = getenv('DB_USER') ?: "root";
$DB_PASS = getenv('DB_PASS') ?: null;
$DB_NAME = getenv('DB_NAME') ?: "unknow";

// adapte ce chemin à ton installation (note le slash initial)
$BASE_PATH = getenv('BASE_PATH') ?: "/unknow/public";


if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
