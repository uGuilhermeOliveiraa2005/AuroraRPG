<?php

namespace Aurora\Services;

class CombatService
{
    /**
     * Calcula dano do jogador contra o monstro
     */
    public function calculatePlayerDamage(array $character, array $monster): array
    {
        $baseDamage = ($character['total_str'] * 2);
        
        // Chance Crítica
        $critChance = min($character['total_agi'] * 0.5, 50); // Máximo 50%
        $isCrit = (rand(1, 100) <= $critChance);
        
        if ($isCrit) {
            $baseDamage = (int)($baseDamage * 1.5);
        }

        $finalDamage = max(1, $baseDamage - $monster['defense']);
        
        return [
            'damage' => $finalDamage,
            'is_crit' => $isCrit
        ];
    }

    /**
     * Calcula dano do monstro contra o jogador
     */
    public function calculateMonsterDamage(array $character, array $monster): array
    {
        // Esquiva do Jogador
        $dodgeChance = min($character['total_agi'] * 0.3, 40); // Máximo 40%
        $isDodge = (rand(1, 100) <= $dodgeChance);

        if ($isDodge) {
            return ['damage' => 0, 'is_dodge' => true];
        }

        $baseDamage = rand($monster['damage_min'], $monster['damage_max']);
        
        // Defesa total = metade do STR total + vitalidade total
        $playerDefense = (int)(($character['total_str'] * 0.5) + ($character['total_vit'] * 0.5)); 
        
        $finalDamage = max(1, $baseDamage - $playerDefense);

        return ['damage' => $finalDamage, 'is_dodge' => false];
    }

    /**
     * Verifica nível UP baseado na XP
     */
    public function checkLevelUp(array &$character): bool
    {
        $leveledUp = false;
        // Fórmula básica progressiva: Nível * 100
        while ($character['xp'] >= ($character['level'] * 100)) {
            $character['xp'] -= ($character['level'] * 100);
            $character['level']++;
            $character['stat_points'] += 3;
            $character['max_hp'] += 10;
            $character['hp'] = $character['max_hp'];
            $character['max_mana'] += 5;
            $character['mana'] = $character['max_mana'];
            $leveledUp = true;
        }
        return $leveledUp;
    }
}
