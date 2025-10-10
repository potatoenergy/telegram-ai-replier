<?php

namespace App\AI;

interface AIInterface
{
    public function generateResponse(string $prompt): ?string;
}