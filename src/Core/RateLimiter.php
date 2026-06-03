<?php
namespace App\Core;

class RateLimiter
{
  private int $windowSize;
  private int $maxRequests;
  private static array $storage = [];

  public function __construct()
  {
    $this->windowSize = (int) ($_ENV["RATE_LIMIT_WINDOW"] ?? 60);
    $this->maxRequests = (int) ($_ENV["RATE_LIMIT_MAX_REQUESTS"] ?? 5);
  }

  public function isRateLimited(int $chatId): bool
  {
    $key = "rate_limit_" . $chatId;
    $currentTime = time();

    if (!isset(self::$storage[$key])) {
      self::$storage[$key] = [
        "requests" => [],
        "expires_at" => $currentTime + $this->windowSize,
      ];
    }

    $data = &self::$storage[$key];

    if ($currentTime >= $data["expires_at"]) {
      $data["requests"] = [];
      $data["expires_at"] = $currentTime + $this->windowSize;
    }

    $validRequests = array_filter(
      $data["requests"],
      fn($time) => $time > $currentTime - $this->windowSize,
    );

    if (count($validRequests) >= $this->maxRequests) {
      return true;
    }

    $validRequests[] = $currentTime;
    $data["requests"] = $validRequests;

    return false;
  }

  public function getStatus(): array
  {
    return [
      "available" => true,
      "type" => "in-memory (per-worker)",
      "active_keys" => count(self::$storage),
    ];
  }
}
