<?php

namespace Aurora\Core;

class TelegramBot
{
    private string $token;
    private string $apiUrl;

    public function __construct()
    {
        $this->token = Config::get('TELEGRAM_BOT_TOKEN', '');
        $this->apiUrl = "https://api.telegram.org/bot{$this->token}/";
    }

    public function sendMessage(int|string $chatId, string $text, array $replyMarkup = null): array|bool
    {
        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if ($replyMarkup) {
            $data['reply_markup'] = json_encode($replyMarkup);
        }

        return $this->request('sendMessage', $data);
    }
    
    public function answerCallbackQuery(string $callbackQueryId, string $text = '', bool $showAlert = false): array|bool
    {
        $data = [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => $showAlert,
        ];
        
        return $this->request('answerCallbackQuery', $data);
    }
    
    public function editMessageText(int|string $chatId, int $messageId, string $text, array $replyMarkup = null): array|bool
    {
        $data = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];

        if ($replyMarkup) {
            $data['reply_markup'] = json_encode($replyMarkup);
        }

        return $this->request('editMessageText', $data);
    }

    public function getUpdates(int $offset = 0, int $limit = 100, int $timeout = 30): array|bool
    {
        $data = [
            'offset' => $offset,
            'limit' => $limit,
            'timeout' => $timeout,
        ];
        
        return $this->request('getUpdates', $data);
    }

    private function request(string $method, array $data): array|bool
    {
        $url = $this->apiUrl . $method;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Considerar true em PRD se tiver certificado ok

        $response = curl_exec($ch);
        $error = curl_error($ch);

        if ($error) {
            error_log("Telegram API Error: $error");
            return false;
        }

        return json_decode($response, true) ?: false;
    }
}
