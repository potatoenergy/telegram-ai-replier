<?php

use App\Config\Config;
use App\Core\Bot;
use App\Core\WebhookHandler;
use App\Core\StatusPage;
use App\AI\OpenAIProvider;
use App\AI\OllamaProvider;
use App\Exceptions\ConfigException;

require_once 'vendor/autoload.php';

try {
    Config::loadAndValidate();

    $webhookUrl = Config::get('TELEGRAM_WEBHOOK_URL');
    if (!$webhookUrl) {
        throw new ConfigException('TELEGRAM_WEBHOOK_URL must be set in environment variables.');
    }

    $botToken = Config::get('BOT_TOKEN');
    if (!$botToken) {
        throw new ConfigException('BOT_TOKEN must be set in environment variables.');
    }

    $telegramBot = new Bot();

    $setWebhookUrl = "https://api.telegram.org/bot{$botToken}/setWebhook";
    $checkWebhookUrl = "https://api.telegram.org/bot{$botToken}/getWebhookInfo";

    $ch_check = curl_init();
    curl_setopt_array($ch_check, [
        CURLOPT_URL => $checkWebhookUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $webhook_info_response = curl_exec($ch_check);
    $http_code_check = curl_getinfo($ch_check, CURLINFO_HTTP_CODE);
    curl_close($ch_check);

    if ($http_code_check !== 200 || !$webhook_info_response) {
        throw new Exception("Failed to get webhook info from Telegram API. HTTP Code: $http_code_check");
    }

    $webhook_info = json_decode($webhook_info_response, true);
    if (!$webhook_info || !isset($webhook_info['ok']) || !$webhook_info['ok']) {
        throw new Exception("Telegram API returned an error on getWebhookInfo: " . ($webhook_info['description'] ?? 'Unknown error'));
    }

    $currentWebhookUrl = $webhook_info['result']['url'] ?? null;

    $webhookSetSuccessfully = false;

    if ($currentWebhookUrl !== $webhookUrl) {
        $ch_set = curl_init();
        curl_setopt_array($ch_set, [
            CURLOPT_URL => $setWebhookUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['url' => $webhookUrl]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        $set_response = curl_exec($ch_set);
        $http_code_set = curl_getinfo($ch_set, CURLINFO_HTTP_CODE);
        curl_close($ch_set);

        if ($http_code_set !== 200 || !$set_response) {
            throw new Exception("Failed to set webhook to Telegram API. HTTP Code: $http_code_set");
        }

        $set_result = json_decode($set_response, true);
        if (!$set_result || !isset($set_result['ok']) || !$set_result['ok']) {
            throw new Exception("Telegram API returned an error on setWebhook: " . ($set_result['description'] ?? 'Unknown error'));
        }

        error_log("Webhook successfully set to: $webhookUrl");
        $webhookSetSuccessfully = true;
    } else {
        error_log("Webhook is already correctly set to: $webhookUrl");
        $webhookSetSuccessfully = true;
    }

    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';

    if ($requestMethod === 'POST' && $requestUri === '/') {
        $aiProviderName = Config::get('AI_PROVIDER');

        $aiProvider = null;
        if ($aiProviderName === 'openai') {
            $aiProvider = new OpenAIProvider();
        } elseif ($aiProviderName === 'ollama') {
            $aiProvider = new OllamaProvider();
        } else {
            throw new ConfigException("Unsupported AI_PROVIDER: '$aiProviderName'");
        }

        $webhookHandler = new WebhookHandler($telegramBot, $aiProvider);
        $webhookHandler->process();
    } else if ($requestMethod === 'GET' && $requestUri === '/') {
        $statusPage = new StatusPage();
        $statusPage->render($currentWebhookUrl, $webhookUrl, $webhookSetSuccessfully);
    } else {
        http_response_code(404);
        echo "Not Found";
    }

} catch (ConfigException $e) {
    error_log("Configuration Error: " . $e->getMessage());
    http_response_code(500);
    echo "Configuration Error: " . $e->getMessage();
} catch (Exception $e) {
    error_log("General Error in bot.php: " . $e->getMessage());
    http_response_code(500);
    echo "An error occurred.";
}