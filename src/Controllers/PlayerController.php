<?php

namespace Aurora\Controllers;

use Aurora\Core\TelegramBot;
use Aurora\Repositories\UserRepository;
use Aurora\Repositories\CharacterRepository;

class PlayerController
{
    private TelegramBot $bot;
    private UserRepository $userRepo;
    private CharacterRepository $charRepo;

    public function __construct(TelegramBot $bot)
    {
        $this->bot = $bot;
        $this->userRepo = new UserRepository();
        $this->charRepo = new CharacterRepository();
    }

    public function register(int|string $chatId, int $userId, string $username, string $firstName): void
    {
        // 1. Criar/Atualizar Usuário
        $this->userRepo->create($userId, $username, $firstName);

        // 2. Checar se já tem personagem
        $character = $this->charRepo->findByUserId($userId);
        if ($character) {
            $this->bot->sendMessage($chatId, "Você já possui um personagem: <b>{$character['name']}</b> (Lvl {$character['level']}).\nUse /perfil para ver seus status.");
            return;
        }

        // 3. Oferecer Classes
        $classes = $this->charRepo->getClasses();
        $keyboard = ['inline_keyboard' => []];

        foreach ($classes as $class) {
            $keyboard['inline_keyboard'][] = [
                ['text' => "⚔️ {$class['name']}", 'callback_data' => "class_select:{$class['id']}"]
            ];
        }

        $text = "<b>Criação de Personagem</b>\n\nEscolha sua classe inicial para começar sua jornada em Aurora:";
        $this->bot->sendMessage($chatId, $text, $keyboard);
    }

    public function processRegistration(int|string $chatId, int $userId, int $classId, int $messageId): void
    {
        $character = $this->charRepo->findByUserId($userId);
        if ($character) {
            $this->bot->editMessageText($chatId, $messageId, "Você já possui um personagem!");
            return;
        }

        $user = $this->userRepo->findById($userId);
        if (!$user) {
            $this->bot->editMessageText($chatId, $messageId, "Erro: Use /registrar primeiro.");
            return;
        }

        $success = $this->charRepo->create($userId, $user['first_name'], $classId);

        if ($success) {
            $this->bot->editMessageText($chatId, $messageId, "🎉 <b>Personagem criado com sucesso!</b>\n\nUse /perfil para ver seus status e /explorar para começar sua jornada!");
        } else {
            $this->bot->editMessageText($chatId, $messageId, "⚠️ Erro ao criar personagem. Tente novamente.");
        }
    }

    private function generateProgressBar(int $current, int $max, int $length = 10): string
    {
        $max = max(1, $max); // Prevenir divisão por zero
        $percent = $current / $max;
        $filledBlocks = (int)round($percent * $length);
        $emptyBlocks = $length - $filledBlocks;
        
        $bar = str_repeat('█', max(0, $filledBlocks)) . str_repeat('░', max(0, $emptyBlocks));
        return "<code>[{$bar}]</code> " . floor($percent * 100) . "%";
    }

    public function profile(int|string $chatId, int $userId, ?int $messageId = null): void
    {
        $character = $this->charRepo->findByUserId($userId);
        if (!$character) {
            $this->bot->sendMessage($chatId, "Você ainda não possui um personagem. Use /registrar para criar um.");
            return;
        }

        // Calcula XP necessário
        $xpRequired = (int)(pow($character['level'], 1.5) * 100);

        $text = "👤 <b>Ficha de Personagem: {$character['name']}</b>\n";
        $text .= "📜 Classe: <b>{$character['class_name']}</b> | ⭐ Nível: <b>{$character['level']}</b>\n";
        $text .= "🪙 Ouro: <b>{$character['gold']}</b>\n\n";
        
        $text .= "❤️ HP: {$character['hp']}/{$character['max_hp']}\n";
        $text .= $this->generateProgressBar($character['hp'], $character['max_hp']) . "\n";
        $text .= "🧪 MP: {$character['mana']}/{$character['max_mana']}\n";
        $text .= $this->generateProgressBar($character['mana'], $character['max_mana']) . "\n";
        $text .= "✨ XP: {$character['xp']}/{$xpRequired}\n";
        $text .= $this->generateProgressBar($character['xp'], $xpRequired) . "\n\n";
        
        $text .= "<b>⚔️ Atributos Básicos:</b>\n";
        $text .= "💪 Força: <b>{$character['str']}</b>\n";
        $text .= "🏃 Agilidade: <b>{$character['agi']}</b>\n";
        $text .= "🧠 Inteligência: <b>{$character['int']}</b>\n";
        $text .= "🛡️ Vitalidade: <b>{$character['vit']}</b>\n\n";

        $keyboard = null;

        if ($character['stat_points'] > 0) {
            $text .= "<i>🌟 Você tem <b>{$character['stat_points']}</b> pontos de atributo disponíveis!</i>";
            
            $keyboard = ['inline_keyboard' => [
                [
                    ['text' => '💪 +1 Força', 'callback_data' => 'stat_add:str'],
                    ['text' => '🏃 +1 Agilidade', 'callback_data' => 'stat_add:agi']
                ],
                [
                    ['text' => '🧠 +1 Inteligência', 'callback_data' => 'stat_add:int'],
                    ['text' => '🛡️ +1 Vitalidade', 'callback_data' => 'stat_add:vit']
                ]
            ]];
        }

        if (isset($messageId)) {
            if ($keyboard) {
                $this->bot->editMessageText($chatId, $messageId, $text, $keyboard);
            } else {
                $this->bot->editMessageText($chatId, $messageId, $text);
            }
        } else {
            if ($keyboard) {
                $this->bot->sendMessage($chatId, $text, $keyboard);
            } else {
                $this->bot->sendMessage($chatId, $text);
            }
        }
    }

    public function addStat(int|string $chatId, int $userId, int $messageId, string $callbackId, string $stat): void
    {
        $character = $this->charRepo->findByUserId($userId);
        if (!$character || $character['stat_points'] <= 0) {
            $this->bot->answerCallbackQuery($callbackId, "Você não tem pontos disponíveis.", true);
            return;
        }

        $success = $this->charRepo->addStatPoint($character['id'], $stat);

        if ($success) {
            $this->bot->answerCallbackQuery($callbackId, "Ponto distribuído com sucesso!");
            $this->profile($chatId, $userId, $messageId);
        } else {
            $this->bot->answerCallbackQuery($callbackId, "Erro ao distribuir ponto.", true);
        }
    }

    public function rest(int|string $chatId, int $userId): void
    {
        $character = $this->charRepo->findByUserId($userId);
        if (!$character) {
            $this->bot->sendMessage($chatId, "Você precisa criar um personagem antes de descansar.");
            return;
        }

        $user = $this->userRepo->findById($userId);
        if ($user['state'] === 'combat') {
            $this->bot->sendMessage($chatId, "⚔️ Você não pode descansar na taverna enquanto está no meio de uma batalha!");
            return;
        }

        if ($character['hp'] >= $character['max_hp'] && $character['mana'] >= $character['max_mana']) {
            $this->bot->sendMessage($chatId, "✨ Sua energia já está completa. Vá explorar!");
            return;
        }

        $cost = $character['level'] * 5; // Custo escala com o nível

        if ($character['gold'] < $cost) {
            $this->bot->sendMessage($chatId, "🏨 Você precisa de 🪙 <b>{$cost}</b> de Ouro para pagar a estadia na taverna.");
            return;
        }

        $db = \Aurora\Core\Database::getInstance();
        $stmt = $db->prepare("UPDATE characters SET hp = max_hp, mana = max_mana, gold = gold - :cost WHERE id = :id");
        $stmt->execute(['cost' => $cost, 'id' => $character['id']]);

        $this->bot->sendMessage($chatId, "🛌 <b>Você descansou na taverna!</b>\n\nPor 🪙 {$cost} Ouro, você dormiu profundamente e recuperou todo o seu HP e Mana.");
    }
}
