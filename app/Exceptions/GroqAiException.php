<?php

namespace App\Exceptions;

use Exception;

class GroqAiException extends Exception
{
    protected ?int $statusCode;
    protected ?string $userFriendlyMessage;

    public function __construct(string $message, int $statusCode = 500, ?string $userFriendlyMessage = null, ?Exception $previous = null)
    {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
        $this->userFriendlyMessage = $userFriendlyMessage ?? "Le service d'intelligence artificielle est temporairement indisponible. Veuillez réessayer ultérieurement.";
    }

    public function getUserFriendlyMessage(): string
    {
        return $this->userFriendlyMessage ?? $this->getMessage();
    }

    public function getStatusCode(): int
    {
        return $this->statusCode ?? 500;
    }
}
