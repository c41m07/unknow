<?php
// includes/game.php
require_once __DIR__ . "/db.php";
require_once __DIR__ . "/buildings_config.php";

/* Helpers de calcul depuis la config (préfixés cfg_) */
function cfg_prod_at_level(array $cfg, int $lvl): int {
    if ($lvl <= 0) return 0;
    return (int) round(($cfg['prod_base'] ?? 0) * pow(($cfg['prod_growth'] ?? 1.0), $lvl - 1));
}
function cfg_energy_use_at_level(array $cfg, int $lvl): int {
    if ($lvl <= 0) return 0;
    $base = $cfg['energy_use_base'] ?? 0;
    $g    = $cfg['energy_use_growth'] ?? 1.0;
    $lin  = !empty($cfg['energy_use_linear']);
    $val  = $base * pow($g, $lvl);
    if ($lin) $val *= $lvl;
    return (int) round($val);
}

/** Calcule les taux /h à partir des niveaux */
function computePlanetRates(int $planetId, PDO $pdo = null): array {
    $pdo = $pdo ?: db();
    $st  = $pdo->prepare("SELECT bkey, level FROM planet_buildings WHERE planet_id = ?");
    $st->execute([$planetId]);
    $levels = [];
    foreach ($st as $r) $levels[$r['bkey']] = (int)$r['level'];

    $cfg = buildings_config();
    $mPH = $cPH = $hPH = $eProdPH = $eConsPH = 0;

    foreach ($cfg as $k => $c) {
        $lvl  = $levels[$k] ?? 0;
        $prod = cfg_prod_at_level($c, $lvl);
        $cons = cfg_energy_use_at_level($c, $lvl);

        $aff = $c['affects'] ?? '';
        if ($aff === 'metal')    { $mPH += $prod; $eConsPH += $cons; }
        if ($aff === 'crystal')  { $cPH += $prod; $eConsPH += $cons; }
        if ($aff === 'hydrogen') { $hPH += $prod; $eConsPH += $cons; }
        if ($aff === 'energy')   { $eProdPH += $prod; }
    }
    return [
        'mPH' => (int)$mPH,
        'cPH' => (int)$cPH,
        'hPH' => (int)$hPH,
        'eProdPH' => (int)$eProdPH,
        'eConsPH' => (int)$eConsPH,
        'eNetPH'  => (int)($eProdPH - $eConsPH),
    ];
}

/* Création planète + renommage */
function getOrCreateHomeworld(int $userId): array {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM planets WHERE user_id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $p = $stmt->fetch();

        if (!$p) {
            $pdo->prepare("
        INSERT INTO planets (user_id, name, metal, crystal, hydrogen, energy,
                             prod_metal_per_hour, prod_crystal_per_hour, prod_hydrogen_per_hour, prod_energy_per_hour)
        VALUES (?, 'Planète mère', 500, 250, 0, 0, 100, 50, 0, 0)
      ")->execute([$userId]);
            $stmt = $pdo->prepare("SELECT * FROM planets WHERE user_id = ?");
            $stmt->execute([$userId]);
            $p = $stmt->fetch();
        }

        $p = updateResourcesNow((int)$p['id'], $pdo);
        $pdo->commit();
        return $p;
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
}

function renamePlanet(int $planetId, int $userId, string $name): array {
    $name = trim($name);
    if ($name === "" || mb_strlen($name) > 50) return ["ok"=>false, "error"=>"Nom invalide (1–50 caractères)"];
    $pdo = db();
    $stmt = $pdo->prepare("UPDATE planets SET name = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$name, $planetId, $userId]);
    if ($stmt->rowCount() === 0) return ["ok"=>false, "error"=>"Action non autorisée"];
    return ["ok"=>true];
}

/** ✅ Abandonner une planète (colonie)
 *  - Interdit si c’est la dernière planète du joueur
 *  - Supprime file de construction + niveaux de bâtiments + la planète
 */
function abandonPlanet(int $planetId, int $userId): array {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Vérifier appartenance + compter le total de planètes
        $st = $pdo->prepare("SELECT id FROM planets WHERE id=? AND user_id=? FOR UPDATE");
        $st->execute([$planetId, $userId]);
        if (!$st->fetch()) { $pdo->rollBack(); return ["ok"=>false, "error"=>"Planète introuvable ou non autorisée."]; }

        $cnt = $pdo->prepare("SELECT COUNT(*) FROM planets WHERE user_id=?");
        $cnt->execute([$userId]);
        $total = (int)$cnt->fetchColumn();
        if ($total <= 1) { $pdo->rollBack(); return ["ok"=>false, "error"=>"Impossible d’abandonner votre dernière planète."]; }

        // Nettoyer dépendances (d’autres tables à ajouter plus tard : flottes, défenses, etc.)
        $pdo->prepare("DELETE FROM build_queue WHERE planet_id=?")->execute([$planetId]);
        $pdo->prepare("DELETE FROM planet_buildings WHERE planet_id=?")->execute([$planetId]);

        // Supprimer la planète
        $pdo->prepare("DELETE FROM planets WHERE id=?")->execute([$planetId]);

        $pdo->commit();
        return ["ok"=>true];
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ["ok"=>false, "error"=>"Erreur: ".$e->getMessage()];
    }
}

/* Tick ressources + malus si énergie = 0 (appliqué métal/cristal/hydrogène) */
function updateResourcesNow(int $planetId, PDO $pdo = null): array {
    $pdo = $pdo ?: db();

    $stmt = $pdo->prepare("SELECT * FROM planets WHERE id = ? FOR UPDATE");
    $stmt->execute([$planetId]);
    $p = $stmt->fetch();
    if (!$p) throw new RuntimeException("Planète introuvable");

    $stmt = $pdo->prepare("SELECT GREATEST(TIMESTAMPDIFF(SECOND, last_update, NOW()), 0) FROM planets WHERE id = ?");
    $stmt->execute([$planetId]);
    $elapsed = (int)$stmt->fetchColumn();
    if ($elapsed <= 0) return $p;

    // Taux stockés
    $mPH_stored = (int)$p['prod_metal_per_hour'];
    $cPH_stored = (int)$p['prod_crystal_per_hour'];
    $hPH_stored = (int)$p['prod_hydrogen_per_hour'];

    // Énergie physique
    $rates   = computePlanetRates($planetId, $pdo);
    $eProdPH = $rates['eProdPH'];
    $eConsPH = $rates['eConsPH'];
    $eNetPH  = $rates['eNetPH'];

    // /s
    $mPS = $mPH_stored / 3600.0;
    $cPS = $cPH_stored / 3600.0;
    $hPS = $hPH_stored / 3600.0;
    $eNetPS = $eNetPH / 3600.0;

    $energyStock = (int)$p['energy'];

    // Phase normale (avant 0 énergie)
    $tNormal = 0;
    if ($eNetPH >= 0) {
        $tNormal = $elapsed;
    } else {
        if ($energyStock > 0) {
            $timeToZero = (int) floor($energyStock / (-$eNetPS));
            $tNormal = max(0, min($elapsed, $timeToZero));
        } else {
            $tNormal = 0;
        }
    }
    $tMalus = $elapsed - $tNormal;

    // Gains phase normale
    $gainM = $mPS * $tNormal;
    $gainC = $cPS * $tNormal;
    $gainH = $hPS * $tNormal;
    $gainE = $eNetPS * $tNormal;

    $energyAfterNormal = max(0, (int)round($energyStock + $gainE));

    // Malus si énergie au plancher
    if ($tMalus > 0) {
        $factor = 1.0;
        if ($eConsPH > 0) $factor = max(0.0, min(1.0, $eProdPH / $eConsPH));

        $gainM += ($mPS * $factor) * $tMalus;
        $gainC += ($cPS * $factor) * $tMalus;
        $gainH += ($hPS * $factor) * $tMalus;

        if ($eNetPH > 0) {
            $gainE += ($eNetPH / 3600.0) * $tMalus; // remonte le stock si excédentaire
        }
    }

    $dM = (int) floor($gainM);
    $dC = (int) floor($gainC);
    $dH = (int) floor($gainH);
    $newEnergy = max(0, (int)round($energyAfterNormal + ($tMalus > 0 && $eNetPH > 0 ? ($eNetPH/3600.0)*$tMalus : 0)));

    $upd = $pdo->prepare("
    UPDATE planets
    SET metal = metal + ?, crystal = crystal + ?, hydrogen = hydrogen + ?, energy = ?, last_update = NOW()
    WHERE id = ?
  ");
    $upd->execute([$dM, $dC, $dH, $newEnergy, $planetId]);

    $stmt = $pdo->prepare("SELECT * FROM planets WHERE id = ?");
    $stmt->execute([$planetId]);
    return $stmt->fetch();
}
