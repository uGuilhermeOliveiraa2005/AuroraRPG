<?php

namespace Aurora\Services;

class CombatService
{
    /**
     * Calcula dano do jogador contra o monstro
     */
    public function calculatePlayerDamage(array $character, array $monster): array
    {
        $className = strtolower($character['class_name'] ?? '');
        
        // Define o atributo base de ataque
        if ($className === 'mago') {
            $baseAtk = $character['total_int'] * 2.5;
            $type = 'magical';
        } elseif ($className === 'arqueiro') {
            $baseAtk = $character['total_agi'] * 2;
            $type = 'physical';
        } else { // Guerreiro ou outros
            $baseAtk = $character['total_str'] * 2;
            $type = 'physical';
        }
        
        // Variância de dano: 85% a 115%
        $variance = rand(85, 115) / 100;
        $baseAtk = $baseAtk * $variance;

        // Chance de Acerto e Esquiva do Monstro
        // AGI do jogador vs AGI do monstro (simplificado: nível do monstro atua como AGI base x 2)
        $monsterAgi = $monster['level'] * 2;
        $hitChance = 80 + ($character['total_agi'] - $monsterAgi);
        $hitChance = max(30, min($hitChance, 100)); // Mínimo 30%, máximo 100%

        if (rand(1, 100) > $hitChance) {
            return ['damage' => 0, 'is_crit' => false, 'is_miss' => true, 'type' => $type];
        }

        // Chance Crítica (Máximo 50%)
        $critChance = min($character['total_agi'] * 0.5, 50);
        $isCrit = (rand(1, 100) <= $critChance);
        if ($isCrit) {
            $baseAtk *= 1.5;
        }

        // Mitigação de Armadura (Monster)
        $monsterDef = $monster['defense'];
        $mitigation = $monsterDef / ($monsterDef + 50); // Fórmula logarítmica suave
        
        if ($type === 'magical') {
            $mitigation *= 0.5; // Magia ignora 50% da armadura do monstro
        }

        $finalDamage = max(1, (int)($baseAtk * (1 - $mitigation)));
        
        return [
            'damage' => $finalDamage,
            'is_crit' => $isCrit,
            'is_miss' => false,
            'type' => $type
        ];
    }

    /**
     * Calcula dano do monstro contra o jogador
     */
    public function calculateMonsterDamage(array $character, array $monster): array
    {
        // Esquiva do Jogador
        $monsterAgi = $monster['level'] * 2;
        $dodgeChance = 10 + (($character['total_agi'] - $monsterAgi) * 0.5);
        $dodgeChance = max(5, min($dodgeChance, 40)); // Mínimo 5%, Máximo 40%
        
        $isDodge = (rand(1, 100) <= $dodgeChance);
        if ($isDodge) {
            return ['damage' => 0, 'is_dodge' => true];
        }

        // Dano Base do Monstro com Variância
        $baseDamage = rand($monster['damage_min'], $monster['damage_max']);
        
        // Defesa do Jogador: Equipamentos + Vitalidade + Força/2
        $playerDef = $character['total_vit'] * 1.5 + ($character['total_str'] * 0.5);
        $mitigation = $playerDef / ($playerDef + 100); // 100 DEF = 50% redução
        $mitigation = min($mitigation, 0.80); // Cap de 80% de redução

        $finalDamage = max(1, (int)($baseDamage * (1 - $mitigation)));

        return ['damage' => $finalDamage, 'is_dodge' => false];
    }

    /**
     * Verifica nível UP baseado na XP
     */
    public function checkLevelUp(array &$character): bool
    {
        $leveledUp = false;
        // Fórmula progressiva: (Nível ^ 1.5) * 100
        $xpRequired = (int)(pow($character['level'], 1.5) * 100);

        while ($character['xp'] >= $xpRequired) {
            $character['xp'] -= $xpRequired;
            $character['level']++;
            $character['stat_points'] += 3;
            
            // Bônus de classe ao subir de nível
            $className = strtolower($character['class_name'] ?? '');
            if ($className === 'guerreiro') {
                $character['max_hp'] += 15;
                $character['max_mana'] += 2;
            } elseif ($className === 'mago') {
                $character['max_hp'] += 6;
                $character['max_mana'] += 10;
            } else {
                $character['max_hp'] += 10;
                $character['max_mana'] += 5;
            }
            
            $character['hp'] = $character['max_hp'];
            $character['mana'] = $character['max_mana'];
            $leveledUp = true;
            
            // Recalcula XP para o próximo loop caso tenha subido mais de 1 nível de uma vez
            $xpRequired = (int)(pow($character['level'], 1.5) * 100);
        }
        return $leveledUp;
    }

}
