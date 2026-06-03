<?php
use App\Config\Config;
use App\Core\Bot;
use App\Core\WebhookHandler;
use App\Core\StatusPage;
use App\Core\RateLimiter;
use App\AI\OpenAIProvider;
use App\AI\OllamaProvider;
use App\Exceptions\ConfigException;

require_once "vendor/autoload.php";

try {
  Config::loadAndValidate();

  $isDebug = ($_ENV["DEBUG"] ?? "false") === "true";
  $webhookUrl = Config::get("TELEGRAM_WEBHOOK_URL");
  if (!$webhookUrl) {
    throw new ConfigException("TELEGRAM_WEBHOOK_URL must be set.");
  }

  $telegramBot = new Bot();

  $webhookInfo = $telegramBot->getWebhookInfo();
  if (!$webhookInfo) {
    throw new Exception(
      "Failed to get webhook info. Check PROXY_URL and network.",
    );
  }

  $currentWebhookUrl = $webhookInfo["result"]["url"] ?? null;
  $webhookSetSuccessfully = false;
  $pendingUpdates = $webhookInfo["result"]["pending_update_count"] ?? 0;
  $lastErrorDate = $webhookInfo["result"]["last_error_date"] ?? null;
  $lastErrorMessage = $webhookInfo["result"]["last_error_message"] ?? null;

  if ($currentWebhookUrl !== $webhookUrl) {
    $webhookSetSuccessfully = $telegramBot->setWebhook($webhookUrl);
    if ($webhookSetSuccessfully) {
      error_log("Webhook set to: $webhookUrl");
    } else {
      throw new Exception("Failed to set webhook.");
    }
  } else {
    $webhookSetSuccessfully = true;
    if ($isDebug) {
      error_log("Webhook already configured: $webhookUrl");
      if ($pendingUpdates > 0) {
        error_log("Pending updates: $pendingUpdates");
      }
      if ($lastErrorMessage) {
        error_log(
          "Last webhook error: $lastErrorMessage (date: $lastErrorDate)",
        );
      }
    }
  }

  $requestMethod = $_SERVER["REQUEST_METHOD"] ?? "GET";

  if ($isDebug) {
    error_log("Request: $requestMethod to " . ($_SERVER["REQUEST_URI"] ?? "/"));
  }

  if ($requestMethod === "POST") {
    if ($isDebug) {
      error_log("Processing webhook POST request");
    }

    $aiProviderName = Config::get("AI_PROVIDER");
    $aiProvider =
      $aiProviderName === "openai"
        ? new OpenAIProvider()
        : new OllamaProvider();
    (new WebhookHandler($telegramBot, $aiProvider))->process();
  } elseif ($requestMethod === "GET") {
    $apiStatus = $telegramBot->checkApiAvailability();
    $rateLimitStatus = (new RateLimiter())->getStatus();
    (new StatusPage())->render(
      $currentWebhookUrl,
      $webhookUrl,
      $webhookSetSuccessfully,
      $apiStatus,
      $rateLimitStatus,
      $pendingUpdates,
      $lastErrorDate,
      $lastErrorMessage,
    );
  } else {
    http_response_code(404);
    echo "Not Found";
  }
} catch (ConfigException $e) {
  error_log("Configuration Error: " . $e->getMessage());
  http_response_code(500);
  echo "Configuration Error: " . $e->getMessage();
} catch (Exception $e) {
  error_log("Error: " . $e->getMessage());
  http_response_code(500);
  echo "An error occurred.";
}
