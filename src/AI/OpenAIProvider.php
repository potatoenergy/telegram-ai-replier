<?php

namespace App\AI;

use OpenAI;
use App\Exceptions\ConfigException;

class OpenAIProvider implements AIInterface
{
    private $client;
    private string $model;
    private int $maxTokens;
    private float $temperature;
    private ?string $baseUrl;
    private string $apiKey;
    private string $systemPrompt;
    private bool $useCurl;

    public function __construct(
        ?string $apiKey = null,
        ?string $model = null,
        ?int $maxTokens = null,
        ?float $temperature = null,
        ?string $baseUrl = null,
        ?string $systemPrompt = null
    ) {
        $this->apiKey = $apiKey ?? $_ENV['OPENAI_API_KEY'] ?? null;
        if ($this->apiKey === null) {
            throw new ConfigException('OPENAI_API_KEY must be set in environment variables or passed to the constructor.');
        }

        $this->baseUrl = $baseUrl ?? $_ENV['OPENAI_BASE_URL'] ?? null;

        $this->useCurl = ($this->baseUrl !== null);

        if ($this->useCurl) {
            $this->baseUrl = rtrim($this->baseUrl, '/');
            error_log("OpenAIProvider: Initialized with custom URL (using curl): " . $this->baseUrl);
        } else {
            $defaultBaseUrl = 'https://api.openai.com/v1';
            $this->client = OpenAI::client($this->apiKey, $defaultBaseUrl);
            error_log("OpenAIProvider: Initialized with official OpenAI API (using openai-php/client).");
        }

        $this->model = $model ?? $_ENV['OPENAI_MODEL'] ?? 'gpt-3.5-turbo';
        $this->maxTokens = $maxTokens ?? (int) ($_ENV['AI_MAX_TOKENS'] ?? 500);
        $this->temperature = $temperature ?? (float) ($_ENV['AI_TEMPERATURE'] ?? 0.7);
        $this->systemPrompt = $systemPrompt ?? $_ENV['AI_SYSTEM_PROMPT'] ?? 'You are a helpful assistant for a Telegram Business account.';
    }

    public function generateResponse(string $prompt): ?string
    {
        $response_text = null;

        if ($this->useCurl) {
            $response_text = $this->callCustomApi($prompt);
        } else {
            $response_text = $this->callOfficialApi($prompt);
        }

        return $response_text;
    }

    private function callOfficialApi(string $prompt): ?string
    {
        $response_text = null;
        try {
            error_log("OpenAIProvider: Calling Official API with model: " . $this->model);

            $result = $this->client->chat()->create([
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_tokens' => $this->maxTokens,
                'temperature' => $this->temperature,
            ]);

            if (isset($result['choices'][0]['message']['content'])) {
                $response_text = trim($result['choices'][0]['message']['content']);
                error_log("OpenAIProvider: Successfully received response from Official API.");
            }
        } catch (\Exception $e) {
            error_log("OpenAIProvider Official API Error: " . $e->getMessage());
        }
        return $response_text;
    }

    private function callCustomApi(string $prompt): ?string
    {
        $response_text = null;

        $curl = curl_init();

        $payload = json_encode([
            "model" => $this->model,
            "messages" => [
                ['role' => 'system', 'content' => $this->systemPrompt],
                ['role' => 'user', 'content' => $prompt],
            ],
            "max_tokens" => $this->maxTokens,
            "temperature" => $this->temperature,
        ]);

        $fullUrl = $this->baseUrl . '/chat/completions';

        error_log("OpenAIProvider: Calling Custom API at URL: " . $fullUrl . " with model: " . $this->model);

        curl_setopt_array($curl, [
            CURLOPT_URL => $fullUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        unset($curl);

        if ($err) {
            error_log("OpenAIProvider cURL Error for Custom API: " . $err);
        } elseif ($http_code < 200 || $http_code >= 300) {
            error_log("OpenAIProvider Custom API Error (HTTP $http_code): " . $response);
        } else {
            $result = json_decode($response, true);
            if ($result && isset($result['choices'][0]['message']['content'])) {
                $response_text = trim($result['choices'][0]['message']['content']);
                error_log("OpenAIProvider: Successfully received response from Custom API.");
            } else {
                error_log("OpenAIProvider Custom API Unexpected Response: " . $response);
                if ($result && isset($result['error']['message'])) {
                    error_log("OpenAIProvider Custom API Error Message: " . $result['error']['message']);
                }
            }
        }
        return $response_text;
    }
}