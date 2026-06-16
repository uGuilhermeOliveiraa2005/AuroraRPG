<?php

require_once __DIR__ . '/vendor/autoload.php';

use Aurora\Core\Config;
use Aurora\Core\TelegramBot;
use Aurora\Core\Router;

Config::load(__DIR__ . '/.env');

$bot = new TelegramBot();
$router = new Router($bot);

echo "Iniciando Polling Local do Aurora MMORPG...\n";
echo "Pressione Ctrl+C para parar.\n\n";

// Remove qualquer webhook existente para que o getUpdates funcione
file_get_contents("https://api.telegram.org/bot" . Config::get('TELEGRAM_BOT_TOKEN') . "/deleteWebhook");

$offset = 0;

while (true) {
    $updates = $bot->getUpdates($offset, 100, 30); // 30 segundos de long-polling

    if (isset($updates['result']) && is_array($updates['result'])) {
        foreach ($updates['result'] as $update) {
            $offset = $update['update_id'] + 1; // Próximo update
            
            try {
                echo "[Nova Interação] Processando update_id: {$update['update_id']}\n";
                $router->handleUpdate($update);
            } catch (\Throwable $e) {
                echo "⚠️ Erro ao processar: " . $e->getMessage() . " em " . $e->getFile() . ":" . $e->getLine() . "\n";
            }
        }
    }
    
    // Pequeno sleep para evitar overload na CPU caso a conexão falhe rápido
    usleep(100000); 
}
