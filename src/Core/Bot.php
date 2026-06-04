<?php
namespace App\Core;

use App\Exceptions\ConfigException;
use App\Utils\ProxyHelper;

class Bot
{
  private string $token;
  private string $apiUrl;
  private ?string $proxyUrl;

  public function __construct(?string $token = null)
  {
    $this->token = $token ?? ($_ENV["BOT_TOKEN"] ?? null);
    if ($this->token === null) {
      throw new ConfigException("BOT_TOKEN must be set.");
    }

    $baseUrl = $_ENV["TELEGRAM_API_BASE_URL"] ?? "https://api.telegram.org";
    $this->apiUrl = rtrim($baseUrl, "/") . "/bot" . $this->token . "/";
    $this->proxyUrl = $_ENV["PROXY_URL"] ?? null;
  }

  private function request(string $method, array $params = []): ?array
  {
    $ch = curl_init();
    curl_setopt_array($ch, [
      CURLOPT_URL => $this->apiUrl . $method,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => $params,
      CURLOPT_TIMEOUT => 60,
    ]);
    ProxyHelper::apply($ch, $this->proxyUrl);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    unset($ch);

    if ($error || $httpCode !== 200) {
      error_log(
        "Bot API Error ($method): HTTP $httpCode - " . ($error ?: $response),
      );
      return null;
    }

    $decoded = json_decode($response, true);
    return $decoded && $decoded["ok"] ? $decoded : null;
  }

  public function sendMessage(array $params): ?array
  {
    return $this->request("sendMessage", $params);
  }

  public function getWebhookInfo(): ?array
  {
    return $this->request("getWebhookInfo", []);
  }

  public function setWebhook(string $url): bool
  {
    return $this->request("setWebhook", ["url" => $url]) !== null;
  }

  public function deleteWebhook(bool $dropPendingUpdates = false): bool
  {
    return $this->request("deleteWebhook", [
      "drop_pending_updates" => $dropPendingUpdates,
    ]) !== null;
  }

  public function getUpdates(array $params = []): ?array
  {
    return $this->request("getUpdates", $params);
  }

  public function checkApiAvailability(): array
  {
    $ch = curl_init();
    curl_setopt_array($ch, [
      CURLOPT_URL => $this->apiUrl . "getMe",
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 10,
    ]);
    ProxyHelper::apply($ch, $this->proxyUrl);

    $response = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ip = curl_getinfo($ch, CURLINFO_PRIMARY_IP);
    $time = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    unset($ch);

    return [
      "available" => $httpCode === 200 && $response,
      "http_code" => $httpCode,
      "error" => $err,
      "ip" => $ip ?: "N/A",
      "time" => round($time, 3),
      "proxy_used" => $this->proxyUrl
        ? "Yes (" . parse_url($this->proxyUrl, PHP_URL_HOST) . ")"
        : "No",
    ];
  }
}
