<?php
require_once __DIR__ . "/auth.php";
require_once __DIR__ . "/flash.php";

function currentUser(): ?array {
    if (!isset($_SESSION["user_id"])) return null;
    $pdo = db();
    $stmt = $pdo->prepare("SELECT id, email, username, created_at FROM users WHERE id = ?");
    $stmt->execute([$_SESSION["user_id"]]);
    return $stmt->fetch() ?: null;
}

function updateUsername(int $userId, string $username): array {
    $username = trim($username);
    if ($username !== "" && !preg_match('/^[a-z0-9_.-]{3,30}$/i', $username))
        return ["ok"=>false,"error"=>"Le pseudo doit faire 3–30 caractères (lettres, chiffres, _ . -)"];
    try {
        $pdo = db();
        if ($username === "") $username = null;
        if ($username !== null) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id <> ?");
            $stmt->execute([$username, $userId]);
            if ($stmt->fetch()) return ["ok"=>false,"error"=>"Ce pseudo est déjà pris"];
        }
        $pdo->prepare("UPDATE users SET username = ? WHERE id = ?")->execute([$username, $userId]);
        return ["ok"=>true];
    } catch (Throwable $e) { return ["ok"=>false,"error"=>"Erreur : ".$e->getMessage()]; }
}

function updateEmail(int $userId, string $email): array {
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ["ok"=>false,"error"=>"Email invalide"];
    try {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id <> ?");
        $stmt->execute([$email, $userId]);
        if ($stmt->fetch()) return ["ok"=>false,"error"=>"Cet email est déjà utilisé"];
        $pdo->prepare("UPDATE users SET email = ? WHERE id = ?")->execute([$email, $userId]);
        return ["ok"=>true];
    } catch (Throwable $e) { return ["ok"=>false,"error"=>"Erreur : ".$e->getMessage()]; }
}

function updatePassword(int $userId, string $current, string $next, string $confirm): array {
    if (strlen($next) < 8) return ["ok"=>false,"error"=>"Mot de passe trop court (min 8)"];
    if ($next !== $confirm) return ["ok"=>false,"error"=>"La confirmation ne correspond pas"];
    $pdo = db();
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($current, $row["password"]))
        return ["ok"=>false,"error"=>"Mot de passe actuel incorrect"];
    $hash = password_hash($next, PASSWORD_BCRYPT, ["cost"=>12]);
    $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $userId]);
    return ["ok"=>true];
}
function deleteAccount(int $userId, string $password): array {
    try {
        $pdo = db();

        // Détecter la colonne de mot de passe disponible
        $col = null;
        $chk = $pdo->query("SHOW COLUMNS FROM users LIKE 'password_hash'")->fetch();
        if ($chk) { $col = 'password_hash'; }
        if (!$col) {
            $chk2 = $pdo->query("SHOW COLUMNS FROM users LIKE 'password'")->fetch();
            if ($chk2) { $col = 'password'; }
        }
        if (!$col) return ["ok"=>false, "error"=>"Aucune colonne mot de passe trouvée dans users (password_hash ou password)."];

        // Récupérer l'utilisateur et le mot de passe stocké
        $st = $pdo->prepare("SELECT id, $col AS pwd FROM users WHERE id=?");
        $st->execute([$userId]);
        $u = $st->fetch();
        if (!$u) return ["ok"=>false, "error"=>"Utilisateur introuvable."];

        $stored = (string)($u['pwd'] ?? '');

        // Vérification principale (hash moderne)
        $ok = $stored !== '' && password_verify($password, $stored);

        // Tolérance legacy : si ce n’est pas un hash moderne, on tente l’égalité simple ou md5
        if (!$ok) {
            // égalité stricte (si ancien stockage en clair – à éviter en prod)
            if (hash_equals($stored, $password)) $ok = true;
            // ancien stockage en md5 éventuel
            if (!$ok && hash_equals($stored, md5($password))) $ok = true;
        }

        if (!$ok) return ["ok"=>false, "error"=>"Mot de passe incorrect."];

        $pdo->beginTransaction();

        // Récupérer toutes les planètes de l'utilisateur
        $pstmt = $pdo->prepare("SELECT id FROM planets WHERE user_id=? FOR UPDATE");
        $pstmt->execute([$userId]);
        $planetIds = array_map(fn($r)=>(int)$r['id'], $pstmt->fetchAll());

        if (!empty($planetIds)) {
            $in = implode(',', array_fill(0, count($planetIds), '?'));
            $pdo->prepare("DELETE FROM build_queue WHERE planet_id IN ($in)")->execute($planetIds);
            $pdo->prepare("DELETE FROM planet_buildings WHERE planet_id IN ($in)")->execute($planetIds);
            $pdo->prepare("DELETE FROM planets WHERE id IN ($in)")->execute($planetIds);
        }

        $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$userId]);

        $pdo->commit();
        return ["ok"=>true];

    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        return ["ok"=>false, "error"=>"Erreur: ".$e->getMessage()];
    }
}