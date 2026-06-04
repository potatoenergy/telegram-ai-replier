<?php
namespace App\Config;

use App\Exceptions\ConfigException;

class Config
{
  public static function loadAndValidate(): void
  {
    $updateMode = strtolower($_ENV["UPDATE_MODE"] ?? "webhook");

    if (!in_array($updateMode, ["webhook", "polling"], true)) {
      throw new ConfigException(
        "Invalid UPDATE_MODE: '$updateMode'. Must be 'webhook' or 'polling'.",
      );
    }

    $requiredVars = ["AI_PROVIDER"];

    if ($updateMode === "webhook") {
      $requiredVars[] = "TELEGRAM_WEBHOOK_URL";
    }

    $hasAdmins =
      isset($_ENV["ADMIN_USER_IDS"]) && $_ENV["ADMIN_USER_IDS"] !== "";
    $hasOwner =
      isset($_ENV["BUSINESS_USER_ID"]) && $_ENV["BUSINESS_USER_ID"] !== "";

    if (!$hasAdmins && !$hasOwner) {
      throw new ConfigException(
        "At least one of ADMIN_USER_IDS or BUSINESS_USER_ID must be set.",
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

    $ownerId = self::getBusinessOwnerId();
    if ($ownerId !== null && !in_array($ownerId, $ids, true)) {
      $ids[] = $ownerId;
    }

    return $ids;
  }

  public static function isAdmin(int $userId): bool
  {
    return in_array($userId, self::getAdminIds(), true);
  }

  public static function getBusinessOwnerId(): ?int
  {
    $id = $_ENV["BUSINESS_USER_ID"] ?? null;
    return $id !== null && $id !== "" && ctype_digit((string) $id)
      ? (int) $id
      : null;
  }

  public static function isBusinessOwner(int $userId): bool
  {
    $ownerId = self::getBusinessOwnerId();
    return $ownerId !== null && $userId === $ownerId;
  }

  public static function getUpdateMode(): string
  {
    return strtolower($_ENV["UPDATE_MODE"] ?? "webhook");
  }
}
