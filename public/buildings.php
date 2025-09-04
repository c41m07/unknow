<?php
require_once "../config.php";
require_once "../includes/require_login.php";
require_once "../includes/csrf.php";
require_once "../includes/buildings.php";
require_once "../includes/game.php";

$pdo = db();
$userId = (int)$_SESSION["user_id"];

// Au moins une planète
getOrCreateHomeworld($userId);

// Liste planètes
$planetsStmt = $pdo->prepare("SELECT id, name FROM planets WHERE user_id = ? ORDER BY id ASC");
$planetsStmt->execute([$userId]);
$planetsList = $planetsStmt->fetchAll();

// Planète sélectionnée
$selectedId = null;
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["planet_id"])) $selectedId = (int)$_POST["planet_id"];
elseif (isset($_GET["planet_id"])) $selectedId = (int)$_GET["planet_id"];
elseif ($planetsList) $selectedId = (int)$planetsList[0]["id"];

$check = $pdo->prepare("SELECT id FROM planets WHERE id = ? AND user_id = ?");
$check->execute([$selectedId, $userId]);
if (!$check->fetch()) $selectedId = (int)$planetsList[0]["id"];

// Finaliser + recalculer + MAJ stocks
settle_finished_jobs($selectedId, $pdo);
recalc_planet_production($selectedId, $pdo);
$planet = updateResourcesNow($selectedId, $pdo);

// Action build
$msg = $err = null;
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "build") {
    if (!csrf_check($_POST["csrf"] ?? "")) { $err = "Action expirée, réessaie."; }
    else {
        $bkey = $_POST["bkey"] ?? "";
        $res = start_build($selectedId, $bkey);
        if ($res["ok"]) { header("Location: {$BASE_PATH}/buildings.php?planet_id={$selectedId}&msg=ok"); exit; }
        else { $err = $res["error"] ?? "Impossible de lancer la construction."; }
    }
}

// État
recalc_planet_production($selectedId, $pdo);
$planet   = updateResourcesNow($selectedId, $pdo);
$levels   = get_buildings($selectedId);
$catalog  = buildings_catalog();
$queue    = get_queue($selectedId);
$queuedMap= queued_levels_map($selectedId);
$queueCnt = count($queue);

// Ressources de la planète
$metal    = (int)$planet['metal'];
$crystal  = (int)$planet['crystal'];
$hydrogen = (int)$planet['hydrogen'];
$energy   = (int)$planet['energy'];

$mph  = (int)$planet['prod_metal_per_hour'];
$cph  = (int)$planet['prod_crystal_per_hour'];
$hph  = (int)$planet['prod_hydrogen_per_hour'];
$eph  = (int)$planet['prod_energy_per_hour'];

$rates   = computePlanetRates($selectedId, $pdo);
$eProdPH = (int)$rates['eProdPH'];
$eConsPH = (int)$rates['eConsPH'];
$eNetPH  = (int)$rates['eNetPH'];

if (isset($_GET["msg"]) && $_GET["msg"] === "ok") $msg = "Construction ajoutée à la file.";

$TITLE="Bâtiments"; $ACTIVE="buildings";
require_once "../includes/header.php";
?>
<div class="container-xxl">

    <!-- Barre ressources (tuiles) + sélecteur planète -->
    <div class="card p-3 mb-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="resource-bar" style="flex:1 1 auto;">
                <div class="resource-chip">
                    <div class="label"><i class="bi bi-database me-2"></i>Métaux</div>
                    <div class="value"><b id="metal"><?= number_format($metal) ?></b> <span class="pill-dark ms-2"><span id="mph-label">+<?= $mph ?></span>/h</span></div>
                </div>
                <div class="resource-chip">
                    <div class="label"><i class="bi bi-gem me-2"></i>Nano-carbone</div>
                    <div class="value"><b id="crystal"><?= number_format($crystal) ?></b> <span class="pill-dark ms-2"><span id="cph-label">+<?= $cph ?></span>/h</span></div>
                </div>
                <div class="resource-chip">
                    <div class="label"><i class="bi bi-droplet me-2"></i>Hydrogène</div>
                    <div class="value"><b id="hydrogen"><?= number_format($hydrogen) ?></b> <span class="pill-dark ms-2"><span id="hph-label">+<?= $hph ?></span>/h</span></div>
                </div>
                <div class="resource-chip">
                    <div class="label"><i class="bi bi-lightning me-2"></i>Énergie</div>
                    <div class="value"><b id="energy"><?= number_format($energy) ?></b> <span class="pill-dark ms-2"><span id="eph-label"><?= $eph>=0?'+':'' ?><?= $eph ?></span>/h</span></div>
                </div>
            </div>
            <div>
                <form method="get" action="<?= $BASE_PATH ?>/buildings.php" class="m-0">
                    <select class="form-select form-select-sm" name="planet_id" onchange="this.form.submit()">
                        <?php foreach ($planetsList as $pl): ?>
                            <option value="<?= (int)$pl['id'] ?>" <?= ((int)$pl['id']===$selectedId) ? "selected" : "" ?>><?= htmlspecialchars($pl['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>
    </div>

    <?php if ($msg): ?><div class="alert alert-success card p-2"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger card p-2"><?= htmlspecialchars($err) ?></div><?php endif; ?>

    <!-- File de construction -->
    <div class="card p-3 mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <h6 class="mb-0">File de construction (<?= $queueCnt ?>/5)</h6>
            <?php if ($queue): ?><span class="pill-dark"><i class="bi bi-hourglass-split me-1"></i><span data-countdown="<?= htmlspecialchars(str_replace(' ', 'T', $queue[0]['ends_at'])) ?>"></span></span><?php endif; ?>
        </div>
        <hr class="border-secondary">
        <?php if (!$queue): ?>
            <div class="text-secondary">Aucune construction en cours.</div>
        <?php else: foreach ($queue as $i=>$job): ?>
            <div class="d-flex justify-content-between align-items-center py-2 <?= $i<count($queue)-1?'border-bottom border-secondary':'' ?>">
                <div><?= htmlspecialchars($catalog[$job['bkey']]['label']) ?> → niv <?= (int)$job['target_level'] ?> <span class="badge bg-<?= $i===0?'primary':'secondary' ?> ms-2"><?= $i===0?'en cours':'en attente' ?></span></div>
                <div><span data-countdown="<?= htmlspecialchars(str_replace(' ', 'T', $job['ends_at'])) ?>"></span></div>
            </div>
        <?php endforeach; endif; ?>
    </div>

    <!-- Liste des bâtiments -->
    <div class="card p-3">
        <div class="mb-2 text-secondary">Ressources & énergie</div>

        <?php foreach ($catalog as $k=>$b):
            $lvl=(int)($levels[$k]??0);
            $q=(int)($queuedMap[$k]??0);
            $virt=$lvl+$q;

            $cost=building_next_cost($k,$virt);
            $time=building_next_time($k,$virt);

            $prodLabel = match ($b['affects']) {
                'metal' => 'Métal',
                'crystal' => 'Cristal',
                'hydrogen' => 'Hydrogène',
                default => 'Énergie'
            };
            $prodNow   = $b['prod']($lvl);
            $useNow    = $b['energy_use']($lvl);
            $prodNext  = $b['prod']($lvl+1);
            $useNext   = $b['energy_use']($lvl+1);

            $can = ($queueCnt<5) && ($metal>=$cost['metal']) && ($crystal>=$cost['crystal']);
            ?>
            <div class="build-row mb-2">
                <div class="build-thumb"></div>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between">
                        <div class="build-title"><?= htmlspecialchars($b['label']) ?> <span class="text-secondary">Niveau <?= $lvl ?><?= $q ? " (+{$q} en file)" : "" ?></span></div>
                        <div class="build-cost">
                            <span class="me-3"><i class="bi bi-database me-1"></i><?= number_format($cost['metal']) ?></span>
                            <span class="me-3"><i class="bi bi-gem me-1"></i><?= number_format($cost['crystal']) ?></span>
                            <span><i class="bi bi-stopwatch me-1"></i><?= $time ?>s</span>
                        </div>
                    </div>
                    <div class="build-meta mt-1">
                        <div class="d-flex flex-wrap gap-4">
                            <span>Prod (niv. actuel): <b>+<?= number_format($prodNow) ?></b> <?= $prodLabel ?>/h</span>
                            <span>Conso: <b><?= $useNow>0?'-'.number_format($useNow):'0' ?></b> Énergie/h</span>
                            <span class="text-secondary">→ Prochain: +<?= number_format($prodNext) ?>/h, conso <?= $useNext>0?'-'.number_format($useNext):'0' ?>/h</span>
                        </div>
                    </div>
                </div>
                <div>
                    <form method="post" action="<?= $BASE_PATH ?>/buildings.php?planet_id=<?= $selectedId ?>">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token()) ?>">
                        <input type="hidden" name="action" value="build">
                        <input type="hidden" name="planet_id" value="<?= $selectedId ?>">
                        <input type="hidden" name="bkey" value="<?= htmlspecialchars($k) ?>">
                        <button class="btn btn-outline-light" type="submit" <?= $can? "":"disabled" ?>>
                            <i class="bi bi-arrow-up-right-square me-1"></i>Construire
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</div>

<script>
    (function(){
        const NF = new Intl.NumberFormat();

        const metalEl    = document.getElementById('metal');
        const crystalEl  = document.getElementById('crystal');
        const hydrogenEl = document.getElementById('hydrogen');
        const energyEl   = document.getElementById('energy');

        const mphLabel = document.getElementById('mph-label');
        const cphLabel = document.getElementById('cph-label');
        const hphLabel = document.getElementById('hph-label');

        const state = {
            m: <?= $metal ?>,
            c: <?= $crystal ?>,
            h: <?= $hydrogen ?>,
            e: <?= $energy ?>,
            mph: <?= $mph ?>,
            cph: <?= $cph ?>,
            hph: <?= $hph ?>,
            enetPH: <?= $eNetPH ?>,
            eprodPH: <?= $eProdPH ?>,
            econsPH: <?= $eConsPH ?>
        };
        const malusFactor = state.econsPH > 0 ? Math.max(0, Math.min(1, state.eprodPH / state.econsPH)) : 1;
        const netPS = state.enetPH / 3600;

        let last = Date.now();
        function tick(){
            const now = Date.now(); const dt = Math.floor((now-last)/1000); if (dt<=0) return; last = now;

            state.e = state.e + netPS * dt; if (state.e < 0) state.e = 0;

            const factor = (state.e === 0 && state.enetPH <= 0) ? malusFactor : 1;

            state.m += (state.mph * factor / 3600) * dt;
            state.c += (state.cph * factor / 3600) * dt;
            state.h += (state.hph * factor / 3600) * dt;

            if (mphLabel) { const eff = Math.floor(state.mph * factor); mphLabel.textContent = (eff>=0?'+':'')+eff; }
            if (cphLabel) { const eff = Math.floor(state.cph * factor); cphLabel.textContent = (eff>=0?'+':'')+eff; }
            if (hphLabel) { const eff = Math.floor(state.hph * factor); hphLabel.textContent = (eff>=0?'+':'')+eff; }

            if (metalEl)    metalEl.textContent    = NF.format(Math.floor(state.m));
            if (crystalEl)  crystalEl.textContent  = NF.format(Math.floor(state.c));
            if (hydrogenEl) hydrogenEl.textContent = NF.format(Math.floor(state.h));
            if (energyEl)   energyEl.textContent   = NF.format(Math.floor(state.e));
        }
        tick(); setInterval(tick,1000);

        // compte à rebours
        function fmt(sec){ if(sec<0) sec=0; const h=Math.floor(sec/3600), m=Math.floor((sec%3600)/60), s=sec%60; return (h? h+"h ":"")+(m? m+"m ":"")+s+"s"; }
        const cds=[...document.querySelectorAll('[data-countdown]')].map(el=>({el, ends:new Date(el.getAttribute('data-countdown'))}));
        let nextReload=null;
        function tickCd(){
            const now=new Date();
            cds.forEach((o,i)=>{ const sec=Math.floor((o.ends-now)/1000); o.el.textContent=sec>0?fmt(sec):'Terminé'; if(i===0&&sec>=0){ nextReload=o.ends; }});
            if(nextReload && (new Date()>=nextReload)){ setTimeout(()=>location.reload(),1200); nextReload=null; }
        }
        tickCd(); setInterval(tickCd,1000);
    })();
</script>

<?php require_once "../includes/footer.php"; ?>
