<?php
declare(strict_types=1);

namespace ServiceYar\Auth;

final class AuthException extends \RuntimeException
{
    public function __construct(public readonly int $status, string $message, public readonly string $codeName, public readonly array $context = [])
    {
        parent::__construct($message);
    }
}