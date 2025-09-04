<?php
require_once __DIR__ . "/../config.php";
function db(): PDO {
    static $pdo=null;
    if ($pdo instanceof PDO) return $pdo;
    $pdo = new PDO(
        "mysql:host={$GLOBALS['DB_HOST']};dbname={$GLOBALS['DB_NAME']};charset=utf8",
        $GLOBALS['DB_USER'],
        $GLOBALS['DB_PASS'],
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]
    );
    return $pdo;
}
