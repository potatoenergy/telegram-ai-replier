<?php
namespace App\Core;

use App\AI\AIInterface;
use App\Config\Config;

class WebhookHandler
{
  private Bot $bot;
  private AIInterface $aiProvider;
  private RateLimiter $rateLimiter;
  private bool $isDebug;

  public function __construct(Bot $bot, AIInterface $aiProvider)
  {
    $this->bot = $bot;
    $this->aiProvider = $aiProvider;
    $this->rateLimiter = new RateLimiter();
    $this->isDebug = ($_ENV["DEBUG"] ?? "false") === "true";
  }

  public function process(): void
  {
    $input = file_get_contents("php://input");
    $update = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
      error_log("JSON Decode Error: " . json_last_error_msg());
      http_response_code(400);
      return;
    }

    if (!$update) {
      if ($this->isDebug) {
        error_log("Empty update received");
      }
      http_response_code(200);
      return;
    }

    if ($this->isDebug) {
      error_log("Received update: " . json_encode(array_keys($update)));
    }

    if (isset($update["business_connection"])) {
      $this->handleBusinessConnection($update["business_connection"]);
    } elseif (isset($update["business_message"])) {
      $this->handleBusinessMessage($update["business_message"]);
    } elseif (isset($update["business_message_deleted"])) {
      $this->handleBusinessMessagesDeleted($update["business_message_deleted"]);
    } elseif (isset($update["message"])) {
      $this->handleMessage($update["message"]);
    }

    http_response_code(200);
  }

  private function handleBusinessConnection(array $connection): void
  {
    $connId = $connection["id"] ?? "unknown";
    $isEnabled = $connection["is_enabled"] ?? false;
    $canReply = $connection["rights"]["can_reply"] ?? false;

    if (!$isEnabled || !$canReply) {
      error_log(
        "[Business Connection] ID: $connId - DISABLED or no can_reply rights!",
      );
    } else {
      error_log("[Business Connection] ID: $connId - active.");
    }
  }

  private function handleBusinessMessagesDeleted(array $deleted): void
  {
    $connId = $deleted["business_connection_id"] ?? "unknown";
    $chatId = $deleted["chat"]["id"] ?? "unknown";
    error_log("[Business Messages Deleted] Connection: $connId, Chat: $chatId");
  }

  private function handleBusinessMessage(array $bMessage): void
  {
    $bText = $bMessage["text"] ?? ($bMessage["caption"] ?? null);
    $bId = $bMessage["business_connection_id"] ?? null;
    $bChatId = $bMessage["chat"]["id"] ?? null;
    $bMessageId = $bMessage["message_id"] ?? null;
    $bSenderId = $bMessage["from"]["id"] ?? null;

    if ($this->isDebug) {
      error_log(
        "[Business Message] From: $bSenderId, Text: " .
          substr($bText ?? "", 0, 50),
      );
    }

    if ($bSenderId !== null && Config::isAdmin((int) $bSenderId)) {
      if ($this->isDebug) {
        error_log(
          "[Business Message] Ignored: message from admin (ID: $bSenderId).",
        );
      }
      return;
    }

    if (
      $bText !== null &&
      $bId !== null &&
      $bChatId !== null &&
      $bMessageId !== null
    ) {
      if ($this->rateLimiter->isRateLimited($bChatId)) {
        $this->bot->sendMessage([
          "business_connection_id" => $bId,
          "chat_id" => $bChatId,
          "text" => "⚠️ Too many requests. Please wait.",
          "reply_parameters" => json_encode(["message_id" => $bMessageId]),
        ]);
        return;
      }

      $aiResponse = $this->aiProvider->generateResponse($bText);
      $this->bot->sendMessage([
        "business_connection_id" => $bId,
        "chat_id" => $bChatId,
        "text" => $aiResponse ?: "❌ Sorry, could not generate a response.",
        "parse_mode" => "html",
        "disable_web_page_preview" => true,
        "reply_parameters" => json_encode(["message_id" => $bMessageId]),
      ]);
    }
  }

  private function handleMessage(array $message): void
  {
    $chatId = $message["chat"]["id"] ?? null;
    $senderId = $message["from"]["id"] ?? null;
    $text = $message["text"] ?? "";

    if ($this->isDebug) {
      error_log("[Message] From: $chatId, Text: $text");
    }

    if (
      $senderId !== null &&
      Config::isAdmin((int) $senderId) &&
      $text === "/start"
    ) {
      $info = "<b>Telegram AI Replier - Admin Configuration</b>\n\n";

      $info .= "<b>Telegram Settings</b>\n";
      $info .=
        "├ ADMIN_USER_ID: <code>" .
        ($_ENV["ADMIN_USER_ID"] ?? "Not Set") .
        "</code>\n";
      $info .=
        "├ ADMIN_USER_IDS: <code>" .
        ($_ENV["ADMIN_USER_IDS"] ?? "Not Set") .
        "</code>\n";
      $info .=
        "├ TELEGRAM_WEBHOOK_URL: <code>" .
        ($_ENV["TELEGRAM_WEBHOOK_URL"] ?? "Not Set") .
        "</code>\n";
      $info .=
        "└ TELEGRAM_API_BASE_URL: <code>" .
        ($_ENV["TELEGRAM_API_BASE_URL"] ?? "https://api.telegram.org") .
        "</code>\n\n";

      $info .= "<b>AI Configuration</b>\n";
      $info .=
        "├ AI_PROVIDER: <code>" .
        ($_ENV["AI_PROVIDER"] ?? "Unknown") .
        "</code>\n";

      if (($_ENV["AI_PROVIDER"] ?? "") === "openai") {
        $info .=
          "├ OPENAI_MODEL: <code>" .
          ($_ENV["OPENAI_MODEL"] ?? "gpt-3.5-turbo") .
          "</code>\n";
        $info .=
          "├ OPENAI_BASE_URL: <code>" .
          ($_ENV["OPENAI_BASE_URL"] ?? "Not Set") .
          "</code>\n";
      } else {
        $info .=
          "├ OLLAMA_URL: <code>" .
          ($_ENV["OLLAMA_URL"] ?? "http://host.docker.internal:11434") .
          "</code>\n";
        $info .=
          "├ OLLAMA_MODEL: <code>" .
          ($_ENV["OLLAMA_MODEL"] ?? "llama3.2") .
          "</code>\n";
      }

      $info .=
        "├ AI_MAX_TOKENS: <code>" .
        ($_ENV["AI_MAX_TOKENS"] ?? "500") .
        "</code>\n";
      $info .=
        "├ AI_TEMPERATURE: <code>" .
        ($_ENV["AI_TEMPERATURE"] ?? "0.7") .
        "</code>\n";
      $info .=
        "└ AI_SYSTEM_PROMPT: <code>" .
        $this->truncate($_ENV["AI_SYSTEM_PROMPT"] ?? "", 50) .
        "</code>\n\n";

      $info .= "<b>Network & Limits</b>\n";
      $info .=
        "├ PROXY_URL: <code>" .
        (isset($_ENV["PROXY_URL"]) ? "Configured" : "Not Set") .
        "</code>\n";
      $info .=
        "├ RATE_LIMIT_WINDOW: <code>" .
        ($_ENV["RATE_LIMIT_WINDOW"] ?? "60") .
        "s</code>\n";
      $info .=
        "├ RATE_LIMIT_MAX_REQUESTS: <code>" .
        ($_ENV["RATE_LIMIT_MAX_REQUESTS"] ?? "5") .
        "</code>\n";
      $info .=
        "└ DEBUG: <code>" .
        (($_ENV["DEBUG"] ?? "false") === "true" ? "Enabled" : "Disabled") .
        "</code>\n\n";

      $info .= "Bot is active and processing messages.";

      $this->bot->sendMessage([
        "chat_id" => $chatId,
        "text" => $info,
        "parse_mode" => "HTML",
      ]);
    } elseif ($senderId === null || !Config::isAdmin((int) $senderId)) {
      $aiProvider = $_ENV["AI_PROVIDER"] ?? "Unknown";
      $aiModel = $_ENV["OPENAI_MODEL"] ?? ($_ENV["OLLAMA_MODEL"] ?? "Unknown");

      $info = "<b>Telegram AI Replier</b>\n\n";
      $info .= "This service automatically processes messages using AI.\n\n";
      $info .= "Configuration:\n";
      $info .= "├ Provider: <code>$aiProvider</code>\n";
      $info .= "└ Model: <code>$aiModel</code>\n\n";
      $info .=
        "<a href=\"https://github.com/potatoenergy/telegram-ai-replier\">GitHub Repository</a>";

      $this->bot->sendMessage([
        "chat_id" => $chatId,
        "text" => $info,
        "parse_mode" => "HTML",
        "disable_web_page_preview" => true,
      ]);
    }
  }

  private function truncate(string $text, int $length): string
  {
    if (strlen($text) <= $length) {
      return htmlspecialchars($text, ENT_QUOTES, "UTF-8");
    }
    return htmlspecialchars(substr($text, 0, $length), ENT_QUOTES, "UTF-8") .
      "...";
  }
}
