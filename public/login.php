<?php
require_once "../config.php";
require_once "../includes/auth.php";
require_once "../includes/flash.php";
$TITLE="Connexion"; $ACTIVE="auth";

$flash = flash_get("success"); $error=null;
if ($_SERVER["REQUEST_METHOD"]==="POST") {
    $email=$_POST["email"]??""; $password=$_POST["password"]??"";
    if (loginUser($email,$password)) { header("Location: {$BASE_PATH}/dashboard.php"); exit; }
    else $error="Identifiants incorrects";
}
require_once "../includes/header.php";
?>
<div class="container-xxl">
    <div class="row justify-content-center">
        <div class="col-12 col-md-6 col-lg-5">
            <div class="card p-4">
                <h3 class="mb-3">Se connecter</h3>
                <?php if ($flash): ?><div class="alert alert-success"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <form method="post" novalidate>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mot de passe</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Connexion</button>
                </form>
                <div class="mt-3 text-secondary">Pas de compte ? <a href="<?= $BASE_PATH ?>/register.php">Inscription</a></div>
            </div>
        </div>
    </div>
</div>
<?php require_once "../includes/footer.php"; ?>
