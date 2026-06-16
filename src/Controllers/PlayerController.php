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
        $user = $this->userRepo->findById($userId);
        
        if (!$user) {
            $this->userRepo->create($userId, $username, $firstName);
        }

        $character = $this->charRepo->findByUserId($userId);
        if ($character) {
            $this->bot->sendMessage($chatId, "Você já possui um personagem: <b>{$character['name']}</b> (Lvl {$character['level']}).\nUse /perfil para ver seus status.");
            return;
        }

        // Enviar Prólogo
        $introText = "📜 <b>Bem-vindo a Aurora!</b>\n\n";
        $introText .= "<i>Você acorda em uma carroça sacolejante. O cheiro de pinheiros e fumaça enche o ar. O Rei Demônio despertou após mil anos de sono, e as hordas das trevas marcharam sobre a capital.</i>\n\n";
        $introText .= "<i>Você, um sobrevivente, chegou à <b>Vila de Alvorada</b>. É aqui que sua jornada para se tornar um herói lendário começa.</i>\n\n";
        $introText .= "<b>Escolha sua vocação para iniciar sua jornada:</b>";

        $classes = $this->charRepo->getClasses();
        $keyboard = ['inline_keyboard' => []];

        foreach ($classes as $class) {
            $keyboard['inline_keyboard'][] = [
                ['text' => "⚔️ {$class['name']}", 'callback_data' => "class_select:{$class['id']}"]
            ];
        }

        $photoUrl = "https://aurora-rpg-sepia.vercel.app/images/vila.png";
        $this->bot->sendPhoto($chatId, $photoUrl, $introText, $keyboard);
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
            $this->bot->sendMessage($chatId, "Erro: Use /registrar primeiro.");
            return;
        }

        $class = $this->charRepo->getClassById($classId);
        if (!$class) {
            $this->bot->sendMessage($chatId, "Classe inválida.");
            return;
        }

        // Criar o personagem
        $charId = $this->charRepo->createCharacter($userId, $user['first_name'], $classId);

        if (!$charId) {
            $this->bot->sendMessage($chatId, "Ocorreu um erro ao criar seu personagem.");
            return;
        }

        // Dar equipamentos iniciais dependendo da classe
        $this->giveStartingEquipment($charId, $class['name']);

        // Setar area inicial
        $db = \Aurora\Core\Database::getInstance();
        $db->prepare("UPDATE characters SET current_area_id = 1 WHERE id = ?")->execute([$charId]);

        // Determinar imagem da classe
        $className = strtolower($class['name']);
        if ($className === 'guerreiro') {
            $classPhoto = "https://aurora-rpg-sepia.vercel.app/images/guerreiro.png";
        } elseif ($className === 'mago') {
            $classPhoto = "https://aurora-rpg-sepia.vercel.app/images/mago.png";
        } else {
            $classPhoto = "https://aurora-rpg-sepia.vercel.app/images/arqueiro.png";
        }

        $successText = "🎉 <b>Personagem Criado com Sucesso!</b>\n\n";
        $successText .= "Você agora é um <b>{$class['name']}</b> e recebeu seus equipamentos básicos.\n\n";
        $successText .= "🔹 Digite /perfil para ver seus status.\n";
        $successText .= "🔹 Digite /inventario para gerenciar seus itens.\n";
        $successText .= "🔹 Digite /cidade para falar com o Ancião e pegar sua primeira missão!";

        $this->bot->sendPhoto($chatId, $classPhoto, $successText);
        
        // Remove a mensagem anterior (a foto da vila com botões) para não poluir
        $this->bot->editMessageText($chatId, $messageId, "<i>Jornada iniciada...</i>");
    }

    private function giveStartingEquipment(int $charId, string $className): void
    {
        $db = \Aurora\Core\Database::getInstance();
        $invRepo = new \Aurora\Repositories\InventoryRepository();
        
        // Encontrar os IDs dos itens no banco
        $stmt = $db->query("SELECT name, id FROM items WHERE name IN ('Espada Enferrujada', 'Armadura de Couro', 'Arco Curto de Madeira', 'Túnica Leve', 'Cajado de Aprendiz', 'Veste de Pano')");
        $items = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR); // Retorna ['Nome' => ID]

        $weaponId = null;
        $armorId = null;

        $className = strtolower($className);
        if ($className === 'guerreiro') {
            $weaponId = $items['Espada Enferrujada'] ?? null;
            $armorId = $items['Armadura de Couro'] ?? null;
        } elseif ($className === 'arqueiro') {
            $weaponId = $items['Arco Curto de Madeira'] ?? null;
            $armorId = $items['Túnica Leve'] ?? null;
        } else {
            $weaponId = $items['Cajado de Aprendiz'] ?? null;
            $armorId = $items['Veste de Pano'] ?? null;
        }

        if ($weaponId) {
            $invRepo->addItem($charId, $weaponId, 1);
            // Pega o ID do inventário que acabou de ser inserido
            $invId = $db->lastInsertId();
            $invRepo->toggleEquip($invId, $charId, 'weapon', true);
        }

        if ($armorId) {
            $invRepo->addItem($charId, $armorId, 1);
            $invId = $db->lastInsertId();
            $invRepo->toggleEquip($invId, $charId, 'armor', true);
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
