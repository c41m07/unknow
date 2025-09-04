<?php
// includes/buildings.php
// - Catalogue depuis buildings_config.php
// - File de construction (max 5)
// - Recalc prod : métal, cristal, hydrogène, énergie = prod - conso

require_once __DIR__ . "/game.php";
require_once __DIR__ . "/buildings_config.php";

/* Helpers */
function _cfg(string $key): array {
    $cfg = buildings_config();
    if (!isset($cfg[$key])) throw new InvalidArgumentException("Bâtiment inconnu: $key");
    return $cfg[$key];
}
function _prod_at(array $cfg, int $lvl): int {
    if ($lvl <= 0) return 0;
    return (int) round(($cfg['prod_base'] ?? 0) * pow(($cfg['prod_growth'] ?? 1.0), $lvl - 1));
}
function _energy_use_at(array $cfg, int $lvl): int {
    if ($lvl <= 0) return 0;
    $base = $cfg['energy_use_base'] ?? 0;
    $g    = $cfg['energy_use_growth'] ?? 1.0;
    $lin  = !empty($cfg['energy_use_linear']);
    $val  = $base * pow($g, $lvl);
    if ($lin) $val *= $lvl;
    return (int) round($val);
}

/* Catalogue runtime */
function buildings_catalog(): array {
    $cfg = buildings_config();
    $out = [];
    foreach ($cfg as $k => $c) {
        $out[$k] = [
            'label'      => $c['label'],
            'affects'    => $c['affects'],               // metal|crystal|hydrogen|energy
            'prod'       => fn(int $lvl) => _prod_at($c, $lvl),
            'energy_use' => fn(int $lvl) => _energy_use_at($c, $lvl),
        ];
    }
    return $out;
}

/* Coûts / temps */
function building_next_cost(string $bkey, int $currentLevel): array {
    $c = _cfg($bkey);
    $f = $c['growth_cost'];
    return [
        'metal'   => (int) round(($c['base_cost']['metal']   ?? 0) * pow($f, $currentLevel)),
        'crystal' => (int) round(($c['base_cost']['crystal'] ?? 0) * pow($f, $currentLevel)),
    ];
}
function building_next_time(string $bkey, int $currentLevel): int {
    $c = _cfg($bkey);
    return (int) round(($c['base_time'] ?? 0) * pow(($c['growth_time'] ?? 1.0), $currentLevel));
}

/* État & file */
function get_buildings(int $planetId): array {
    $pdo = db();
    $st = $pdo->prepare("SELECT bkey, level FROM planet_buildings WHERE planet_id = ?");
    $st->execute([$planetId]);
    $map = [];
    foreach ($st as $r) $map[$r['bkey']] = (int)$r['level'];
    foreach (array_keys(buildings_config()) as $k) if (!isset($map[$k])) $map[$k] = 0;
    return $map;
}
function get_queue(int $planetId): array {
    $pdo = db();
    $st = $pdo->prepare("SELECT * FROM build_queue WHERE planet_id = ? AND ends_at > NOW() ORDER BY ends_at ASC");
    $st->execute([$planetId]); return $st->fetchAll();
}
function queue_count(int $planetId): int {
    $pdo = db();
    $st = $pdo->prepare("SELECT COUNT(*) FROM build_queue WHERE planet_id = ? AND ends_at > NOW()");
    $st->execute([$planetId]); return (int)$st->fetchColumn();
}
function queued_levels_map(int $planetId): array {
    $pdo = db();
    $st = $pdo->prepare("SELECT bkey, COUNT(*) c FROM build_queue WHERE planet_id = ? AND ends_at > NOW() GROUP BY bkey");
    $st->execute([$planetId]); $m = [];
    foreach ($st as $r) $m[$r['bkey']] = (int)$r['c'];
    return $m;
}

/* Finalisation jobs + recalc prod */
function settle_finished_jobs(int $planetId, PDO $pdo = null): void {
    $pdo = $pdo ?: db();
    $own = !$pdo->inTransaction();
    if ($own) $pdo->beginTransaction();
    try {
        $pdo->prepare("SELECT id FROM planets WHERE id = ? FOR UPDATE")->execute([$planetId]);

        $st = $pdo->prepare("SELECT * FROM build_queue WHERE planet_id = ? AND ends_at <= NOW() ORDER BY ends_at ASC");
        $st->execute([$planetId]); $jobs = $st->fetchAll();

        if ($jobs) {
            foreach ($jobs as $j) {
                $pdo->prepare("
          INSERT INTO planet_buildings (planet_id, bkey, level)
          VALUES (?, ?, ?)
          ON DUPLICATE KEY UPDATE level = VALUES(level)
        ")->execute([$planetId, $j['bkey'], (int)$j['target_level']]);

                $pdo->prepare("DELETE FROM build_queue WHERE id = ?")->execute([(int)$j['id']]);
            }
            recalc_planet_production($planetId, $pdo);
            updateResourcesNow($planetId, $pdo);
        }

        if ($own) $pdo->commit();
    } catch (Throwable $e) { if ($own) $pdo->rollBack(); throw $e; }
}

/* Recalc productions /h stockées */
function recalc_planet_production(int $planetId, PDO $pdo = null): void {
    $pdo = $pdo ?: db();

    $st = $pdo->prepare("SELECT bkey, level FROM planet_buildings WHERE planet_id = ?");
    $st->execute([$planetId]);
    $levels = []; foreach ($st as $r) $levels[$r['bkey']] = (int)$r['level'];

    $cat = buildings_catalog();
    $metalPH = 0;
    $crystalPH = 0;
    $hydrogenPH = 0;
    $energyProdPH = 0;
    $energyConsPH = 0;

    foreach ($cat as $k => $b) {
        $lvl  = $levels[$k] ?? 0;
        $prod = $b['prod']($lvl);
        $cons = $b['energy_use']($lvl);

        switch ($b['affects']) {
            case 'metal':    $metalPH    += $prod; $energyConsPH += $cons; break;
            case 'crystal':  $crystalPH  += $prod; $energyConsPH += $cons; break;
            case 'hydrogen': $hydrogenPH += $prod; $energyConsPH += $cons; break;
            case 'energy':   $energyProdPH += $prod; break;
        }
    }

    $energyNetPH = (int)$energyProdPH - (int)$energyConsPH;

    $upd = $pdo->prepare("
    UPDATE planets
    SET prod_metal_per_hour = ?, prod_crystal_per_hour = ?, prod_hydrogen_per_hour = ?, prod_energy_per_hour = ?
    WHERE id = ?
  ");
    $upd->execute([(int)$metalPH, (int)$crystalPH, (int)$hydrogenPH, (int)$energyNetPH, $planetId]);
}

/* Lancer une construction */
function start_build(int $planetId, string $bkey): array {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        updateResourcesNow($planetId, $pdo);
        settle_finished_jobs($planetId, $pdo);

        $cnt = queue_count($planetId);
        if ($cnt >= 5) { $pdo->rollBack(); return ["ok"=>false, "error"=>"File complète (5 max)."]; }

        $levels = get_buildings($planetId);
        $qmap   = queued_levels_map($planetId);
        $virt   = (int)($levels[$bkey] ?? 0) + (int)($qmap[$bkey] ?? 0);

        $cost = building_next_cost($bkey, $virt);
        $time = building_next_time($bkey, $virt);

        $st = $pdo->prepare("SELECT metal, crystal FROM planets WHERE id = ? FOR UPDATE");
        $st->execute([$planetId]); $p = $st->fetch();
        if (!$p) { $pdo->rollBack(); return ["ok"=>false, "error"=>"Planète introuvable"]; }
        if ($p['metal'] < $cost['metal'] || $p['crystal'] < $cost['crystal']) {
            $pdo->rollBack(); return ["ok"=>false, "error"=>"Ressources insuffisantes"];
        }

        $pdo->prepare("UPDATE planets SET metal = metal - ?, crystal = crystal - ? WHERE id = ?")
            ->execute([$cost['metal'], $cost['crystal'], $planetId]);

        $st = $pdo->prepare("SELECT COALESCE(MAX(ends_at), NOW()) FROM build_queue WHERE planet_id = ? AND ends_at > NOW()");
        $st->execute([$planetId]); $startAt = $st->fetchColumn();

        $st = $pdo->prepare("SELECT (? + INTERVAL ? SECOND)");
        $st->execute([$startAt, $time]); $endsAt = $st->fetchColumn();

        $targetLevel = $virt + 1;

        $pdo->prepare("INSERT INTO build_queue (planet_id, bkey, target_level, ends_at) VALUES (?,?,?,?)")
            ->execute([$planetId, $bkey, $targetLevel, $endsAt]);

        $pdo->commit();
        return ["ok"=>true, "ends_at"=>$endsAt, "target_level"=>$targetLevel];
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ["ok"=>false, "error"=>"Erreur : ".$e->getMessage()];
    }
}
