<?php
require_once "../config.php";
require_once "../includes/require_login.php";
require_once "../includes/csrf.php";
require_once "../includes/flash.php";
require_once "../includes/user.php";

$user = currentUser();
if (!$user) { header("Location: {$BASE_PATH}/login.php"); exit; }

if ($_SERVER["REQUEST_METHOD"]==="POST") {
    if (!csrf_check($_POST["csrf"]??"")) { flash_set("error","Action expirée."); header("Location: {$BASE_PATH}/profile.php"); exit; }
    $action=$_POST["action"]??"";

    if ($action==="username") {
        $res=updateUsername((int)$user["id"], $_POST["username"]??"");
        $res["ok"] ? flash_set("success","Pseudo mis à jour.") : flash_set("error",$res["error"]??"Erreur pseudo.");
        header("Location: {$BASE_PATH}/profile.php"); exit;
    } elseif ($action==="email") {
        $res=updateEmail((int)$user["id"], $_POST["email"]??"");
        $res["ok"] ? flash_set("success","Email mis à jour.") : flash_set("error",$res["error"]??"Erreur email.");
        header("Location: {$BASE_PATH}/profile.php"); exit;
    } elseif ($action==="password") {
        $res=updatePassword((int)$user["id"], $_POST["current"]??"", $_POST["next"]??"", $_POST["confirm"]??"");
        $res["ok"] ? flash_set("success","Mot de passe mis à jour.") : flash_set("error",$res["error"]??"Erreur mot de passe.");
        header("Location: {$BASE_PATH}/profile.php"); exit;
    }
    // ✅ Suppression de compte
    elseif ($action==="delete_account") {
        $password = $_POST["password"] ?? "";
        $confirm  = trim($_POST["confirm"] ?? "");
        if ($confirm !== "DELETE") {
            flash_set("error","Vous devez saisir exactement DELETE pour confirmer.");
            header("Location: {$BASE_PATH}/profile.php"); exit;
        }
        $res = deleteAccount((int)$user["id"], $password);
        if ($res["ok"]) {
            // Déconnexion propre
            session_regenerate_id(true);
            $_SESSION = [];
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
            }
            session_destroy();
            header("Location: {$BASE_PATH}/login.php?msg=account_deleted");
            exit;
        } else {
            flash_set("error", $res["error"] ?? "Suppression impossible.");
            header("Location: {$BASE_PATH}/profile.php"); exit;
        }
    }
}

// recharger l'utilisateur (au cas où il a été modifié)
$user = currentUser();
$TITLE="Profil"; $ACTIVE="profile";
$success=flash_get("success"); $error=flash_get("error");
require_once "../includes/header.php";
?>
<div class="container-xxl">
    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="row g-3">
        <div class="col-12 col-lg-4">
            <div class="card p-3">
                <h5>Informations</h5>
                <div class="text-secondary">ID : <b><?= (int)$user["id"] ?></b></div>
                <div class="text-secondary">Créé le : <b><?= htmlspecialchars($user["created_at"]) ?></b></div>
            </div>
        </div>

        <div class="col-12 col-lg-8">
            <div class="card p-3 mb-3">
                <h5>Changer le pseudo</h5>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="username">
                    <div class="row g-2 align-items-center">
                        <div class="col"><input class="form-control" type="text" name="username" minlength="3" maxlength="30" pattern="[A-Za-z0-9_.-]{3,30}" value="<?= htmlspecialchars($user["username"] ?? "") ?>"></div>
                        <div class="col-auto"><button class="btn btn-outline-light">Mettre à jour</button></div>
                    </div>
                    <small class="text-secondary">Lettres, chiffres, _ . - (3–30)</small>
                </form>
            </div>

            <div class="card p-3 mb-3">
                <h5>Changer l’email</h5>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="email">
                    <div class="row g-2 align-items-center">
                        <div class="col"><input class="form-control" type="email" name="email" required value="<?= htmlspecialchars($user["email"]) ?>"></div>
                        <div class="col-auto"><button class="btn btn-outline-light">Mettre à jour</button></div>
                    </div>
                </form>
            </div>

            <div class="card p-3 mb-3">
                <h5>Changer le mot de passe</h5>
                <form method="post" autocomplete="off">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="password">
                    <div class="mb-2"><label class="form-label">Mot de passe actuel</label><input class="form-control" type="password" name="current" required></div>
                    <div class="mb-2"><label class="form-label">Nouveau mot de passe</label><input class="form-control" type="password" name="next" minlength="8" required></div>
                    <div class="mb-2"><label class="form-label">Confirmer</label><input class="form-control" type="password" name="confirm" minlength="8" required></div>
                    <button class="btn btn-outline-light">Changer le mot de passe</button>
                </form>
            </div>

            <!-- ✅ Danger zone : suppression du compte -->
            <div class="card p-3">
                <h5 class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Supprimer mon compte</h5>
                <p class="text-secondary mb-2">Cette action est <b>irréversible</b>. Toutes vos planètes, files de construction et données seront supprimées.</p>
                <form method="post" action="<?= $BASE_PATH ?>/profile.php" onsubmit="return confirm('Supprimer définitivement votre compte ? Cette action est irréversible.');">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete_account">
                    <div class="row g-2 align-items-center">
                        <div class="col-12 col-md-6">
                            <label class="form-label mb-1">Mot de passe</label>
                            <input class="form-control" type="password" name="password" required autocomplete="current-password">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label mb-1">Tapez <code>DELETE</code> pour confirmer</label>
                            <input class="form-control" type="text" name="confirm" placeholder="DELETE" required>
                        </div>
                        <div class="col-12 mt-2">
                            <button class="btn btn-danger" type="submit"><i class="bi bi-trash me-1"></i>Supprimer mon compte</button>
                        </div>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>
<?php require_once "../includes/footer.php"; ?>
