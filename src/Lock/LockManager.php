<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\Lock;

use Psr\Container\ContainerInterface;

final readonly class LockManager
{
    public function __construct(private ContainerInterface $container) {}

    public function create(string $key): LockInterface
    {
        /** @var class-string<LockInterface> $lockClass */
        $lockClass = $this->container->get(LockInterface::class);

        return $lockClass::createLock($key);
    }
}
