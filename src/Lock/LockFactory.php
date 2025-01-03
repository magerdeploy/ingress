<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\Lock;

use PRSW\SwarmIngress\Store\StorageInterface;
use Psr\Container\ContainerInterface;

final readonly class LockFactory
{
    private StorageInterface $storage;

    public function __construct(private ContainerInterface $container)
    {
        $this->storage = $this->container->get(StorageInterface::class);
    }

    public function create(string $key): LockInterface
    {
        /** @var LockInterface $lock */
        $lock = $this->container->get($this->storage->getLockClass());
        $lock = clone $lock;

        $lock->setKey($key);

        return $lock;
    }
}
