<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\Lock;

final readonly class LockFactory
{
    public function __construct(private LockInterface $lock) {}

    public function create(string $key): LockInterface
    {
        $lock = $this->lock->createLock();
        $lock->setKey($key);

        return $lock;
    }
}
