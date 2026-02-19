<?php

namespace App\Core;

use App\Exceptions\ConfigException;

class Bot
{
    private string $token;
    private string $apiUrl;

    public function __construct(?string $token = null)
    {
        $this->token = $token ?? $_ENV['BOT_TOKEN'] ?? null;
        if ($this->token === null) {
            throw new ConfigException('BOT_TOKEN must be set in environment variables or passed to the constructor.');
        }
        $this->apiUrl = 'https://api.telegram.org/bot' . $this->token . '/';
    }

    private function request(string $method, array $params = []): ?array
    {
        $url = $this->apiUrl . $method;
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_HTTPHEADER => [
                "Content-Type: multipart/form-data"
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        unset($ch);

        if ($error) {
            error_log("cURL Error in Bot API request ($method): " . $error);
            return null;
        }

        if ($httpCode !== 200) {
            error_log("Telegram API Error ($method): HTTP $httpCode - $response");
            return null;
        }

        $decodedResponse = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON Decode Error in Bot API response ($method): " . json_last_error_msg());
            return null;
        }

        if (!$decodedResponse['ok']) {
            error_log("Telegram API Error ($method): " . ($decodedResponse['description'] ?? 'Unknown error'));
            return null;
        }

        return $decodedResponse;
    }

    public function sendMessage(array $params): ?array
    {
        return $this->request('sendMessage', $params);
    }
}