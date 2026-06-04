<?php
namespace App\Commands;

use App\Core\Bot;

class StartCommand implements CommandInterface
{
  public function getName(): string
  {
    return "/start";
  }

  public function getDescription(): string
  {
    return "Show admin configuration panel";
  }

  public function execute(
    Bot $bot,
    int $chatId,
    ?string $businessConnectionId = null,
  ): void {
    $updateMode = strtolower($_ENV["UPDATE_MODE"] ?? "webhook");
    $modeLabel = $updateMode === "polling" ? "Long Polling" : "Webhook";

    $info = "<b>Telegram AI Replier - Admin Panel</b>\n\n";

    $info .= "<b>Update Method</b>\n";
    $info .= "└ Mode: <code>$modeLabel</code>\n\n";

    $info .= "<b>Telegram Settings</b>\n";
    $info .=
      "├ BUSINESS_USER_ID: <code>" .
      ($_ENV["BUSINESS_USER_ID"] ?? "Not Set") .
      "</code>\n";
    $info .=
      "├ ADMIN_USER_IDS: <code>" .
      ($_ENV["ADMIN_USER_IDS"] ?? "Not Set") .
      "</code>\n";

    if ($updateMode === "webhook") {
      $info .=
        "├ WEBHOOK_URL: <code>" .
        ($_ENV["TELEGRAM_WEBHOOK_URL"] ?? "Not Set") .
        "</code>\n";
    }

    $info .=
      "└ API_BASE_URL: <code>" .
      ($_ENV["TELEGRAM_API_BASE_URL"] ?? "https://api.telegram.org") .
      "</code>\n\n";

    $info .= "<b>AI Configuration</b>\n";
    $info .=
      "├ Provider: <code>" . ($_ENV["AI_PROVIDER"] ?? "Unknown") . "</code>\n";

    if (($_ENV["AI_PROVIDER"] ?? "") === "openai") {
      $info .=
        "├ Model: <code>" .
        ($_ENV["OPENAI_MODEL"] ?? "gpt-3.5-turbo") .
        "</code>\n";
      $info .=
        "├ Base URL: <code>" .
        ($_ENV["OPENAI_BASE_URL"] ?? "Default") .
        "</code>\n";
    } else {
      $info .=
        "├ Ollama URL: <code>" .
        ($_ENV["OLLAMA_URL"] ?? "http://host.docker.internal:11434") .
        "</code>\n";
      $info .=
        "├ Model: <code>" . ($_ENV["OLLAMA_MODEL"] ?? "llama3.2") . "</code>\n";
    }

    $info .=
      "├ Max Tokens: <code>" . ($_ENV["AI_MAX_TOKENS"] ?? "500") . "</code>\n";
    $info .=
      "├ Temperature: <code>" .
      ($_ENV["AI_TEMPERATURE"] ?? "0.7") .
      "</code>\n";
    $prompt = $_ENV["AI_SYSTEM_PROMPT"] ?? "";
    $info .=
      "└ System Prompt: <code>" .
      htmlspecialchars(substr($prompt, 0, 50), ENT_QUOTES, "UTF-8") .
      (strlen($prompt) > 50 ? "..." : "") .
      "</code>\n\n";

    $info .= "<b>Network & Limits</b>\n";
    $info .=
      "├ Proxy: <code>" .
      (isset($_ENV["PROXY_URL"]) ? "Configured" : "Not Set") .
      "</code>\n";
    $info .=
      "├ Rate Limit: <code>" .
      ($_ENV["RATE_LIMIT_MAX_REQUESTS"] ?? "5") .
      " req / " .
      ($_ENV["RATE_LIMIT_WINDOW"] ?? "60") .
      "s</code>\n";
    $info .=
      "└ Debug: <code>" .
      (($_ENV["DEBUG"] ?? "false") === "true" ? "Enabled" : "Disabled") .
      "</code>\n\n";

    $info .= "<b>Safety & Timing</b>\n";
    $info .=
      "├ Response Delay: <code>" .
      ($_ENV["MIN_RESPONSE_DELAY"] ?? "3000") .
      "-" .
      ($_ENV["MAX_RESPONSE_DELAY"] ?? "8000") .
      "ms</code>\n";
    $info .=
      "├ Response Probability: <code>" .
      ($_ENV["CHAT_RESPONSE_PROBABILITY"] ?? "1.0") .
      "</code>\n";
    $info .=
      "├ Max Message Length: <code>" .
      ($_ENV["MAX_MESSAGE_LENGTH"] ?? "4096") .
      "</code>\n";
    $info .=
      "├ Ignore User IDs: <code>" .
      ($_ENV["IGNORE_USER_IDS"] ?? "None") .
      "</code>\n";
    $info .=
      "└ Ignore Patterns: <code>" .
      ($_ENV["IGNORE_USER_PATTERNS"] ?? "None") .
      "</code>\n\n";

    $info .= "<b>Available Commands</b>\n";
    $info .= "├ /start — Show this panel\n";
    $info .= "├ /status — System diagnostics\n";
    $info .= "└ /clear — Reset chat history (in business chat)\n\n";

    $info .= "✅ Bot is active and processing messages.";

    $params = [
      "chat_id" => $chatId,
      "text" => $info,
      "parse_mode" => "HTML",
    ];

    if ($businessConnectionId !== null) {
      $params["business_connection_id"] = $businessConnectionId;
    }

    $bot->sendMessage($params);
  }
}
