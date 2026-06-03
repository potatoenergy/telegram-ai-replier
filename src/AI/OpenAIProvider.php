<?php
namespace App\AI;

use OpenAI;
use App\Exceptions\ConfigException;
use App\Utils\ProxyHelper;

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
  private ?string $proxyUrl;

  public function __construct(
    ?string $apiKey = null,
    ?string $model = null,
    ?int $maxTokens = null,
    ?float $temperature = null,
    ?string $baseUrl = null,
    ?string $systemPrompt = null,
  ) {
    $this->apiKey = $apiKey ?? ($_ENV["OPENAI_API_KEY"] ?? null);
    if ($this->apiKey === null) {
      throw new ConfigException("OPENAI_API_KEY must be set.");
    }

    $this->baseUrl = $baseUrl ?? ($_ENV["OPENAI_BASE_URL"] ?? null);
    $this->proxyUrl = $_ENV["PROXY_URL"] ?? null;
    $this->useCurl = $this->baseUrl !== null || $this->proxyUrl !== null;

    if ($this->useCurl) {
      $this->baseUrl = $this->baseUrl
        ? rtrim($this->baseUrl, "/")
        : "https://api.openai.com/v1";
    } else {
      $this->client = OpenAI::client(
        $this->apiKey,
        "https://api.openai.com/v1",
      );
    }

    $this->model = $model ?? ($_ENV["OPENAI_MODEL"] ?? "gpt-3.5-turbo");
    $this->maxTokens = $maxTokens ?? (int) ($_ENV["AI_MAX_TOKENS"] ?? 500);
    $this->temperature =
      $temperature ?? (float) ($_ENV["AI_TEMPERATURE"] ?? 0.7);
    $this->systemPrompt =
      $systemPrompt ??
      ($_ENV["AI_SYSTEM_PROMPT"] ?? "You are a helpful assistant.");
  }

  public function generateResponse(string $prompt): ?string
  {
    return $this->useCurl
      ? $this->callCustomApi($prompt)
      : $this->callOfficialApi($prompt);
  }

  private function callOfficialApi(string $prompt): ?string
  {
    try {
      $result = $this->client->chat()->create([
        "model" => $this->model,
        "messages" => [
          ["role" => "system", "content" => $this->systemPrompt],
          ["role" => "user", "content" => $prompt],
        ],
        "max_tokens" => $this->maxTokens,
        "temperature" => $this->temperature,
      ]);
      return trim($result["choices"][0]["message"]["content"] ?? "");
    } catch (\Exception $e) {
      error_log("OpenAI Official API Error: " . $e->getMessage());
      return null;
    }
  }

  private function callCustomApi(string $prompt): ?string
  {
    $ch = curl_init();
    $payload = json_encode([
      "model" => $this->model,
      "messages" => [
        ["role" => "system", "content" => $this->systemPrompt],
        ["role" => "user", "content" => $prompt],
      ],
      "max_tokens" => $this->maxTokens,
      "temperature" => $this->temperature,
    ]);

    curl_setopt_array($ch, [
      CURLOPT_URL => $this->baseUrl . "/chat/completions",
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => $payload,
      CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "Authorization: Bearer " . $this->apiKey,
      ],
      CURLOPT_TIMEOUT => 60,
    ]);
    ProxyHelper::apply($ch, $this->proxyUrl);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    unset($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
      $result = json_decode($response, true);
      return trim($result["choices"][0]["message"]["content"] ?? "");
    }

    error_log("OpenAI Custom API Error (HTTP $httpCode): " . $response);
    return null;
  }
}
