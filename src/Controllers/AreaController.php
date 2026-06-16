<?php

namespace Aurora\Controllers;

use Aurora\Core\TelegramBot;
use Aurora\Repositories\UserRepository;
use Aurora\Repositories\CharacterRepository;
use Aurora\Repositories\AreaRepository;

class AreaController
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

    public function showMap(int|string $chatId, int $userId, ?int $messageId = null): void
    {
        $character = $this->charRepo->findByUserId($userId);
        if (!$character) {
            $msg = "Você precisa de um personagem.";
            if ($messageId) $this->bot->editMessageText($chatId, $messageId, $msg);
            else $this->bot->sendMessage($chatId, $msg);
            return;
        }

        $user = $this->userRepo->findById($userId);
        if ($user['state'] === 'combat') {
            $msg = "Você não pode abrir o mapa enquanto está em combate!";
            if ($messageId) $this->bot->editMessageText($chatId, $messageId, $msg);
            else $this->bot->sendMessage($chatId, $msg);
            return;
        }

        $areas = $this->areaRepo->getAreas();
        $currentAreaId = $character['current_area_id'] ?? 1;

        $text = "🗺️ <b>Mapa de Aurora</b>\n\nEscolha para onde deseja viajar. Áreas mais perigosas exigem níveis maiores.\n";
        $keyboard = ['inline_keyboard' => []];

        foreach ($areas as $area) {
            $prefix = ($area['id'] == $currentAreaId) ? "📍 [AQUI] " : "🧭 ";
            $locked = ($character['level'] < $area['min_level']) ? " 🔒" : "";
            $buttonText = "{$prefix}{$area['name']} (Lvl {$area['min_level']}-{$area['max_level']}){$locked}";
            
            $callback = "map_travel:{$area['id']}";
            
            // Só 1 botão por linha para ficar bonito
            $keyboard['inline_keyboard'][] = [
                ['text' => $buttonText, 'callback_data' => $callback]
            ];
        }

        if ($messageId) {
            $this->bot->editMessageText($chatId, $messageId, $text, $keyboard);
        } else {
            $this->bot->sendMessage($chatId, $text, $keyboard);
        }
    }

    public function travel(int|string $chatId, int $userId, int $messageId, string $callbackId, int $areaId): void
    {
        $character = $this->charRepo->findByUserId($userId);
        $area = $this->areaRepo->getAreaById($areaId);

        if (!$area) {
            $this->bot->answerCallbackQuery($callbackId, "Área inválida.", true);
            return;
        }

        if ($character['level'] < $area['min_level']) {
            $this->bot->answerCallbackQuery($callbackId, "Você precisa ser nível {$area['min_level']} para entrar nesta área!", true);
            return;
        }

        if ($character['current_area_id'] == $area['id']) {
            $this->bot->answerCallbackQuery($callbackId, "Você já está nesta área.");
            return;
        }

        $this->areaRepo->updateCharacterArea($character['id'], $areaId);
        $this->bot->answerCallbackQuery($callbackId, "Você viajou para {$area['name']}!");
        $this->showMap($chatId, $userId, $messageId);
    }
}
