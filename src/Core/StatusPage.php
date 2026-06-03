<?php
namespace App\Core;

class StatusPage
{
  public function render(
    ?string $currentUrl,
    string $desiredUrl,
    bool $webhookSetResult = true,
    array $apiStatus = [],
    array $rateLimitStatus = [],
    int $pendingUpdates = 0,
    ?int $lastErrorDate = null,
    ?string $lastErrorMessage = null,
  ): void {
    $pageTitle = "Telegram AI Replier Status";

    $webhookOk = $currentUrl === $desiredUrl && $webhookSetResult;
    $apiOk = $apiStatus["available"] ?? false;
    $rateLimitOk = $rateLimitStatus["available"] ?? false;

    $issues = [];

    if (!$webhookOk) {
      $issues[] = "Webhook is not configured correctly";
    } elseif (!empty($lastErrorMessage)) {
      $issues[] =
        "Webhook delivery failed: " . htmlspecialchars($lastErrorMessage);
    } elseif ($pendingUpdates > 0) {
      $issues[] = "Pending updates: $pendingUpdates messages waiting";
    }

    if (!$apiOk) {
      $apiErr = $apiStatus["error"] ?? "Connection failed";
      $issues[] = "Telegram API unreachable: " . htmlspecialchars($apiErr);
    }

    if (!$rateLimitOk) {
      $issues[] = "Rate Limiter is inactive";
    }

    $systemOk = empty($issues);
    $statusText = $systemOk ? "Operational" : "Issues Detected";
    $statusClass = $systemOk ? "status-ok" : "status-error";

    $pageContent = $this->getPageHtml(
      $pageTitle,
      $statusText,
      $statusClass,
      $systemOk,
      $issues,
      $apiStatus,
      $rateLimitStatus,
    );
    echo $pageContent;
  }

  private function getPageHtml(
    string $title,
    string $statusText,
    string $statusClass,
    bool $systemOk,
    array $issues,
    array $apiStatus,
    array $rateLimitStatus,
  ): string {
    $issuesHtml = "";
    if (!$systemOk && !empty($issues)) {
      $issuesHtml = '<ul class="issues-list">';
      foreach ($issues as $issue) {
        $issuesHtml .= "<li>$issue</li>";
      }
      $issuesHtml .= "</ul>";
    }

    $aiProvider = $_ENV["AI_PROVIDER"] ?? "Unknown";
    $aiModel = $_ENV["OPENAI_MODEL"] ?? ($_ENV["OLLAMA_MODEL"] ?? "Unknown");

    $publicInfoHtml = <<<HTML
            <div class="info-section">
                <h2>System Information</h2>
                <div class="info-item">
                    <span class="info-label">AI Provider:</span>
                    <span class="info-value">$aiProvider</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Model:</span>
                    <span class="info-value">$aiModel</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Status:</span>
                    <span class="info-value">Active</span>
                </div>
            </div>
    HTML;

    $errorDetailsHtml = "";
    if (!$systemOk) {
      $apiTime = $apiStatus["time"] ?? "N/A";
      $apiIp = $apiStatus["ip"] ?? "N/A";
      $proxyUsed =
        ($apiStatus["proxy_used"] ?? "No") === "Yes" ? "Enabled" : "Disabled";
      $rlType = $rateLimitStatus["type"] ?? "N/A";

      $errorDetailsHtml = <<<HTML
                  <div class="info-section">
                      <h2>Diagnostic Information</h2>
                      <div class="info-item">
                          <span class="info-label">API Response Time:</span>
                          <span class="info-value">{$apiTime}s</span>
                      </div>
                      <div class="info-item">
                          <span class="info-label">API Remote IP:</span>
                          <span class="info-value">$apiIp</span>
                      </div>
                      <div class="info-item">
                          <span class="info-label">Proxy:</span>
                          <span class="info-value">$proxyUsed</span>
                      </div>
                      <div class="info-item">
                          <span class="info-label">Rate Limiter Type:</span>
                          <span class="info-value">$rlType</span>
                      </div>
                  </div>
      HTML;
    }

    $html = <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>$title</title>
    <style>
    :root {
        --tg-primary: #0088cc;
        --tg-bg: #f0f0f0;
        --tg-bg-dark: #ffffff;
        --tg-text-primary: #000000;
        --tg-text-secondary: #8e8e93;
        --tg-status-ok: #00c853;
        --tg-status-error: #ff1744;
        --tg-border: #e0e0e0;
        --tg-radius: 8px;
        --tg-spacing-sm: 8px;
        --tg-spacing-md: 16px;
        --tg-spacing-lg: 24px;
    }

    @media (prefers-color-scheme: dark) {
        :root {
            --tg-bg: #0b0f15;
            --tg-bg-dark: #1a1f26;
            --tg-text-primary: #ffffff;
            --tg-text-secondary: #aaaaaa;
            --tg-border: #3a3a3c;
        }
    }

    * { box-sizing: border-box; }

    body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        background-color: var(--tg-bg);
        margin: 0;
        padding: var(--tg-spacing-md);
        color: var(--tg-text-primary);
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
        line-height: 1.6;
    }

    .container {
        background-color: var(--tg-bg-dark);
        border-radius: var(--tg-radius);
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        padding: var(--tg-spacing-lg);
        max-width: 600px;
        width: 100%;
    }

    .header { margin-bottom: var(--tg-spacing-lg); text-align: center; }
    .header h1 { font-size: 24px; font-weight: 600; margin: 0 0 var(--tg-spacing-sm) 0; }
    .header p { font-size: 15px; color: var(--tg-text-secondary); margin: 0; }

    .status-box {
        background-color: var(--tg-bg);
        border-radius: var(--tg-radius);
        padding: var(--tg-spacing-md);
        margin: var(--tg-spacing-md) 0;
        font-weight: 500;
        font-size: 18px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    .status-main {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: var(--tg-spacing-sm);
        width: 100%;
    }

    .status-ok { color: var(--tg-status-ok); }
    .status-error { color: var(--tg-status-error); }

    .issues-list {
        margin: 12px 0 0 0;
        padding: 12px 0 0 0;
        list-style: none;
        text-align: left;
        font-size: 13px;
        font-family: 'SF Mono', Monaco, monospace;
        width: 100%;
        border-top: 1px solid var(--tg-border);
    }
    .issues-list li {
        margin-bottom: 6px;
        color: var(--tg-status-error);
        word-break: break-word;
        padding-left: 20px;
        position: relative;
    }
    .issues-list li::before {
        content: "•";
        position: absolute;
        left: 8px;
        color: var(--tg-status-error);
    }

    .info-section { margin: var(--tg-spacing-lg) 0; }
    .info-section h2 {
        font-size: 14px; font-weight: 600; color: var(--tg-text-secondary);
        margin: 0 0 var(--tg-spacing-md) 0; padding-bottom: var(--tg-spacing-sm);
        border-bottom: 1px solid var(--tg-border);
        text-transform: uppercase; letter-spacing: 0.5px;
    }

    .info-item {
        display: flex; justify-content: space-between; align-items: center;
        padding: var(--tg-spacing-sm) 0; border-bottom: 1px solid var(--tg-border); gap: var(--tg-spacing-md);
    }
    .info-item:last-child { border-bottom: none; }
    .info-label { font-weight: 500; font-size: 14px; flex-shrink: 0; }
    .info-value {
        font-family: 'SF Mono', Monaco, monospace; font-size: 13px;
        color: var(--tg-text-secondary); word-break: break-all; text-align: right; flex: 1; min-width: 0;
    }

    .details-section {
        margin-top: var(--tg-spacing-lg); padding-top: var(--tg-spacing-lg);
        border-top: 1px solid var(--tg-border);
    }
    .details-section h2 {
        font-size: 14px; font-weight: 600; color: var(--tg-text-secondary);
        margin: 0 0 var(--tg-spacing-md) 0; text-transform: uppercase; letter-spacing: 0.5px;
    }
    .details-section p { font-size: 14px; margin: 0 0 var(--tg-spacing-md) 0; line-height: 1.6; }

    .footer {
        margin-top: var(--tg-spacing-lg); padding-top: var(--tg-spacing-md);
        border-top: 1px solid var(--tg-border); color: var(--tg-text-secondary);
        font-size: 12px; text-align: center;
    }

    @media (max-width: 600px) {
        body { padding: var(--tg-spacing-sm); }
        .container { padding: var(--tg-spacing-md); }
        .header h1 { font-size: 20px; }
        .info-item { flex-direction: column; align-items: flex-start; gap: var(--tg-spacing-sm); }
        .info-value { text-align: left; width: 100%; }
    }
    </style>
    </head>
    <body>
    <div class="container">
        <div class="header">
            <h1>Telegram AI Replier</h1>
            <p>Status Dashboard</p>
        </div>

        <div class="status-box $statusClass">
            <div class="status-main">
                <span>$statusText</span>
            </div>
            $issuesHtml
        </div>

        $publicInfoHtml
        $errorDetailsHtml

        <div class="details-section">
            <h2>About</h2>
            <p>
                This service automatically processes messages from a Telegram Business account using artificial intelligence.
            </p>
            <p>
                <strong>Repository:</strong> <a href="https://github.com/potatoenergy/telegram-ai-replier" target="_blank" style="color: var(--tg-primary);">GitHub</a>
            </p>
        </div>

        <div class="footer">
            <p>Status Page | Telegram AI Replier</p>
        </div>
    </div>
    </body>
    </html>
    HTML;
    return $html;
  }
}
