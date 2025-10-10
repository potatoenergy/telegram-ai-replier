<?php

namespace App\Core;

class StatusPage
{
    public function render(?string $currentUrl, string $desiredUrl, bool $webhookSetResult = true): void
    {
        $pageTitle = "Telegram AI Replier Status";
        $isSet = $currentUrl === $desiredUrl;
        $statusText = $isSet ? "OK" : "Not Set Correctly";
        $statusClass = $isSet ? "status-ok" : "status-error";
        $webhookSetStatus = $webhookSetResult ? "Successfully Set" : "Error During Setup";

        $pageContent = $this->getPageHtml($pageTitle, $statusText, $statusClass, $currentUrl, $desiredUrl, $webhookSetStatus);
        echo $pageContent;
    }

    private function getPageHtml(string $title, string $statusText, string $statusClass, ?string $currentUrl, string $desiredUrl, string $webhookSetStatus): string
    {
        $currentUrlDisplay = $currentUrl !== null ? $currentUrl : 'Not Set';
        $currentUrlDisplayHtml = htmlspecialchars($currentUrlDisplay, ENT_QUOTES, 'UTF-8');

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>$title</title>
    <style>
        :root {
            --primary-color: #4CAF50;
            --error-color: #f44336;
            --warning-color: #ff9800;
            --info-color: #2196F3;
            --bg-color: #f5f7fa;
            --card-bg: #ffffff;
            --text-color: #333333;
            --border-color: #e0e0e0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4edf5 100%);
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: var(--text-color);
        }

        .container {
            background-color: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 40px;
            max-width: 800px;
            width: 100%;
            text-align: center;
        }

        h1 {
            color: var(--text-color);
            margin-bottom: 10px;
            font-size: 2.5em;
            font-weight: 600;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 1.1em;
        }

        .status-box {
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            font-weight: bold;
            font-size: 1.2em;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .status-ok {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 2px solid #4CAF50;
        }

        .status-error {
            background-color: #ffebee;
            color: #c62828;
            border: 2px solid #f44336;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }

        .info-card {
            background-color: #f9f9f9;
            border-radius: 8px;
            padding: 20px;
            text-align: left;
            box-shadow: 0 4px 8px rgba(0,0,0,0.05);
        }

        .info-card h3 {
            margin-top: 0;
            color: #555;
            font-size: 1.1em;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 10px;
        }

        .info-card dl {
            margin: 0;
        }

        .info-card dt {
            font-weight: bold;
            color: #777;
            margin-top: 10px;
        }

        .info-card dd {
            margin: 5px 0 10px 0;
            padding-left: 15px;
            font-family: monospace;
            background-color: #f0f0f0;
            padding: 5px;
            border-radius: 4px;
            word-break: break-all;
        }

        .details-section {
            margin-top: 30px;
            text-align: left;
        }

        .details-section h2 {
            color: #555;
            font-size: 1.3em;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 10px;
        }

        .details-section p {
            line-height: 1.6;
        }

        .footer {
            margin-top: 40px;
            color: #999;
            font-size: 0.9em;
        }

        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(0,0,0,.1);
            border-radius: 50%;
            border-top-color: var(--primary-color);
            animation: spin 1s ease-in-out infinite;
            vertical-align: middle;
            margin-left: 10px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @media (max-width: 600px) {
            .container {
                padding: 20px;
            }
            h1 {
                font-size: 2em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🤖 Telegram AI Replier</h1>
        <p class="subtitle">Status Dashboard</p>

        <div class="status-box $statusClass">
            <span>Webhook Status: $statusText</span>
            <span id="status-icon">$statusText</span>
        </div>

        <p>This page confirms the configuration status of the Telegram Bot webhook.</p>

        <div class="info-grid">
            <div class="info-card">
                <h3>Configuration</h3>
                <dl>
                    <dt>Desired Webhook URL:</dt>
                    <dd>$desiredUrl</dd>
                    <dt>Webhook Setup Result:</dt>
                    <dd id="setup-result">$webhookSetStatus</dd>
                </dl>
            </div>
            <div class="info-card">
                <h3>Telegram API Status</h3>
                <dl>
                    <dt>Current Webhook URL (from Telegram):</dt>
                    <dd id="current-url">$currentUrlDisplayHtml</dd>
                    <dt>Last Check:</dt>
                    <dd id="last-check">Just now</dd>
                </dl>
            </div>
        </div>

        <div class="details-section">
            <h2>How It Works</h2>
            <p>
                This bot automatically replies to messages sent to a Telegram Business account using AI.
                The webhook is the mechanism Telegram uses to send incoming messages to this bot's server.
                This page verifies that the webhook is correctly configured to point to this server's address.
            </p>
            <p>
                <strong>AI Provider:</strong> <span id="ai-provider">{$this->getEnvOrDefault('AI_PROVIDER', 'Unknown')}</span><br>
                <strong>Model:</strong> <span id="ai-model">{$this->getEnvOrDefault('OPENAI_MODEL', $this->getEnvOrDefault('OLLAMA_MODEL', 'Unknown'))}</span>
            </p>
        </div>

        <div class="footer">
            <p>Status Page | Telegram AI Replier</p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const lastCheckElement = document.getElementById('last-check');
            const statusIcon = document.getElementById('status-icon');
            const setupResultElement = document.getElementById('setup-result');

            if (statusIcon.textContent === 'Not Set Correctly') {
                statusIcon.innerHTML = '❌';
            } else if (statusIcon.textContent === 'OK') {
                 statusIcon.innerHTML = '✅';
            }

            if (setupResultElement.textContent === 'Error During Setup') {
                setupResultElement.style.color = '#c62828';
            } else {
                 setupResultElement.style.color = '#2e7d32';
            }

            function updateTimestamp() {
                const now = new Date();
                lastCheckElement.textContent = now.toLocaleString();
            }
            updateTimestamp();
            setInterval(updateTimestamp, 60000);
        });
    </script>
</body>
</html>
HTML;

        return $html;
    }

    private function getEnvOrDefault(string $key, string $default): string
    {
        return $_ENV[$key] ?? $default;
    }
}