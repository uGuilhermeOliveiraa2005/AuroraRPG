<?php

namespace Aurora\Controllers;

use Aurora\Core\TelegramBot;
use Aurora\Repositories\UserRepository;
use Aurora\Repositories\CharacterRepository;
use Aurora\Repositories\AreaRepository;

class CityController
{
    private TelegramBot $bot;
    private UserRepository $userRepo;
    private CharacterRepository $charRepo;
    private AreaRepository $areaRepo;

    public function __construct(TelegramBot $bot)
    {
        $this->bot = $bot;
        $this->userRepo = new UserRepository();
        $this->charRepo = new CharacterRepository();
        $this->areaRepo = new AreaRepository();
    }

    public function showCity(int|string $chatId, int $userId, ?int $messageId = null): void
    {
        $character = $this->charRepo->findByUserId($userId);
        if (!$character) {
            $this->bot->sendMessage($chatId, "Crie um personagem primeiro com /registrar.");
            return;
        }

        $user = $this->userRepo->findById($userId);
        if ($user['state'] === 'combat') {
            $this->bot->sendMessage($chatId, "Você não pode visitar a cidade enquanto está em combate!");
            return;
        }

        $areaId = $character['current_area_id'] ?? 1;
        $area = $this->areaRepo->getAreaById($areaId);

        if ($areaId == 1) {
            $text = "🏘️ <b>Vila de Alvorada</b>\n\n";
            $text .= "O ar cheira a lenha queimada. A cidade está movimentada com aventureiros e mercadores.\n\n";
            $text .= "Com quem você quer falar?";

            $keyboard = ['inline_keyboard' => [
                [
                    ['text' => '🧙‍♂️ Ancião Kaelen (Missões)', 'callback_data' => 'npc:ancian']
                ],
                [
                    ['text' => '🛠️ Ferreiro Brokk', 'callback_data' => 'npc:blacksmith'],
                    ['text' => '🍻 Taverna (Descansar)', 'callback_data' => 'npc:tavern']
                ]
            ]];
        } else {
            $text = "🏕️ <b>Acampamento de {$area['name']}</b>\n\n";
            $text .= "Um pequeno refúgio seguro no meio do perigo.\n\n";
            $text .= "Com quem você quer falar?";

            $keyboard = ['inline_keyboard' => [
                [
                    ['text' => '🍻 Barraca de Descanso', 'callback_data' => 'npc:tavern']
                ]
            ]];
        }

        if ($messageId) {
            $this->bot->editMessageText($chatId, $messageId, $text, $keyboard);
        } else {
            $this->bot->sendMessage($chatId, $text, $keyboard);
        }
    }

    public function interactNpc(int|string $chatId, int $userId, int $messageId, string $callbackId, string $npcId): void
    {
        if ($npcId === 'tavern') {
            $this->bot->answerCallbackQuery($callbackId);
            $playerController = new PlayerController($this->bot);
            $playerController->rest($chatId, $userId);
            // We could delete the inline keyboard here to avoid spam
            return;
        }

        if ($npcId === 'blacksmith') {
            $this->bot->answerCallbackQuery($callbackId, "Ferreiro Brokk: 'Volte depois, minha forja ainda está esquentando!'", true);
            return;
        }

        if ($npcId === 'ancian') {
            $this->bot->answerCallbackQuery($callbackId);
            $questController = new QuestController($this->bot);
            $questController->showQuests($chatId, $userId, $messageId);
            return;
        }

        $this->bot->answerCallbackQuery($callbackId, "NPC ocupado.", true);
    }
}
