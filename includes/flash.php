<?php
function flash_set($key, $message){ $_SESSION["flash"][$key] = $message; }
function flash_get($key){
    if (!empty($_SESSION["flash"][$key])) {
        $m = $_SESSION["flash"][$key]; unset($_SESSION["flash"][$key]); return $m;
    }
    return null;
}
