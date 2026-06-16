<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Aurora\Core\Config;

if (file_exists(__DIR__ . '/../.env')) {
    Config::load(__DIR__ . '/../.env');
}

$token = Config::get('TELEGRAM_BOT_TOKEN');
$webhookUrl = Config::get('WEBHOOK_URL');

echo "<pre>";
echo "====================================\n";
echo " Configuração de Webhook do Aurora\n";
echo "====================================\n\n";

if (empty($token) || $token === 'SEU_TOKEN_AQUI') {
    die("Erro: TELEGRAM_BOT_TOKEN não está configurado no arquivo .env.\n");
}

if (empty($webhookUrl) || strpos($webhookUrl, 'seu-dominio') !== false) {
    die("Erro: A URL de Webhook configurada no .env é inválida.\n");
}

$apiUrl = "https://api.telegram.org/bot{$token}/setWebhook?url=" . urlencode($webhookUrl);

echo "Enviando requisição para o Telegram definindo URL: {$webhookUrl}\n\n";
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
if ($response === false) {
    $response = "Erro cURL: " . curl_error($ch);
}

echo "Resposta do Telegram:\n";
echo $response . "\n";

echo "\nSe o status for 'ok: true', seu Webhook está configurado com sucesso!\n";
echo "O Telegram agora enviará as mensagens diretamente para o seu site.\n";
echo "</pre>";
