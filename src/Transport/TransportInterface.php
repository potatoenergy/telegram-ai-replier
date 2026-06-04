<?php
namespace App\Transport;

use App\Core\UpdateHandler;

interface TransportInterface
{
  public function run(UpdateHandler $handler): void;

  public function getName(): string;
}
