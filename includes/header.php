<?php
// includes/header.php
require_once __DIR__ . "/../config.php";

$APP_NAME = $APP_NAME ?? "Nova Empire";
$TITLE  = $TITLE  ?? $APP_NAME;
$ACTIVE = $ACTIVE ?? ""; // 'dashboard' | 'buildings' | 'profile'

// Connecté ?
$userId   = $_SESSION['user_id'] ?? null;
$showMenu = !empty($userId);

// Liste des planètes pour la sidebar (facultatif)
$planetsList = [];
if ($showMenu) {
    require_once __DIR__ . "/game.php";
    try {
        $stmt = db()->prepare("SELECT id, name FROM planets WHERE user_id = ? ORDER BY id ASC");
        $stmt->execute([$userId]);
        $planetsList = $stmt->fetchAll();
    } catch (Throwable $e) {}
}
function isActive($k){ global $ACTIVE; return $ACTIVE === $k ? "active" : ""; }
?>
<!doctype html>
<html lang="fr" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($TITLE) ?> · <?= htmlspecialchars($APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= $BASE_PATH ?>/assets/css/app.css" rel="stylesheet">
</head>
<body class="bg-space">

<?php if ($showMenu): ?>
    <!-- Barre sup mobile -->
    <nav class="navbar navbar-dark bg-topbar d-md-none">
        <div class="container-fluid">
            <button class="btn btn-outline-light" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasMenu">
                <i class="bi bi-list"></i>
            </button>
            <span class="navbar-brand ms-2 fw-bold"><?= htmlspecialchars($APP_NAME) ?></span>
        </div>
    </nav>

    <!-- Offcanvas mobile -->
    <div class="offcanvas offcanvas-start text-bg-dark" tabindex="-1" id="offcanvasMenu">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title"><?= htmlspecialchars($APP_NAME) ?></h5>
            <button class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body p-0">
            <div class="sidebar p-3">
                <div class="brand mb-3">
                    <i class="bi bi-rocket-takeoff text-primary me-2"></i>
                    <span class="fw-bold"><?= htmlspecialchars($APP_NAME) ?></span>
                </div>

                <div class="sidebar-group">Empire</div>
                <ul class="nav flex-column">
                    <li class="nav-item"><a class="nav-link <?= isActive('dashboard') ?>" href="<?= $BASE_PATH ?>/dashboard.php"><i class="bi bi-globe me-2"></i>Planètes</a></li>
                    <li class="nav-item"><a class="nav-link <?= isActive('buildings') ?>" href="<?= $BASE_PATH ?>/buildings.php"><i class="bi bi-buildings me-2"></i>Bâtiments</a></li>
                    <li class="nav-item"><span class="nav-link disabled-link"><i class="bi bi-beaker me-2"></i>Recherche</span></li>
                    <li class="nav-item"><span class="nav-link disabled-link"><i class="bi bi-shield me-2"></i>Défense</span></li>
                </ul>

                <div class="sidebar-group mt-3">Militaire</div>
                <ul class="nav flex-column">
                    <li class="nav-item"><span class="nav-link disabled-link"><i class="bi bi-rocket me-2"></i>Flotte</span></li>
                    <li class="nav-item"><span class="nav-link disabled-link"><i class="bi bi-people me-2"></i>Alliance</span></li>
                </ul>

                <div class="sidebar-group mt-3">Compte</div>
                <ul class="nav flex-column mb-3">
                    <li class="nav-item"><a class="nav-link <?= isActive('profile') ?>" href="<?= $BASE_PATH ?>/profile.php"><i class="bi bi-person me-2"></i>Profil</a></li>
                    <li class="nav-item"><span class="nav-link disabled-link"><i class="bi bi-gear me-2"></i>Paramètres</span></li>
                </ul>

                <?php if ($planetsList): ?>
                    <div class="sidebar-group mt-3">Vos planètes</div>
                    <ul class="nav flex-column">
                        <?php foreach ($planetsList as $pl): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="<?= $BASE_PATH ?>/buildings.php?planet_id=<?= (int)$pl['id'] ?>">
                                    <i class="bi bi-planet me-2"></i><?= htmlspecialchars($pl['name']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <a class="btn btn-outline-light w-100 mt-3" href="<?= $BASE_PATH ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</a>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="container-fluid">
    <div class="row">
        <?php if ($showMenu): ?>
            <!-- Sidebar desktop -->
            <aside class="col-md-3 col-lg-2 d-none d-md-block p-0">
                <div class="sidebar h-100">
                    <div class="p-3 d-flex align-items-center brand">
                        <i class="bi bi-rocket-takeoff text-primary me-2"></i>
                        <span class="fw-bold"><?= htmlspecialchars($APP_NAME) ?></span>
                    </div>

                    <div class="px-3 sidebar-group">Empire</div>
                    <ul class="nav flex-column px-2">
                        <li class="nav-item"><a class="nav-link <?= isActive('dashboard') ?>" href="<?= $BASE_PATH ?>/dashboard.php"><i class="bi bi-globe me-2"></i>Planètes</a></li>
                        <li class="nav-item"><a class="nav-link <?= isActive('buildings') ?>" href="<?= $BASE_PATH ?>/buildings.php"><i class="bi bi-buildings me-2"></i>Bâtiments</a></li>
                        <li class="nav-item"><span class="nav-link disabled-link"><i class="bi bi-beaker me-2"></i>Recherche</span></li>
                        <li class="nav-item"><span class="nav-link disabled-link"><i class="bi bi-shield me-2"></i>Défense</span></li>
                    </ul>

                    <div class="px-3 sidebar-group mt-2">Militaire</div>
                    <ul class="nav flex-column px-2">
                        <li class="nav-item"><span class="nav-link disabled-link"><i class="bi bi-rocket me-2"></i>Flotte</span></li>
                        <li class="nav-item"><span class="nav-link disabled-link"><i class="bi bi-people me-2"></i>Alliance</span></li>
                    </ul>

                    <div class="px-3 sidebar-group mt-2">Compte</div>
                    <ul class="nav flex-column px-2">
                        <li class="nav-item"><a class="nav-link <?= isActive('profile') ?>" href="<?= $BASE_PATH ?>/profile.php"><i class="bi bi-person me-2"></i>Profil</a></li>
                        <li class="nav-item"><span class="nav-link disabled-link"><i class="bi bi-gear me-2"></i>Paramètres</span></li>
                    </ul>

                    <?php if ($planetsList): ?>
                        <div class="px-3 sidebar-group mt-2">Vos planètes</div>
                        <ul class="nav flex-column px-2">
                            <?php foreach ($planetsList as $pl): ?>
                                <li class="nav-item">
                                    <a class="nav-link" href="<?= $BASE_PATH ?>/buildings.php?planet_id=<?= (int)$pl['id'] ?>">
                                        <i class="bi bi-planet me-2"></i><?= htmlspecialchars($pl['name']) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <div class="p-3">
                        <a class="btn btn-outline-light w-100" href="<?= $BASE_PATH ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Déconnexion</a>
                    </div>
                </div>
            </aside>
        <?php endif; ?>

        <?php $mainCols = $showMenu ? "col-12 col-md-9 col-lg-10 px-3 px-md-4" : "col-12 px-3 px-md-4"; ?>
        <main class="<?= $mainCols ?> py-3">
