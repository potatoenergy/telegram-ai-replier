<?php
namespace App\Core;

use App\AI\AIInterface;
use App\Config\Config;
use App\Commands\CommandInterface;
use App\Commands\StartCommand;
use App\Commands\StatusCommand;
use App\Commands\ClearCommand;

class UpdateHandler
{
  private Bot $bot;
  private AIInterface $aiProvider;
  private RateLimiter $rateLimiter;
  private ChatHistory $chatHistory;
  private SafetyFilter $safetyFilter;
  private bool $isDebug;

  /** @var array<string, CommandInterface> */
  private array $commands = [];

  public function __construct(Bot $bot, AIInterface $aiProvider)
  {
    $this->bot = $bot;
    $this->aiProvider = $aiProvider;
    $this->rateLimiter = new RateLimiter();
    $this->chatHistory = new ChatHistory(
      (int) ($_ENV["CHAT_HISTORY_SIZE"] ?? 10),
    );
    $this->safetyFilter = new SafetyFilter();
    $this->isDebug = ($_ENV["DEBUG"] ?? "false") === "true";

    $this->registerCommands();
  }

  private function registerCommands(): void
  {
    $cmds = [
      new StartCommand(),
      new StatusCommand($this->rateLimiter),
      new ClearCommand($this->chatHistory),
    ];

    foreach ($cmds as $cmd) {
      $this->commands[$cmd->getName()] = $cmd;
    }
  }

  public function handle(array $update): void
  {
    if ($this->isDebug) {
      error_log("Received update: " . json_encode(array_keys($update)));
    }

    if (isset($update["business_connection"])) {
      $this->handleBusinessConnection($update["business_connection"]);
    } elseif (isset($update["business_message"])) {
      $this->handleBusinessMessage($update["business_message"]);
    } elseif (
      isset($update["business_message_deleted"]) ||
      isset($update["deleted_business_messages"])
    ) {
      $deleted =
        $update["business_message_deleted"] ??
        $update["deleted_business_messages"];
      $this->handleBusinessMessagesDeleted($deleted);
    } elseif (isset($update["message"])) {
      $this->handleMessage($update["message"]);
    }
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
    $bSenderUsername = $bMessage["from"]["username"] ?? null;
    $bSenderFirstName = $bMessage["from"]["first_name"] ?? null;

    if ($this->isDebug) {
      error_log(
        sprintf(
          "[Business Message] From: %s (%s), Chat: %s, ConnID: %s, Text: %s",
          $bSenderId ?? "null",
          $bSenderUsername ?? ($bSenderFirstName ?? "null"),
          $bChatId,
          $bId,
          substr($bText ?? "", 0, 50),
        ),
      );
    }

    if (
      $bSenderId !== null &&
      $this->safetyFilter->shouldIgnore(
        (int) $bSenderId,
        $bSenderUsername,
        $bSenderFirstName,
      )
    ) {
      if ($this->isDebug) {
        error_log(
          "[Business Message] Ignored by SafetyFilter (ID: $bSenderId).",
        );
      }
      return;
    }

    if (
      $bText === null ||
      $bId === null ||
      $bChatId === null ||
      $bMessageId === null
    ) {
      error_log(
        sprintf(
          "[Business Message] Skipped: missing required fields. Text=%s, ConnID=%s, ChatID=%s, MsgID=%s",
          $bText !== null ? "yes" : "no",
          $bId,
          $bChatId,
          $bMessageId,
        ),
      );
      return;
    }

    $isOwner = $bSenderId !== null && Config::isBusinessOwner((int) $bSenderId);
    $isAdmin = $bSenderId !== null && Config::isAdmin((int) $bSenderId);

    $trimmed = trim($bText);
    if (str_starts_with($trimmed, "/") && isset($this->commands[$trimmed])) {
      if ($isOwner) {
        $this->commands[$trimmed]->execute($this->bot, $bChatId, $bId);
      }
      return;
    }

    if ($isOwner || $isAdmin) {
      if ($this->isDebug) {
        error_log(
          "[Business Message] Ignored: message from Owner/Admin (ID: $bSenderId). AI is for users only.",
        );
      }
      return;
    }

    if ($this->rateLimiter->isRateLimited($bChatId)) {
      $this->bot->sendMessage([
        "business_connection_id" => $bId,
        "chat_id" => $bChatId,
        "text" => "⚠️ Too many requests. Please wait.",
      ]);
      return;
    }

    $history = $this->chatHistory->getMessages($bChatId);
    $aiResponse = $this->aiProvider->generateResponse($bText, $history);

    if ($aiResponse) {
      $aiResponse = $this->safetyFilter->truncateResponse($aiResponse);

      $this->chatHistory->addMessage($bChatId, "user", $bText);
      $this->chatHistory->addMessage($bChatId, "assistant", $aiResponse);
    }

    $delay = $this->safetyFilter->getHumanizedDelay();
    if ($delay > 0) {
      if ($this->isDebug) {
        error_log(
          "[Business Message] Waiting {$delay}ms before sending reply...",
        );
      }
      usleep($delay * 1000);
    }

    $result = $this->bot->sendMessage([
      "business_connection_id" => $bId,
      "chat_id" => $bChatId,
      "text" => $aiResponse ?: "❌ Sorry, could not generate a response.",
      "parse_mode" => "html",
      "disable_web_page_preview" => true,
    ]);

    if ($result === null) {
      error_log(
        sprintf(
          "[Business Message] FAILED to send reply. ConnID: %s, ChatID: %s, MsgID: %s",
          $bId,
          $bChatId,
          $bMessageId,
        ),
      );
    }
  }

  private function handleMessage(array $message): void
  {
    $chatId = $message["chat"]["id"] ?? null;
    $senderId = $message["from"]["id"] ?? null;
    $text = trim($message["text"] ?? "");

    if ($this->isDebug) {
      error_log("[Message] From: $chatId, Text: $text");
    }

    if ($chatId === null) {
      return;
    }

    if ($senderId !== null) {
      $username = $message["from"]["username"] ?? null;
      $firstName = $message["from"]["first_name"] ?? null;
      if (
        $this->safetyFilter->shouldIgnore(
          (int) $senderId,
          $username,
          $firstName,
        )
      ) {
        if ($this->isDebug) {
          error_log("[Message] Ignored by SafetyFilter (ID: $senderId).");
        }
        return;
      }
    }

    $isAdmin = $senderId !== null && Config::isAdmin((int) $senderId);

    if (str_starts_with($text, "/") && isset($this->commands[$text])) {
      if ($isAdmin) {
        $this->commands[$text]->execute($this->bot, $chatId);
      } else {
        $this->sendPublicInfo($chatId);
      }
      return;
    }

    if ($isAdmin) {
      $help = "<b>Available Commands</b>\n\n";
      foreach ($this->commands as $name => $cmd) {
        $help .= "$name — " . $cmd->getDescription() . "\n";
      }
      $this->bot->sendMessage([
        "chat_id" => $chatId,
        "text" => $help,
        "parse_mode" => "HTML",
      ]);
    } else {
      $this->sendPublicInfo($chatId);
    }
  }

  private function sendPublicInfo(int $chatId): void
  {
    $aiProvider = $_ENV["AI_PROVIDER"] ?? "Unknown";
    $aiModel = $_ENV["OPENAI_MODEL"] ?? ($_ENV["OLLAMA_MODEL"] ?? "Unknown");
    $modeLabel =
      Config::getUpdateMode() === "polling" ? "Long Polling" : "Webhook";

    $info = "<b>Telegram AI Replier</b>\n\n";
    $info .= "This service automatically processes messages using AI.\n\n";
    $info .= "Configuration:\n";
    $info .= "├ Provider: <code>$aiProvider</code>\n";
    $info .= "├ Model: <code>$aiModel</code>\n";
    $info .= "└ Mode: <code>$modeLabel</code>\n\n";
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
