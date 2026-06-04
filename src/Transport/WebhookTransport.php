<?php
namespace App\Transport;

use App\Core\UpdateHandler;

class WebhookTransport implements TransportInterface
{
  public function run(UpdateHandler $handler): void
  {
    $input = file_get_contents("php://input");

    if (empty($input)) {
      http_response_code(200);
      return;
    }

    $update = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
      error_log("JSON Decode Error: " . json_last_error_msg());
      http_response_code(400);
      return;
    }

    if (!is_array($update)) {
      http_response_code(400);
      return;
    }

    try {
      $handler->handle($update);
    } catch (\Throwable $e) {
      error_log("Update handling error: " . $e->getMessage());
    }

    http_response_code(200);
  }

  public function getName(): string
  {
    return "webhook";
  }
}
