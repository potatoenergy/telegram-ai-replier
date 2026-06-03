<?php
namespace App\Config;

use App\Exceptions\ConfigException;

class Config
{
  public static function loadAndValidate(): void
  {
    $requiredVars = ["TELEGRAM_WEBHOOK_URL", "AI_PROVIDER"];

    $hasAdminId =
      isset($_ENV["ADMIN_USER_ID"]) && $_ENV["ADMIN_USER_ID"] !== "";
    $hasAdminIds =
      isset($_ENV["ADMIN_USER_IDS"]) && $_ENV["ADMIN_USER_IDS"] !== "";

    if (!$hasAdminId && !$hasAdminIds) {
      throw new ConfigException(
        "At least one of ADMIN_USER_ID or ADMIN_USER_IDS must be set.",
      );
    }

    if (!isset($_ENV["BOT_TOKEN"]) || $_ENV["BOT_TOKEN"] === "") {
      throw new ConfigException("BOT_TOKEN must be set.");
    }

    $aiProvider = $_ENV["AI_PROVIDER"] ?? null;

    if ($aiProvider === "openai") {
      $requiredVars[] = "OPENAI_API_KEY";
    } elseif ($aiProvider !== "ollama") {
      throw new ConfigException(
        "Unsupported AI_PROVIDER: '$aiProvider'. Must be 'openai' or 'ollama'.",
      );
    }

    foreach ($requiredVars as $var) {
      if (!isset($_ENV[$var]) || $_ENV[$var] === "") {
        throw new ConfigException(
          "Required environment variable '$var' is not set.",
        );
      }
    }
  }

  public static function get(string $key, ?string $default = null): ?string
  {
    return $_ENV[$key] ?? $default;
  }

  public static function getAdminIds(): array
  {
    $ids = [];

    if (isset($_ENV["ADMIN_USER_IDS"]) && $_ENV["ADMIN_USER_IDS"] !== "") {
      $parts = array_map("trim", explode(",", $_ENV["ADMIN_USER_IDS"]));
      foreach ($parts as $part) {
        if ($part !== "" && ctype_digit($part)) {
          $ids[] = (int) $part;
        }
      }
    }

    if (isset($_ENV["ADMIN_USER_ID"]) && $_ENV["ADMIN_USER_ID"] !== "") {
      $singleId = (int) $_ENV["ADMIN_USER_ID"];
      if (!in_array($singleId, $ids, true)) {
        $ids[] = $singleId;
      }
    }

    return $ids;
  }

  public static function isAdmin(int $userId): bool
  {
    return in_array($userId, self::getAdminIds(), true);
  }
}
