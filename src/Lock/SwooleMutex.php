<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\Lock;

use Swoole\Lock;

final class SwooleMutex implements LockInterface
{
    private ?Lock $lock;

    public function __construct()
    {
        $this->lock = null;
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

    public function createLock(): LockInterface
    {
        $new = clone $this;
        $new->lock = new Lock();

        return $new;
    }
}
