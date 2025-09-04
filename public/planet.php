<?php
require_once "../config.php";
require_once "../includes/require_login.php";
require_once "../includes/csrf.php";
require_once "../includes/game.php";

$userId = (int)$_SESSION["user_id"];
$planet = getOrCreateHomeworld($userId); // simple : une planète

$TITLE="Ma planète"; $ACTIVE="planet";
require_once "../includes/header.php";
?>
<div class="container-xxl">
    <div class="card p-3 mb-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h3 class="m-0"><?= htmlspecialchars($planet['name']) ?></h3>
            <small class="text-secondary">Dernière MAJ : <?= htmlspecialchars($planet['last_update']) ?></small>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-lg-4">
            <div class="card p-3">
                <div class="d-flex justify-content-between"><span>Métal</span><b><?= number_format($planet['metal']) ?></b></div>
                <div class="d-flex justify-content-between"><span>Cristal</span><b><?= number_format($planet['crystal']) ?></b></div>
                <div class="d-flex justify-content-between"><span>Énergie</span><b><?= number_format($planet['energy']) ?></b></div>
            </div>
        </div>
        <div class="col-12 col-lg-8">
            <div class="card p-3">
                <h5>Renommer la planète</h5>
                <form method="post" action="<?= $BASE_PATH ?>/planet.php">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="rename">
                    <input type="hidden" name="planet_id" value="<?= (int)$planet['id'] ?>">
                    <div class="row g-2 align-items-center">
                        <div class="col"><input class="form-control" type="text" name="name" maxlength="50" value="<?= htmlspecialchars($planet['name']) ?>"></div>
                        <div class="col-auto"><button class="btn btn-outline-light" type="submit">Renommer</button></div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once "../includes/footer.php"; ?>
