<?php
// includes/buildings_config.php
// Modifie cette config pour équilibrer le jeu.
function buildings_config(): array {
    return [
        'metal_mine' => [
            'label' => 'Mine de métal',
            'base_cost' => ['metal' => 60, 'crystal' => 15],
            'base_time' => 20,          // s
            'growth_cost' => 1.6,
            'growth_time' => 1.6,
            'prod_base' => 100,         // Métal/h @lvl1
            'prod_growth' => 1.15,
            'energy_use_base' => 10,    // conso/h @lvl1
            'energy_use_growth' => 1.10,
            'energy_use_linear' => true,
            'affects' => 'metal',
        ],
        'crystal_mine' => [
            'label' => 'Mine de cristal',
            'base_cost' => ['metal' => 48, 'crystal' => 24],
            'base_time' => 25,
            'growth_cost' => 1.6,
            'growth_time' => 1.6,
            'prod_base' => 50,          // Cristal/h @lvl1
            'prod_growth' => 1.15,
            'energy_use_base' => 15,
            'energy_use_growth' => 1.10,
            'energy_use_linear' => true,
            'affects' => 'crystal',
        ],
        'solar_plant' => [
            'label' => 'Centrale solaire',
            'base_cost' => ['metal' => 120, 'crystal' => 60],
            'base_time' => 30,
            'growth_cost' => 1.6,
            'growth_time' => 1.6,
            'prod_base' => 100,          // Énergie/h @lvl1
            'prod_growth' => 1.12,
            'energy_use_base' => 0,     // pas de conso
            'energy_use_growth' => 1.0,
            'energy_use_linear' => false,
            'affects' => 'energy',
        ],
        // 🆕 Générateur d’hydrogène (électrolyse) – produit H₂, consomme énergie
        'hydrogen_plant' => [
            'label' => 'Générateur d’hydrogène',
            'base_cost' => ['metal' => 150, 'crystal' => 100],
            'base_time' => 35,
            'growth_cost' => 1.6,
            'growth_time' => 1.6,
            'prod_base' => 30,          // Hydrogène/h @lvl1
            'prod_growth' => 1.15,
            'energy_use_base' => 20,    // conso/h @lvl1
            'energy_use_growth' => 1.10,
            'energy_use_linear' => true,
            'affects' => 'hydrogen',
        ],
    ];
}
