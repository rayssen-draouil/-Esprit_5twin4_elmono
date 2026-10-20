<?php

namespace App\Exceptions;

use Exception;

/**
 * Exception métier du module IA AquaSecure.
 * Porte un code HTTP et un message destiné à l'utilisateur final
 * afin de garantir un repli élégant (jamais d'exception brute côté UI).
 */
class AiException extends Exception
{
    protected ?int $statusCode;

    protected ?string $userFriendlyMessage;

    public function __construct(
        string $message,
        int $statusCode = 500,
        ?string $userFriendlyMessage = null,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);

        $this->statusCode = $statusCode;
        $this->userFriendlyMessage = $userFriendlyMessage
            ?? "Le service d'intelligence artificielle est temporairement indisponible. Veuillez réessayer ultérieurement.";
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
