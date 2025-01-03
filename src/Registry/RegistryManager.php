<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\Registry;

use PRSW\SwarmIngress\Ingress\Service;
use PRSW\SwarmIngress\TableCache\ServiceTable;

final readonly class RegistryManager implements RegistryManagerInterface
{
    public function __construct(
        private RegistryInterface $registry,
        private ServiceTable $serviceTable,
    ) {}

    public function onContainerStart(Service $service): void
    {
        if (Service::TYPE_SERVICE === $service->type) {
            return;
        }

        if ($this->serviceTable->exist($service->domain) && $this->registry instanceof CanToManageUpstream) {
            $this->registry->addUpstream($service->domain, $service->path, $service->upstream);
            $this->reload();

            return;
        }

        $this->registry->addService($service->domain, $service->path, $service->upstream);
        $this->reload();
    }

    public function onContainerKill(Service $service): void
    {
        if (Service::TYPE_SERVICE === $service->type) {
            return;
        }
        if ($this->registry instanceof CanToManageUpstream) {
            $this->registry->removeUpstream($service->domain, $service->path, $service->upstream);
            if (0 === count($this->serviceTable->getUpstream($service->domain))) {
                $this->registry->removeService($service->domain, $service->path, $service->upstream);
            }
            $this->reload();

            return;
        }

        $this->registry->removeService($service->domain, $service->path, $service->upstream);
        $this->reload();
    }

    public function onServiceCreate(Service $service): void
    {
        $this->registry->addService($service->domain, $service->path, $service->upstream);
        $this->reload();
    }

    public function onServiceRemove(Service $service): void
    {
        $this->registry->removeService($service->domain, $service->path, $service->upstream);
        $this->reload();
    }

    public function init(): void
    {
        if ($this->registry instanceof Initializer) {
            $this->registry->init();
        }
    }

    private function reload(): void
    {
        if ($this->registry instanceof Reloadable) {
            $this->registry->reload();
        }
    }
}
