<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\Store\Exception;

final class LockTimeoutException extends \Exception
{
    public static function create(string $msg = 'failed to acquire lock'): self
    {
        return new self($msg);
    }
}
