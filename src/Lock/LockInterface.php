<?php

declare(strict_types=1);

namespace PRSW\Ingress\Lock;

interface LockInterface
{
    public function lock(int $ttl = 5): bool;

    public function release(): void;

    public static function createLock(string $key): self;
}
