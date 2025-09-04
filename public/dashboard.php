<?php
require_once "../config.php";
require_once "../includes/require_login.php";
require_once "../includes/game.php";
require_once "../includes/buildings.php";
require_once "../includes/csrf.php";
require_once "../includes/flash.php";

$pdo = db();
$userId = (int)$_SESSION["user_id"];

/* Renommage planète */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "rename") {
    if (!csrf_check($_POST["csrf"] ?? "")) { flash_set("error","Action expirée."); header("Location: {$BASE_PATH}/dashboard.php"); exit; }
    $pid  = (int)($_POST["planet_id"]??0);
    $name = $_POST["name"]??"";
    $res  = renamePlanet($pid,$userId,$name);
    $res["ok"] ? flash_set("success","Nom mis à jour.") : flash_set("error",$res["error"]??"Impossible de renommer.");
    header("Location: {$BASE_PATH}/dashboard.php?planet_id=".$pid);
    exit;
}

/* ✅ Abandon de planète */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "abandon") {
    if (!csrf_check($_POST["csrf"] ?? "")) { flash_set("error","Action expirée."); header("Location: {$BASE_PATH}/dashboard.php"); exit; }
    $pid = (int)($_POST["planet_id"]??0);
    $res = abandonPlanet($pid, $userId);
    if ($res["ok"]) {
        flash_set("success","Colonie abandonnée.");
        header("Location: {$BASE_PATH}/dashboard.php");
    } else {
        flash_set("error", $res["error"] ?? "Impossible d’abandonner la colonie.");
        header("Location: {$BASE_PATH}/dashboard.php?planet_id=".$pid);
    }
    exit;
}

/* Au moins une planète */
getOrCreateHomeworld($userId);

/* Finaliser + recalculer prod + actualiser stocks */
$stmt=$pdo->prepare("SELECT id FROM planets WHERE user_id=? ORDER BY id ASC");
$stmt->execute([$userId]); $ids=$stmt->fetchAll();
foreach($ids as $row){
    $pid=(int)$row['id'];
    settle_finished_jobs($pid,$pdo);
    recalc_planet_production($pid,$pdo);
    updateResourcesNow($pid,$pdo);
}

/* Charger planètes */
$stmt=$pdo->prepare("
  SELECT id, name, metal, crystal, hydrogen, energy,
         prod_metal_per_hour AS mph, prod_crystal_per_hour AS cph, prod_hydrogen_per_hour AS hph, prod_energy_per_hour AS eph, last_update
  FROM planets WHERE user_id=? ORDER BY id ASC
");
$stmt->execute([$userId]); $planets=$stmt->fetchAll();

/* Planète sélectionnée (pour la fiche à droite) */
$selectedId = isset($_GET["planet_id"]) ? (int)$_GET["planet_id"] : ( ($planets[0]['id'] ?? 0) );
$selectedPlanet = null;
foreach ($planets as $p) { if ((int)$p['id'] === $selectedId) { $selectedPlanet = $p; break; } }
if (!$selectedPlanet && $planets) { $selectedPlanet = $planets[0]; $selectedId = (int)$selectedPlanet['id']; }

/* Taux énergie pour JS */
$withRates=[];
foreach($planets as $p){
    $r = computePlanetRates((int)$p['id'],$pdo);
    $p['eProdPH'] = (int)$r['eProdPH'];
    $p['eConsPH'] = (int)$r['eConsPH'];
    $p['eNetPH']  = (int)$r['eNetPH'];
    $withRates[] = $p;
}

/* Agrégats init */
$total=['metal'=>0,'crystal'=>0,'hydrogen'=>0,'energy'=>0,'mph'=>0,'cph'=>0,'hph'=>0,'eph'=>0];
foreach($planets as $p){
    $total['metal']    += (int)$p['metal'];
    $total['crystal']  += (int)$p['crystal'];
    $total['hydrogen'] += (int)$p['hydrogen'];
    $total['energy']   += (int)$p['energy'];
    $total['mph']      += (int)$p['mph'];
    $total['cph']      += (int)$p['cph'];
    $total['hph']      += (int)$p['hph'];
    $total['eph']      += (int)$p['eph'];
}

/* File globale */
$q=$pdo->prepare("
  SELECT bq.*, p.name AS planet_name
  FROM build_queue bq JOIN planets p ON p.id=bq.planet_id
  WHERE p.user_id=? AND bq.ends_at>NOW()
  ORDER BY bq.ends_at ASC
");
$q->execute([$userId]); $queue=$q->fetchAll();
$nextJob=$queue[0]??null; $catalog=buildings_catalog();

$success=flash_get("success"); $error=flash_get("error");
$TITLE="Planètes"; $ACTIVE="dashboard";
require_once "../includes/header.php";
?>
<div class="container-xxl">

    <!-- Tuiles ressources empire -->
    <div class="resource-bar mb-3">
        <div class="resource-chip">
            <div class="label"><i class="bi bi-database me-2"></i>Métaux</div>
            <div class="value"><span id="total-metal"><?= number_format($total['metal']) ?></span></div>
        </div>
        <div class="resource-chip">
            <div class="label"><i class="bi bi-gem me-2"></i>Nano-carbone</div>
            <div class="value"><span id="total-crystal"><?= number_format($total['crystal']) ?></span></div>
        </div>
        <div class="resource-chip">
            <div class="label"><i class="bi bi-droplet me-2"></i>Hydrogène</div>
            <div class="value"><span id="total-hydrogen"><?= number_format($total['hydrogen']) ?></span></div>
        </div>
        <div class="resource-chip">
            <div class="label"><i class="bi bi-lightning me-2"></i>Énergie</div>
            <div class="value"><span id="total-energy"><?= number_format($total['energy']) ?></span></div>
        </div>
    </div>

    <?php if ($success): ?><div class="alert alert-success card p-2"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger card p-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="row g-3">
        <!-- Aperçu de l’empire -->
        <div class="col-12 col-xl-7">
            <div class="card p-3">
                <h5 class="mb-3">Aperçu de l’Empire</h5>

                <div class="card p-0 mb-2">
                    <div class="p-3 border-bottom border-secondary">Flotte</div>
                    <div class="px-3 pb-3 text-secondary d-flex flex-column gap-2">
                        <div class="d-flex justify-content-between"><span><i class="bi bi-rocket me-2"></i>Vaisseaux en déplacement</span><span>Aucun</span></div>
                        <div class="d-flex justify-content-between"><span><i class="bi bi-rocket-takeoff me-2"></i>Vaisseaux en stationnement</span><span>Aucun</span></div>
                    </div>
                </div>

                <div class="card p-0 mb-2">
                    <div class="p-3 border-bottom border-secondary">Bâtiments en construction</div>
                    <div class="px-3 pb-3 text-secondary">
                        <?php if (!$queue): ?>
                            <div class="d-flex justify-content-between"><span>Aucun</span><span>—</span></div>
                        <?php else: ?>
                            <?php foreach($queue as $i=>$job): ?>
                                <div class="d-flex justify-content-between py-2 <?= $i<count($queue)-1?'border-bottom border-secondary':'' ?>">
                                    <span><?= htmlspecialchars($catalog[$job['bkey']]['label'] ?? $job['bkey']) ?> · <span class="text-secondary">Niv <?= (int)$job['target_level'] ?></span> <span class="badge bg-primary ms-2"><?= $i===0?'en cours':'en attente' ?></span></span>
                                    <span data-countdown="<?= htmlspecialchars(str_replace(' ', 'T', $job['ends_at'])) ?>"></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card p-0">
                    <div class="p-3 border-bottom border-secondary">Recherche</div>
                    <div class="px-3 pb-3 text-secondary">
                        <div class="d-flex justify-content-between"><span><i class="bi bi-beaker me-2"></i>—</span><span>—</span></div>
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div class="stat-cards mt-3">
                <div class="stat-card">
                    <div class="text-secondary">Points</div>
                    <div class="fs-5 fw-bold">1 234</div>
                    <div class="text-secondary small">Rang : #42</div>
                </div>
                <div class="stat-card">
                    <div class="text-secondary">Planètes</div>
                    <div class="fs-5 fw-bold"><?= count($planets) ?>/3</div>
                    <div class="text-secondary small">Colonies disponibles</div>
                </div>
                <div class="stat-card">
                    <div class="text-secondary">Puissance militaire</div>
                    <div class="fs-5 fw-bold">890</div>
                    <div class="text-secondary small">Rang : #56</div>
                </div>
            </div>
        </div>

        <!-- Planète sélectionnée + renommage + abandon -->
        <div class="col-12 col-xl-5">
            <div class="planet-frame">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="fw-semibold">Planète sélectionnée</div>
                    <form class="m-0" method="get" action="<?= $BASE_PATH ?>/dashboard.php">
                        <select class="form-select form-select-sm" name="planet_id" onchange="this.form.submit()">
                            <?php foreach ($planets as $pl): ?>
                                <option value="<?= (int)$pl['id'] ?>" <?= ((int)$pl['id']===$selectedId) ? "selected" : "" ?>>
                                    <?= htmlspecialchars($pl['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>

                <div class="planet-img mb-2"></div>

                <div><b><?= htmlspecialchars($selectedPlanet['name']) ?></b></div>
                <div class="text-secondary small">Dernière MAJ : <?= htmlspecialchars($selectedPlanet['last_update']) ?></div>
                <div class="text-secondary small mb-2">Coordonnées : [1:234:5]</div>

                <!-- Renommage -->
                <form class="row g-2 align-items-center mt-1" method="post" action="<?= $BASE_PATH ?>/dashboard.php?planet_id=<?= $selectedId ?>">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="rename">
                    <input type="hidden" name="planet_id" value="<?= (int)$selectedPlanet['id'] ?>">
                    <div class="col-8">
                        <input class="form-control form-control-sm" type="text" name="name" maxlength="50" value="<?= htmlspecialchars($selectedPlanet['name']) ?>">
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-sm btn-outline-light" type="submit">Renommer</button>
                    </div>
                </form>

                <!-- ✅ Abandonner -->
                <form class="mt-3" method="post" action="<?= $BASE_PATH ?>/dashboard.php" onsubmit="return confirm('Confirmer l’abandon de la colonie « <?= htmlspecialchars($selectedPlanet['name']) ?> » ? Cette action est irréversible.');">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="action" value="abandon">
                    <input type="hidden" name="planet_id" value="<?= (int)$selectedPlanet['id'] ?>">
                    <button class="btn btn-sm btn-danger" type="submit">
                        <i class="bi bi-exclamation-triangle me-1"></i>Abandonner cette colonie
                    </button>
                    <div class="text-secondary small mt-1">Impossible d’abandonner votre dernière planète.</div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    (function(){
        const NF = new Intl.NumberFormat();

        // Modèle par planète
        const planets = <?= json_encode(array_map(function($p){
            return [
                    'id'=>(int)$p['id'],
                    'm'=>(int)$p['metal'],
                    'c'=>(int)$p['crystal'],
                    'h'=>(int)$p['hydrogen'],
                    'e'=>(int)$p['energy'],
                    'mph'=>(int)$p['mph'],
                    'cph'=>(int)$p['cph'],
                    'hph'=>(int)$p['hph'],
                    'eProdPH'=>(int)$p['eProdPH'],
                    'eConsPH'=>(int)$p['eConsPH'],
                    'eNetPH'=>(int)$p['eNetPH'],
            ];
        }, $withRates), JSON_UNESCAPED_UNICODE) ?>;

        const tM = document.getElementById('total-metal');
        const tC = document.getElementById('total-crystal');
        const tH = document.getElementById('total-hydrogen');
        const tE = document.getElementById('total-energy');

        planets.forEach(p=>{
            p.malusFactor = p.eConsPH > 0 ? Math.max(0, Math.min(1, p.eProdPH / p.eConsPH)) : 1;
            p.netPS = p.eNetPH / 3600;
        });

        function sum(field){ return planets.reduce((a,p)=>a+(p[field]||0),0); }

        let last = Date.now();
        function tick(){
            const now = Date.now();
            const dt = Math.floor((now-last)/1000);
            if (dt<=0) return;
            last = now;

            planets.forEach(p=>{
                p.e = p.e + p.netPS * dt; if (p.e < 0) p.e = 0;
                const factor = (p.e === 0 && p.eNetPH <= 0) ? p.malusFactor : 1;
                p.m += (p.mph * factor / 3600) * dt;
                p.c += (p.cph * factor / 3600) * dt;
                p.h += (p.hph * factor / 3600) * dt;
            });

            if (tM) tM.textContent = NF.format(Math.floor(sum('m')));
            if (tC) tC.textContent = NF.format(Math.floor(sum('c')));
            if (tH) tH.textContent = NF.format(Math.floor(sum('h')));
            if (tE) tE.textContent = NF.format(Math.floor(sum('e')));
        }
        tick(); setInterval(tick,1000);

        // Compte à rebours
        function fmt(sec){ if(sec<0) sec=0; const h=Math.floor(sec/3600), m=Math.floor((sec%3600)/60), s=sec%60; return (h? h+"h ":"")+(m? m+"m ":"")+s+"s"; }
        const nodes=[...document.querySelectorAll('[data-countdown]')].map(el=>({el, ends:new Date(el.getAttribute('data-countdown'))}));
        let reloadAt = nodes.length ? nodes[0].ends : null;
        function tickCountdown(){
            const now=new Date();
            nodes.forEach((o,i)=>{ const sec=Math.floor((o.ends-now)/1000); o.el.textContent = sec>0 ? fmt(sec) : 'Terminé'; if(i===0 && sec>=0) reloadAt=o.ends; });
            if (reloadAt && new Date()>=reloadAt){ setTimeout(()=>location.reload(),1200); reloadAt=null; }
        }
        tickCountdown(); setInterval(tickCountdown,1000);
    })();
</script>

<?php require_once "../includes/footer.php"; ?>
