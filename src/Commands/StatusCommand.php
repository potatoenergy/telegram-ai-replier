<?php
namespace App\Commands;

use App\Core\Bot;
use App\Core\RateLimiter;

class StatusCommand implements CommandInterface
{
  private RateLimiter $rateLimiter;

  public function __construct(RateLimiter $rateLimiter)
  {
    $this->rateLimiter = $rateLimiter;
  }

  public function getName(): string
  {
    return "/status";
  }

  public function getDescription(): string
  {
    return "System diagnostics and health check";
  }

  public function execute(
    Bot $bot,
    int $chatId,
    ?string $businessConnectionId = null,
  ): void {
    $apiStatus = $bot->checkApiAvailability();
    $rlStatus = $this->rateLimiter->getStatus();

    $apiOk = $apiStatus["available"] ?? false;
    $rlOk = $rlStatus["available"] ?? false;

    $statusIcon = $apiOk && $rlOk ? "✅" : "❌";
    $statusText = $apiOk && $rlOk ? "Operational" : "Issues Detected";

    $info = "<b>System Status: $statusIcon $statusText</b>\n\n";

    $info .= "<b>Telegram API</b>\n";
    $info .= "├ Available: <code>" . ($apiOk ? "Yes" : "No") . "</code>\n";
    $info .=
      "├ Response Time: <code>" . ($apiStatus["time"] ?? "N/A") . "s</code>\n";
    $info .= "├ Remote IP: <code>" . ($apiStatus["ip"] ?? "N/A") . "</code>\n";
    $info .=
      "└ Proxy: <code>" . ($apiStatus["proxy_used"] ?? "No") . "</code>\n\n";

    $info .= "<b>Rate Limiter</b>\n";
    $info .= "├ Type: <code>" . ($rlStatus["type"] ?? "N/A") . "</code>\n";
    $info .=
      "└ Active Keys: <code>" . ($rlStatus["active_keys"] ?? 0) . "</code>\n\n";

    $info .= "<b>Update Mode</b>\n";
    $info .=
      "└ <code>" .
      strtoupper(strtolower($_ENV["UPDATE_MODE"] ?? "webhook")) .
      "</code>\n";

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
