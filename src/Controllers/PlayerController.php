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

    public function profile(int|string $chatId, int $userId, ?int $messageId = null): void
    {
        $character = $this->charRepo->findByUserId($userId);
        if (!$character) {
            $this->bot->sendMessage($chatId, "Você ainda não possui um personagem. Use /registrar para criar um.");
            return;
        }

        $text = "👤 <b>Perfil de {$character['name']}</b>\n";
        $text .= "Classe: {$character['class_name']} | Nível: {$character['level']}\n";
        $text .= "XP: {$character['xp']} | Ouro: 🪙 {$character['gold']}\n\n";
        
        $text .= "❤️ HP: {$character['hp']}/{$character['max_hp']}\n";
        $text .= "🧪 Mana: {$character['mana']}/{$character['max_mana']}\n\n";
        
        $text .= "<b>Atributos:</b>\n";
        $text .= "💪 Força: {$character['str']}\n";
        $text .= "🏃 Agilidade: {$character['agi']}\n";
        $text .= "🧠 Inteligência: {$character['int']}\n";
        $text .= "🛡️ Vitalidade: {$character['vit']}\n\n";

        $keyboard = null;

        if ($character['stat_points'] > 0) {
            $text .= "<i>Você tem {$character['stat_points']} pontos disponíveis! Use os botões abaixo para distribuir.</i>";
            
            $keyboard = ['inline_keyboard' => [
                [
                    ['text' => '💪 +1 Força', 'callback_data' => 'stat_add:str'],
                    ['text' => '🏃 +1 Agilidade', 'callback_data' => 'stat_add:agi']
                ],
                [
                    ['text' => '🧠 +1 Int', 'callback_data' => 'stat_add:int'],
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
            $this->bot->answerCallbackQuery($callbackId, "Ponto distribuído!");
            // Recarrega o perfil com messageId para editar a mesma mensagem
            $this->profile($chatId, $userId, $messageId);
        } else {
            $this->bot->answerCallbackQuery($callbackId, "Erro ao distribuir ponto.", true);
        }
    }
}
