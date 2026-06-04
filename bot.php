<?php
use App\Config\Config;
use App\Core\Bot;
use App\Core\UpdateHandler;
use App\Core\StatusPage;
use App\Core\RateLimiter;
use App\Transport\WebhookTransport;
use App\AI\OpenAIProvider;
use App\AI\OllamaProvider;
use App\Exceptions\ConfigException;

require_once __DIR__ . "/vendor/autoload.php";

try {
  Config::loadAndValidate();

  $updateMode = strtolower($_ENV["UPDATE_MODE"] ?? "webhook");
  $isDebug = ($_ENV["DEBUG"] ?? "false") === "true";

  if ($updateMode === "polling") {
    if (php_sapi_name() === "cli") {
      runPollingMode($isDebug);
    } else {
      runPollingStatusPage();
    }
  } else {
    runWebhookMode($isDebug);
  }
} catch (ConfigException $e) {
  error_log("Configuration Error: " . $e->getMessage());
  if (php_sapi_name() === "cli") {
    fwrite(STDERR, "Configuration Error: " . $e->getMessage() . "\n");
    exit(1);
  } else {
    http_response_code(500);
    echo "Configuration Error: " . $e->getMessage();
  }
} catch (Exception $e) {
  error_log("Error: " . $e->getMessage());
  if (php_sapi_name() === "cli") {
    fwrite(STDERR, "Fatal error: " . $e->getMessage() . "\n");
    exit(1);
  } else {
    http_response_code(500);
    echo "An error occurred.";
  }
}

function runPollingMode(bool $isDebug): void
{
  $bot = new Bot();

  echo "Deleting existing webhook...\n";
  $bot->deleteWebhook(true);

  $aiProviderName = Config::get("AI_PROVIDER");
  $aiProvider =
    $aiProviderName === "openai" ? new OpenAIProvider() : new OllamaProvider();
  $handler = new UpdateHandler($bot, $aiProvider);

  $timeout = (int) ($_ENV["POLLING_TIMEOUT"] ?? 30);
  $offset = 0;
  $running = true;

  if (function_exists("pcntl_signal")) {
    $signalHandler = function () use (&$running) {
      echo "\nReceived shutdown signal...\n";
      $running = false;
    };
    pcntl_signal(SIGTERM, $signalHandler);
    pcntl_signal(SIGINT, $signalHandler);
    pcntl_async_signals(true);
  }

  $allowedUpdates = json_encode([
    "message",
    "edited_message",
    "business_connection",
    "business_message",
    "edited_business_message",
    "deleted_business_messages",
    "guest_message",
  ]);

  echo "Long polling started (timeout: {$timeout}s)\n";

  while ($running) {
    $response = $bot->getUpdates([
      "offset" => $offset,
      "limit" => 100,
      "timeout" => $timeout,
      "allowed_updates" => $allowedUpdates,
    ]);

    if (!$response || !isset($response["result"])) {
      sleep(1);
      continue;
    }

    $updates = $response["result"];

    if (empty($updates)) {
      continue;
    }

    foreach ($updates as $update) {
      if (!$running) {
        break;
      }

      try {
        $handler->handle($update);
      } catch (Throwable $e) {
        error_log("Update handling error: " . $e->getMessage());
      }

      $offset = $update["update_id"] + 1;
    }
  }

  echo "Polling stopped gracefully.\n";
}

function runPollingStatusPage(): void
{
  $telegramBot = new Bot();
  $apiStatus = $telegramBot->checkApiAvailability();
  $rateLimitStatus = (new RateLimiter())->getStatus();

  (new StatusPage())->render(
    null,
    "polling",
    true,
    $apiStatus,
    $rateLimitStatus,
    0,
    null,
    null,
  );
}

function runWebhookMode(bool $isDebug): void
{
  if (php_sapi_name() === "cli") {
    echo "Webhook mode requires web server (nginx + php-fpm).\n";
    echo "Use UPDATE_MODE=polling for CLI mode.\n";
    exit(1);
  }

  $webhookUrl = Config::get("TELEGRAM_WEBHOOK_URL");
  if (!$webhookUrl) {
    throw new ConfigException(
      "TELEGRAM_WEBHOOK_URL must be set when UPDATE_MODE=webhook.",
    );
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
    $aiProviderName = Config::get("AI_PROVIDER");
    $aiProvider =
      $aiProviderName === "openai"
        ? new OpenAIProvider()
        : new OllamaProvider();
    $handler = new UpdateHandler($telegramBot, $aiProvider);
    $transport = new WebhookTransport();
    $transport->run($handler);
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
}
