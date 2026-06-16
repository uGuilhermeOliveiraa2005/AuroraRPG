<?php

namespace Aurora\Controllers;

use Aurora\Core\TelegramBot;
use Aurora\Repositories\UserRepository;
use Aurora\Repositories\CharacterRepository;
use Aurora\Repositories\CombatRepository;
use Aurora\Repositories\InventoryRepository;
use Aurora\Services\CombatService;
use Aurora\Core\Database;

class CombatController
{
    private TelegramBot $bot;
    private UserRepository $userRepo;
    private CharacterRepository $charRepo;
    private CombatRepository $combatRepo;
    private InventoryRepository $invRepo;
    private CombatService $combatSvc;

    public function __construct(TelegramBot $bot)
    {
        $this->bot = $bot;
        $this->userRepo = new UserRepository();
        $this->charRepo = new CharacterRepository();
        $this->combatRepo = new CombatRepository();
        $this->invRepo = new InventoryRepository();
        $this->combatSvc = new CombatService();
    }

    public function explore(int|string $chatId, int $userId): void
    {
        $user = $this->userRepo->findById($userId);
        if (!$user) {
            $this->bot->sendMessage($chatId, "Use /registrar primeiro.");
            return;
        }

        if ($user['state'] === 'combat') {
            $this->bot->sendMessage($chatId, "⚠️ Você já está em combate! Termine sua luta ou fuja antes de explorar novamente.");
            return;
        }

        $character = $this->charRepo->findByUserId($userId);
        if (!$character) {
            $this->bot->sendMessage($chatId, "Você precisa criar um personagem primeiro.");
            return;
        }

        if ($character['hp'] <= 0) {
            $this->bot->sendMessage($chatId, "Você está morto! Descanse ou use uma poção antes de explorar.");
            return;
        }

        // Busca monstro compatível
        $monster = $this->combatRepo->getRandomMonsterByArea($character['level']);
        if (!$monster) {
            $this->bot->sendMessage($chatId, "Nenhum monstro encontrado nesta área.");
            return;
        }

        // Atualiza estado do jogador
        $this->userRepo->updateState($userId, 'combat');

        // Cria instância de combate
        $this->combatRepo->createCombatInstance($character['id'], $monster['id'], $monster['hp']);

        $text = "🌲 <b>Você está explorando...</b>\n\n";
        $text .= "⚠️ <b>Um monstro apareceu!</b>\n";
        $text .= "👾 <b>{$monster['name']}</b> (Lvl {$monster['level']})\n";
        $text .= "❤️ HP: {$monster['hp']}/{$monster['hp']}\n\n";
        $text .= "O que você vai fazer?";

        $keyboard = ['inline_keyboard' => [
            [
                ['text' => '⚔️ Atacar', 'callback_data' => "combat_attack"],
                ['text' => '🏃 Fugir', 'callback_data' => "combat_flee"]
            ]
        ]];

        $this->bot->sendMessage($chatId, $text, $keyboard);
    }

    public function attack(int|string $chatId, int $userId, int $messageId, string $callbackId): void
    {
        $user = $this->userRepo->findById($userId);
        if (!$user || $user['state'] !== 'combat') {
            $this->bot->answerCallbackQuery($callbackId, "Você não está em combate.", true);
            return;
        }

        $character = $this->charRepo->findByUserId($userId);
        $combat = $this->combatRepo->getCombatInstance($character['id']);

        if (!$combat) {
            $this->userRepo->updateState($userId, 'idle');
            $this->bot->editMessageText($chatId, $messageId, "O monstro desapareceu.");
            return;
        }

        // === Turno do Jogador ===
        $playerAttack = $this->combatSvc->calculatePlayerDamage($character, $combat);
        $monsterHp = max(0, $combat['monster_current_hp'] - $playerAttack['damage']);
        
        $log = "⚔️ Você ataca o <b>{$combat['name']}</b> e causa <b>{$playerAttack['damage']}</b> de dano!" . ($playerAttack['is_crit'] ? " (Acerto Crítico! 💥)" : "") . "\n";

        // Verifica se Monstro Morreu
        if ($monsterHp <= 0) {
            $this->bot->answerCallbackQuery($callbackId);
            $this->endCombatVictory($chatId, $messageId, $character, $combat, $log);
            return;
        }

        // === Turno do Monstro ===
        $monsterAttack = $this->combatSvc->calculateMonsterDamage($character, $combat);
        $playerHp = max(0, $character['hp'] - $monsterAttack['damage']);

        if ($monsterAttack['is_dodge']) {
            $log .= "💨 Você <b>esquivou</b> do ataque do monstro!\n";
        } else {
            $log .= "🩸 O monstro te ataca e causa <b>{$monsterAttack['damage']}</b> de dano!\n";
        }

        // Verifica se Jogador Morreu
        if ($playerHp <= 0) {
            $this->bot->answerCallbackQuery($callbackId);
            $this->endCombatDefeat($chatId, $messageId, $character, $combat, $log);
            return;
        }

        // Salva estados
        $this->combatRepo->updateMonsterHp($combat['id'], $monsterHp, $combat['turn_count'] + 1);
        $this->combatRepo->updateCharacterHpAndRewards($character['id'], $playerHp); // Só salva HP por enquanto

        // Atualiza mensagem
        $text = "👾 <b>{$combat['name']}</b> (Lvl {$combat['level']})\n";
        $text .= "❤️ HP do Monstro: {$monsterHp}/{$combat['max_hp']}\n";
        $text .= "❤️ Seu HP: {$playerHp}/{$character['max_hp']}\n\n";
        $text .= $log;

        $keyboard = ['inline_keyboard' => [
            [
                ['text' => '⚔️ Atacar', 'callback_data' => "combat_attack"],
                ['text' => '🏃 Fugir', 'callback_data' => "combat_flee"]
            ]
        ]];

        $this->bot->answerCallbackQuery($callbackId);
        $this->bot->editMessageText($chatId, $messageId, $text, $keyboard);
    }

    public function flee(int|string $chatId, int $userId, int $messageId, string $callbackId): void
    {
        $user = $this->userRepo->findById($userId);
        if (!$user || $user['state'] !== 'combat') {
            $this->bot->answerCallbackQuery($callbackId, "Você não está em combate.", true);
            return;
        }

        $character = $this->charRepo->findByUserId($userId);
        $combat = $this->combatRepo->getCombatInstance($character['id']);

        if ($combat) {
            $this->combatRepo->deleteCombatInstance($combat['id']);
        }

        $this->userRepo->updateState($userId, 'idle');
        
        // Cura 50% para facilitar nos testes
        $newHp = (int)($character['max_hp'] * 0.5);
        if ($character['hp'] < $newHp) {
            $this->combatRepo->updateCharacterHpAndRewards($character['id'], $newHp);
        }

        $this->bot->answerCallbackQuery($callbackId);
        $this->bot->editMessageText($chatId, $messageId, "🏃 Você fugiu da batalha com sucesso! Seu HP foi restaurado para 50%.");
    }

    private function endCombatVictory(int|string $chatId, int $messageId, array $character, array $combat, string $log): void
    {
        $this->combatRepo->deleteCombatInstance($combat['id']);
        $this->userRepo->updateState($character['user_id'], 'idle');

        $xp = $combat['base_xp'];
        $gold = $combat['base_gold'];

        $character['xp'] += $xp;
        $character['gold'] += $gold;
        $leveledUp = $this->combatSvc->checkLevelUp($character);

        // Atualiza banco com xp e ouro. SaveLevel() via db:
        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE characters SET hp = :hp, xp = :xp, gold = :gold, level = :level, stat_points = :sp, max_hp = :maxhp, max_mana = :maxmana, mana = :mana WHERE id = :id");
        $stmt->execute([
            'hp' => $character['hp'],
            'xp' => $character['xp'],
            'gold' => $character['gold'],
            'level' => $character['level'],
            'sp' => $character['stat_points'],
            'maxhp' => $character['max_hp'],
            'maxmana' => $character['max_mana'],
            'mana' => $character['mana'],
            'id' => $character['id']
        ]);

        $text = "🎉 <b>Vitória!</b>\n\n";
        $text .= $log . "\n";
        $text .= "Você derrotou o <b>{$combat['name']}</b>!\n";
        $text .= "Obteve: <b>{$xp} XP</b> e 🪙 <b>{$gold} Ouro</b>.\n";

        // Lógica de Drops
        $drops = $this->invRepo->getMonsterDrops($combat['monster_id']);
        $droppedItems = [];
        foreach ($drops as $drop) {
            $chance = rand(1, 100);
            if ($chance <= $drop['drop_chance']) {
                $qty = rand($drop['min_quantity'], $drop['max_quantity']);
                $this->invRepo->addItem($character['id'], $drop['item_id'], $qty);
                $droppedItems[] = "{$qty}x {$drop['name']}";
            }
        }

        if (!empty($droppedItems)) {
            $text .= "📦 Drops: " . implode(", ", $droppedItems) . ".\n";
        }

        if ($leveledUp) {
            $text .= "\n🌟 <b>LEVEL UP!</b> Você alcançou o nível {$character['level']}!\nUse /atributos para distribuir seus pontos.";
        }

        $this->bot->editMessageText($chatId, $messageId, $text);
    }

    private function endCombatDefeat(int|string $chatId, int $messageId, array $character, array $combat, string $log): void
    {
        $this->combatRepo->deleteCombatInstance($combat['id']);
        $this->userRepo->updateState($character['user_id'], 'idle');

        // Punição: Perde 10% do XP atual e volta com 50% do HP
        $xpLoss = (int)($character['xp'] * 0.1);
        $newXp = max(0, $character['xp'] - $xpLoss);
        $newHp = (int)($character['max_hp'] * 0.5);

        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE characters SET hp = :hp, xp = :xp WHERE id = :id");
        $stmt->execute(['hp' => $newHp, 'xp' => $newXp, 'id' => $character['id']]);

        $text = "💀 <b>Derrota...</b>\n\n";
        $text .= $log . "\n";
        $text .= "Você foi derrotado pelo <b>{$combat['name']}</b>.\n";
        $text .= "Você perdeu <b>{$xpLoss} XP</b> e acordou na cidade com 50% de HP.";

        $this->bot->editMessageText($chatId, $messageId, $text);
    }
}
