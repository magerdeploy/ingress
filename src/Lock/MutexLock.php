<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\Lock;

use Amp\Sync\KeyedMutex;
use Amp\Sync\LocalKeyedMutex;
use Amp\Sync\Lock;

final class MutexLock implements LockInterface
{
    private ?Lock $lock = null;

    public function __construct(private KeyedMutex $mutex, private string $key) {}

    public function lock(int $ttl = 5): bool
    {
        $this->lock = $this->mutex->acquire($this->key);

        return true;
    }

    public function release(): void
    {
        if (null === $this->lock) {
            throw new \RuntimeException('lock not acquired');
        }

        if (!$this->lock->isReleased()) {
            $this->lock->release();
        }
    }

    public static function createLock(string $key): self
    {
        return new self(new LocalKeyedMutex(), $key);
    }
}
