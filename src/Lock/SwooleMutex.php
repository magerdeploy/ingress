<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\Lock;

use Swoole\Lock;

final readonly class SwooleMutex implements LockInterface
{
    private Lock $lock;

    public function __construct()
    {
        $this->lock = new Lock();
    }

    public function setKey(string $key): void {}

    public function lock(int $ttl = 5): bool
    {
        return $this->lock->lockwait($ttl);
    }

    public function release(): void
    {
        $this->lock->unlock();
    }
}
