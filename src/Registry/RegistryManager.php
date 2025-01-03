<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\Registry;

use PRSW\SwarmIngress\Ingress\Service;

final readonly class RegistryManager
{
    public function __construct(
        public RegistryInterface $registry,
    ) {}

    public function onContainerStart(Service $service): void
    {
        $this->registry->addVirtualHost($service->domain, $service->path, $service->upstream);
        $this->reload();
    }

    public function onContainerKill(Service $service): void
    {
        $this->registry->removeVirtualHost($service->domain, $service->path, $service->upstream);
        $this->reload();
    }

    private function reload(): void
    {
        if ($this->registry instanceof Reloadable) {
            $this->registry->reload();
        }
    }
}
