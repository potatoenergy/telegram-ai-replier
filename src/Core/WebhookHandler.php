<?php

namespace App\Core;

use App\AI\AIInterface;

class WebhookHandler
{
    private Bot $bot;
    private AIInterface $aiProvider;
    private array $rateLimitStorage;

    public function __construct(Bot $bot, AIInterface $aiProvider)
    {
        $this->bot = $bot;
        $this->aiProvider = $aiProvider;
        $this->rateLimitStorage = $this->loadRateLimitData();
    }

    public function process(): void
    {
        $input = file_get_contents('php://input');
        $update = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON Decode Error in Webhook: " . json_last_error_msg());
            http_response_code(400);
            return;
        }

        if (!$update) {
            error_log("Received empty or invalid update: " . $input);
            http_response_code(200);
            return;
        }

        if (isset($update['business_message'])) {
            $this->handleBusinessMessage($update['business_message']);
        } elseif (isset($update['message'])) {
            $this->handleMessage($update['message']);
        }

        $this->saveRateLimitData($this->rateLimitStorage);
        http_response_code(200);
    }

    private function handleMessage(array $message): void
    {
        $adminId = (int) ($_ENV['ADMIN_USER_ID'] ?? 0);
        if ($adminId === 0) {
            error_log("ADMIN_USER_ID is not set, cannot handle admin commands.");
            return;
        }

        $chatId = $message['chat']['id'] ?? null;
        $text = $message['text'] ?? '';

        if ($chatId == $adminId) {
            if ($text === '/start') {
                $adminInfo = "Telegram AI Replier Admin Panel\n";
                $adminInfo .= "AI Provider: " . ($_ENV['AI_PROVIDER'] ?? 'Not Set') . "\n";
                $adminInfo .= "Model: " . ($_ENV['OPENAI_MODEL'] ?? $_ENV['OLLAMA_MODEL'] ?? 'Not Set') . "\n";
                $adminInfo .= "Webhook URL: " . ($_ENV['TELEGRAM_WEBHOOK_URL'] ?? 'Not Set') . "\n";
                $adminInfo .= "This bot handles messages for the linked Telegram Business account.";

                $this->bot->sendMessage([
                    'chat_id' => $chatId,
                    'text' => $adminInfo,
                ]);
            }
            return;
        } else {
             $this->bot->sendMessage([
                'chat_id' => $chatId,
                'text' => "This bot is used for Telegram Business account replies. For more information, visit: https://github.com/potatoenergy/telegram-ai-replier",
            ]);
        }
    }

    private function handleBusinessMessage(array $bMessage): void
    {
        $bText = $bMessage['text'] ?? null;
        $bId = $bMessage['business_connection_id'] ?? null;
        $bChatId = $bMessage['chat']['id'] ?? null;
        $bMessageId = $bMessage['message_id'] ?? null;
        $bSenderId = $bMessage['from']['id'] ?? null;
        $adminId = (int) ($_ENV['ADMIN_USER_ID'] ?? 0);

        if ($bSenderId !== null && $bSenderId == $adminId) {
            error_log("Received business_message from admin (ID: $bSenderId). Ignoring to prevent bot self-reply.");
            return;
        }

        if ($bText !== null && $bId !== null && $bChatId !== null && $bMessageId !== null) {
            $isRateLimited = $this->checkRateLimit($bChatId);
            if ($isRateLimited) {
                $this->bot->sendMessage([
                    'business_connection_id' => $bId,
                    'chat_id' => $bChatId,
                    'text' => "Too many requests. Please slow down.",
                    'reply_parameters' => json_encode(['message_id' => $bMessageId])
                ]);
                return;
            }

            $aiResponse = $this->aiProvider->generateResponse($bText);

            if ($aiResponse) {
                $this->bot->sendMessage([
                    'business_connection_id' => $bId,
                    'chat_id' => $bChatId,
                    'text' => $aiResponse,
                    'parse_mode' => 'html',
                    'disable_web_page_preview' => true,
                    'reply_parameters' => json_encode(['message_id' => $bMessageId])
                ]);
            } else {
                $this->bot->sendMessage([
                    'business_connection_id' => $bId,
                    'chat_id' => $bChatId,
                    'text' => "Извините, не удалось сформировать ответ.",
                    'reply_parameters' => json_encode(['message_id' => $bMessageId])
                ]);
            }
        } else {
            error_log("Received incomplete business_message: " . json_encode($bMessage));
        }
    }

    private function checkRateLimit(int $chatId): bool
    {
        $currentTime = time();
        $windowSize = (int) ($_ENV['RATE_LIMIT_WINDOW'] ?? 60);
        $maxRequests = (int) ($_ENV['RATE_LIMIT_MAX_REQUESTS'] ?? 5);

        $key = (string) $chatId;

        if (!isset($this->rateLimitStorage[$key])) {
            $this->rateLimitStorage[$key] = ['requests' => [], 'expires_at' => $currentTime + $windowSize];
        }

        $expiresAt = $this->rateLimitStorage[$key]['expires_at'];

        if ($currentTime >= $expiresAt) {
            $this->rateLimitStorage[$key]['requests'] = [];
            $this->rateLimitStorage[$key]['expires_at'] = $currentTime + $windowSize;
        }

        $requests = $this->rateLimitStorage[$key]['requests'];
        $validRequests = array_filter($requests, fn($time) => $time > $currentTime - $windowSize);

        if (count($validRequests) >= $maxRequests) {
            return true;
        }

        $validRequests[] = $currentTime;
        $this->rateLimitStorage[$key]['requests'] = $validRequests;

        return false;
    }

    private function loadRateLimitData(): array
    {
        $data = json_decode(file_get_contents('data/rate_limit.json'), true);
        if (!is_array($data)) {
            return [];
        }
        $currentTime = time();
        $cleanedData = [];
        foreach ($data as $key => $value) {
            if (isset($value['expires_at']) && $currentTime < $value['expires_at']) {
                $cleanedData[$key] = $value;
            }
        }
        return $cleanedData;
    }

    private function saveRateLimitData(array $data): void
    {
        file_put_contents('data/rate_limit.json', json_encode($data));
    }
}