<?php
require_once "../config.php";
require_once "../includes/auth.php";
require_once "../includes/csrf.php";
require_once "../includes/flash.php";
$TITLE="Inscription"; $ACTIVE="auth";

$error=null;
if ($_SERVER["REQUEST_METHOD"]==="POST") {
    if (!csrf_check($_POST["csrf"]??"")) $error="Action expirée. Réessaie.";
    else {
        $email=$_POST["email"]??""; $pass=$_POST["password"]??"";
        $res = registerUser($email,$pass);
        if ($res["ok"]) { flash_set("success","Compte créé. Tu peux te connecter."); header("Location: {$BASE_PATH}/login.php"); exit; }
        else $error = $res["error"] ?? "Inscription impossible.";
    }
}
require_once "../includes/header.php";
?>
<div class="container-xxl">
    <div class="row justify-content-center">
        <div class="col-12 col-md-6 col-lg-5">
            <div class="card p-4">
                <h3 class="mb-3">Créer un compte</h3>
                <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <form method="post" novalidate>
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mot de passe (min 8)</label>
                        <input type="password" name="password" minlength="8" class="form-control" required>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">S’inscrire</button>
                </form>
                <div class="mt-3 text-secondary">Déjà un compte ? <a href="<?= $BASE_PATH ?>/login.php">Connexion</a></div>
            </div>
        </div>
    </div>
</div>
<?php require_once "../includes/footer.php"; ?>
