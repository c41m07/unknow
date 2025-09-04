<?php
require_once "../config.php";
require_once "../includes/auth.php";
$TITLE="Accueil"; $ACTIVE = isLoggedIn() ? "dashboard" : "auth";
require_once "../includes/header.php";
?>
<div class="container-xxl">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8">
            <div class="card p-4 text-center">
                <h1 class="mb-3">Unknow</h1>
                <p class="text-secondary mb-4">Jeu de stratégie spatial — construis, développe, conquiers.</p>
                <?php if (isLoggedIn()): ?>
                    <a class="btn btn-primary me-2" href="<?= $BASE_PATH ?>/dashboard.php"><i class="bi bi-house me-1"></i>Dashboard</a>
                    <a class="btn btn-outline-light" href="<?= $BASE_PATH ?>/logout.php"><i class="bi bi-box-arrow-right me-1"></i>Déconnexion</a>
                <?php else: ?>
                    <a class="btn btn-primary me-2" href="<?= $BASE_PATH ?>/register.php"><i class="bi bi-person-plus me-1"></i>Créer un compte</a>
                    <a class="btn btn-outline-light" href="<?= $BASE_PATH ?>/login.php"><i class="bi bi-box-arrow-in-right me-1"></i>Se connecter</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once "../includes/footer.php"; ?>
