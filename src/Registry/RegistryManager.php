<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\Registry;

use PRSW\SwarmIngress\Cache\ServiceTable;
use PRSW\SwarmIngress\Ingress\Service;
use PRSW\SwarmIngress\SslCertificate\CertificateManager;

final readonly class RegistryManager implements RegistryManagerInterface
{
    public function __construct(
        private RegistryInterface $registry,
        private CertificateManager $certificateManager,
        private ServiceTable $serviceTable,
    ) {}

    public function onContainerStart(Service $service): void
    {
        if (Service::TYPE_SERVICE === $service->type) {
            return;
        }

        $this->certificateManager->create($service);

        if ($this->serviceTable->exist($service->getIdentifier()) && $this->registry instanceof CanToManageUpstream) {
            $this->registry->addUpstream($service);
            $this->reload();

            return;
        }

        $this->registry->addService($service);
        $this->reload();
    }

    public function onContainerKill(Service $service): void
    {
        if (Service::TYPE_SERVICE === $service->type) {
            return;
        }
        if ($this->registry instanceof CanToManageUpstream) {
            $this->registry->removeUpstream($service);
            if (0 === count($this->serviceTable->getUpstream($service->getIdentifier()))) {
                $this->registry->removeService($service);
            }
            $this->reload();

            return;
        }

        $this->registry->removeService($service);
        $this->reload();
    }

    public function onServiceCreate(Service $service): void
    {
        $this->registry->addService($service);
        $this->reload();
    }

    public function onServiceRemove(Service $service): void
    {
        $this->registry->removeService($service);
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
