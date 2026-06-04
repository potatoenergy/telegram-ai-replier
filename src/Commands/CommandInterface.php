<?php
namespace App\Commands;

use App\Core\Bot;

interface CommandInterface
{
  public function getName(): string;

  public function getDescription(): string;

  public function execute(
    Bot $bot,
    int $chatId,
    ?string $businessConnectionId = null,
  ): void;
}
