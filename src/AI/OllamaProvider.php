<?php
namespace App\AI;

use App\Exceptions\ConfigException;

class OllamaProvider implements AIInterface
{
  private string $url;
  private string $model;
  private int $maxTokens;
  private float $temperature;
  private string $systemPrompt;

  public function __construct(
    ?string $url = null,
    ?string $model = null,
    ?int $maxTokens = null,
    ?float $temperature = null,
    ?string $systemPrompt = null,
  ) {
    $this->url = rtrim(
      $url ?? ($_ENV["OLLAMA_URL"] ?? "http://host.docker.internal:11434"),
      "/",
    );
    if ($this->url === "") {
      throw new ConfigException("OLLAMA_URL must be set.");
    }

    $this->model = $model ?? ($_ENV["OLLAMA_MODEL"] ?? "llama3.2");
    $this->maxTokens = $maxTokens ?? (int) ($_ENV["AI_MAX_TOKENS"] ?? 500);
    $this->temperature =
      $temperature ?? (float) ($_ENV["AI_TEMPERATURE"] ?? 0.7);
    $this->systemPrompt =
      $systemPrompt ??
      ($_ENV["AI_SYSTEM_PROMPT"] ??
        "You are a helpful assistant for a Telegram Business account.");
  }

  public function generateResponse(string $prompt, array $history = []): ?string
  {
    $curl = curl_init();

    $messages = [["role" => "system", "content" => $this->systemPrompt]];
    foreach ($history as $msg) {
      $messages[] = ["role" => $msg["role"], "content" => $msg["content"]];
    }
    $messages[] = ["role" => "user", "content" => $prompt];

    $payload = json_encode([
      "model" => $this->model,
      "messages" => $messages,
      "stream" => false,
      "options" => [
        "temperature" => $this->temperature,
        "num_predict" => $this->maxTokens,
      ],
    ]);

    curl_setopt_array($curl, [
      CURLOPT_URL => $this->url . "/api/chat",
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => $payload,
      CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
      CURLOPT_TIMEOUT => 60,
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    unset($curl);

    if ($err) {
      error_log("cURL Error for Ollama: " . $err);
      return null;
    }

    if ($http_code !== 200) {
      error_log("Ollama API Error (HTTP $http_code): " . $response);
      return null;
    }

    $result = json_decode($response, true);
    if ($result && isset($result["message"]["content"])) {
      return trim($result["message"]["content"]);
    }

    error_log("Ollama API Unexpected Response: " . $response);
    return null;
  }
}
