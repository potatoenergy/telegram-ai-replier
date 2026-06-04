<?php
namespace App\Core;

class SafetyFilter
{
  private array $ignoredIds = [];
  private array $ignoredPatterns = [];
  private float $responseProbability;
  private int $minDelay;
  private int $maxDelay;
  private int $maxMessageLength;

  public function __construct()
  {
    $ids = $_ENV["IGNORE_USER_IDS"] ?? "";
    if ($ids !== "") {
      $this->ignoredIds = array_map(
        "intval",
        array_filter(array_map("trim", explode(",", $ids))),
      );
    }

    $patterns = $_ENV["IGNORE_USER_PATTERNS"] ?? "";
    if ($patterns !== "") {
      $this->ignoredPatterns = array_map(
        "strtolower",
        array_filter(array_map("trim", explode(",", $patterns))),
      );
    }

    $this->responseProbability =
      (float) ($_ENV["CHAT_RESPONSE_PROBABILITY"] ?? 1.0);
    $this->minDelay = (int) ($_ENV["MIN_RESPONSE_DELAY"] ?? 3000);
    $this->maxDelay = (int) ($_ENV["MAX_RESPONSE_DELAY"] ?? 8000);
    $this->maxMessageLength = (int) ($_ENV["MAX_MESSAGE_LENGTH"] ?? 4096);
  }

  public function shouldIgnore(
    int $userId,
    ?string $username,
    ?string $firstName,
  ): bool {
    if (in_array($userId, $this->ignoredIds, true)) {
      return true;
    }

    foreach ($this->ignoredPatterns as $pattern) {
      if ($username !== null && str_contains(strtolower($username), $pattern)) {
        return true;
      }
      if (
        $firstName !== null &&
        str_contains(strtolower($firstName), $pattern)
      ) {
        return true;
      }
    }

    return false;
  }

  public function shouldRespond(): bool
  {
    if ($this->responseProbability >= 1.0) {
      return true;
    }
    if ($this->responseProbability <= 0.0) {
      return false;
    }
    return mt_rand() / mt_getrandmax() <= $this->responseProbability;
  }

  public function getHumanizedDelay(): int
  {
    if ($this->minDelay >= $this->maxDelay) {
      return $this->minDelay;
    }
    return mt_rand($this->minDelay, $this->maxDelay);
  }

  public function truncateResponse(string $text): string
  {
    if (mb_strlen($text) <= $this->maxMessageLength) {
      return $text;
    }
    return mb_substr($text, 0, $this->maxMessageLength - 3) . "...";
  }

  public function getMaxMessageLength(): int
  {
    return $this->maxMessageLength;
  }
}
