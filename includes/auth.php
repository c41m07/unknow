<?php
require_once __DIR__ . "/db.php";

function registerUser(string $email, string $password): array {
    try {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ["ok"=>false,"error"=>"Email invalide"];
        if (strlen($password) < 8) return ["ok"=>false,"error"=>"Mot de passe trop court (min 8)"];

        $pdo = db();
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) return ["ok"=>false,"error"=>"Email déjà utilisé"];

        $hash = password_hash($password, PASSWORD_BCRYPT, ["cost"=>12]);
        $pdo->prepare("INSERT INTO users (email, password) VALUES (?, ?)")->execute([$email, $hash]);
        return ["ok"=>true];
    } catch (Throwable $e) {
        return ["ok"=>false,"error"=>"Erreur serveur : ".$e->getMessage()];
    }
}

function loginUser(string $email, string $password): bool {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT id, password FROM users WHERE email = ?");
    $stmt->execute([strtolower(trim($email))]);
    $u = $stmt->fetch();
    if ($u && password_verify($password, $u["password"])) {
        $_SESSION["user_id"] = (int)$u["id"];
        return true;
    }
    return false;
}
function isLoggedIn(): bool { return isset($_SESSION["user_id"]); }
function logoutUser(): void { session_destroy(); }
