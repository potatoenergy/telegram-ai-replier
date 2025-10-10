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

        $currentUrlDisplay = $currentUrl !== null ? $currentUrl : 'Not Set';
        $currentUrlDisplayHtml = htmlspecialchars($currentUrlDisplay, ENT_QUOTES, 'UTF-8');

        $pageContent = $this->getPageHtml($pageTitle, $statusText, $statusClass, $currentUrlDisplayHtml, $desiredUrl, $webhookSetStatus);
        echo $pageContent;
    }

    private function getPageHtml(string $title, string $statusText, string $statusClass, string $currentUrlDisplayHtml, string $desiredUrl, string $webhookSetStatus): string
    {
        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>$title</title>
    <style>
        :root {
            --tg-primary: #0088cc; /* Основной цвет Telegram */
            --tg-primary-hover: #0077b3;
            --tg-bg: #f0f0f0; /* Светлый фон как в Telegram Web A */
            --tg-bg-dark: #ffffff; /* Белый фон карточек */
            --tg-text-primary: #000000; /* Основной текст */
            --tg-text-secondary: #8e8e93; /* Вторичный текст */
            --tg-text-accent: var(--tg-primary);
            --tg-status-ok: #00c853; /* Цвет для OK */
            --tg-status-error: #ff1744; /* Цвет для ошибки */
            --tg-border: #e0e0e0; /* Цвет границ */
            --tg-radius: 8px; /* Радиус скругления */
        }

        /* Темная тема */
        @media (prefers-color-scheme: dark) {
            :root {
                --tg-bg: #0b0f15; /* Темный фон */
                --tg-bg-dark: #1a1f26; /* Темный фон карточек */
                --tg-text-primary: #ffffff; /* Белый текст */
                --tg-text-secondary: #aaaaaa; /* Серый текст */
                --tg-border: #3a3a3c; /* Темная граница */
            }
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: var(--tg-bg);
            margin: 0;
            padding: 0;
            color: var(--tg-text-primary);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            line-height: 1.5;
        }

        .container {
            background-color: var(--tg-bg-dark);
            border-radius: var(--tg-radius);
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
            padding: 24px;
            max-width: 500px;
            width: 90%;
            text-align: center;
        }

        .header {
            margin-bottom: 24px;
        }

        .header h1 {
            font-size: 22px;
            font-weight: 600;
            margin: 0;
            color: var(--tg-text-primary);
        }

        .header p {
            font-size: 15px;
            color: var(--tg-text-secondary);
            margin: 8px 0 0 0;
        }

        .status-box {
            background-color: #f0f0f0; /* Светлый фон статуса как в чатах */
            border-radius: var(--tg-radius);
            padding: 16px;
            margin: 16px 0;
            font-weight: 500;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: var(--tg-text-primary);
        }

        @media (prefers-color-scheme: dark) {
            .status-box {
                background-color: #2a2f35; /* Темный фон статуса */
            }
        }

        .status-ok {
            color: var(--tg-status-ok);
        }

        .status-error {
            color: var(--tg-status-error);
        }

        .info-section {
            margin: 24px 0;
            text-align: left;
        }

        .info-section h2 {
            font-size: 16px;
            font-weight: 600;
            color: var(--tg-text-secondary);
            margin: 0 0 12px 0;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--tg-border);
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid var(--tg-border);
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 500;
            color: var(--tg-text-primary);
        }

        .info-value {
            font-family: monospace;
            font-size: 13px;
            color: var(--tg-text-secondary);
            word-break: break-all;
            text-align: right;
            flex: 1;
            margin-left: 12px;
        }

        .details-section {
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid var(--tg-border);
        }

        .details-section h2 {
            font-size: 16px;
            font-weight: 600;
            color: var(--tg-text-secondary);
            margin: 0 0 12px 0;
        }

        .details-section p {
            font-size: 14px;
            color: var(--tg-text-primary);
            margin: 0 0 16px 0;
        }

        .footer {
            margin-top: 24px;
            color: var(--tg-text-secondary);
            font-size: 13px;
        }

        .icon {
            font-size: 20px;
        }

        @media (max-width: 500px) {
            .container {
                padding: 16px;
            }
            .header h1 {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🤖 Telegram AI Replier</h1>
            <p>Status Dashboard</p>
        </div>

        <div class="status-box $statusClass">
            <span class="icon">$statusText</span>
            <span>Webhook Status: $statusText</span>
        </div>

        <p>This page confirms the configuration status of the Telegram Bot webhook.</p>

        <div class="info-section">
            <h2>Configuration</h2>
            <div class="info-item">
                <span class="info-label">Desired Webhook URL:</span>
                <span class="info-value">$desiredUrl</span>
            </div>
            <div class="info-item">
                <span class="info-label">Webhook Setup Result:</span>
                <span class="info-value">$webhookSetStatus</span>
            </div>
        </div>

        <div class="info-section">
            <h2>Telegram API Status</h2>
            <div class="info-item">
                <span class="info-label">Current Webhook URL:</span>
                <span class="info-value" id="current-url">$currentUrlDisplayHtml</span>
            </div>
            <div class="info-item">
                <span class="info-label">Last Check:</span>
                <span class="info-value" id="last-check">Just now</span>
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
            const statusIcon = document.querySelector('.icon');
            const setupResultElement = document.getElementById('setup-result');

            if (statusIcon.textContent === 'Not Set Correctly') {
                statusIcon.textContent = '❌';
            } else if (statusIcon.textContent === 'OK') {
                 statusIcon.textContent = '✅';
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