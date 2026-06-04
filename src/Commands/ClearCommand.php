<?php
namespace App\Commands;

use App\Core\Bot;
use App\Core\ChatHistory;

class ClearCommand implements CommandInterface
{
  private ChatHistory $chatHistory;

  public function __construct(ChatHistory $chatHistory)
  {
    $this->chatHistory = $chatHistory;
  }

  public function getName(): string
  {
    return "/clear";
  }

  public function getDescription(): string
  {
    return "Reset AI conversation history for current chat";
  }

  public function execute(
    Bot $bot,
    int $chatId,
    ?string $businessConnectionId = null,
  ): void {
    $this->chatHistory->clear($chatId);

    $params = [
      "chat_id" => $chatId,
      "text" => "🧹 Контекст диалога очищен.",
    ];

    if ($businessConnectionId !== null) {
      $params["business_connection_id"] = $businessConnectionId;
    }

    $bot->sendMessage($params);
  }
}
