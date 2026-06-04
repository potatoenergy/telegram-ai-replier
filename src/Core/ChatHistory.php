<?php
namespace App\Core;

class ChatHistory
{
  private static array $history = [];
  private int $maxMessages;

  public function __construct(int $maxMessages = 10)
  {
    $this->maxMessages = $maxMessages;
  }

  public function addMessage(int $chatId, string $role, string $content): void
  {
    if (!isset(self::$history[$chatId])) {
      self::$history[$chatId] = [];
    }

    self::$history[$chatId][] = [
      "role" => $role,
      "content" => $content,
    ];

    if (count(self::$history[$chatId]) > $this->maxMessages) {
      self::$history[$chatId] = array_slice(
        self::$history[$chatId],
        -$this->maxMessages,
      );
    }
  }

  public function getMessages(int $chatId): array
  {
    return self::$history[$chatId] ?? [];
  }

  public function clear(int $chatId): void
  {
    unset(self::$history[$chatId]);
  }
}
