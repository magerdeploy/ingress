<?php

declare(strict_types=1);

namespace PRSW\Ingress\Registry;

use PRSW\Ingress\Cache\ServiceTable;
use PRSW\Ingress\SslCertificate\CertificateManager;
use Psr\Log\LoggerInterface;

final readonly class RegistryManager implements RegistryManagerInterface
{
    public function __construct(
        private RegistryInterface $registry,
        private CertificateManager $certificateManager,
        private ServiceTable $serviceTable,
        private LoggerInterface $logger,
    ) {}

    public function onContainerStart(Service $service): void
    {
        if (Service::TYPE_SERVICE === $service->type) {
            return;
        }

        if (null !== $service->autoTls) {
            $this->certificateManager->create($service->autoTls, $service->domain);
        }

        $this->serviceTable->addUpstream($service->getIdentifier(), $service->path, $service->upstream);

        if ($this->serviceTable->exist($service->getIdentifier()) && $this->registry instanceof CanToManageUpstream) {
            try {
                $this->registry->addUpstream($service);
                $this->reload();

                return;
            } catch (\Throwable $e) {
                $this->logger->warning('failed to add service/upstream, performing rollback', ['service' => $service]);

                $this->serviceTable->removeUpstream($service->getIdentifier(), $service->path, $service->upstream);
                $this->registry->removeUpstream($service);

                throw $e;
            }
        }

        try {
            $this->registry->addService($service);
            $this->reload();
        } catch (\Throwable $e) {
            $this->logger->warning('failed to add service/upstream, performing rollback', ['service' => $service]);

            $this->serviceTable->del($service->getIdentifier());
            $this->registry->removeService($service);

            throw $e;
        }
    }

    public function onContainerKill(Service $service): void
    {
        if (Service::TYPE_SERVICE === $service->type) {
            return;
        }

        $this->serviceTable->removeUpstream($service->getIdentifier(), $service->path, $service->upstream);

        try {
            if ($this->registry instanceof CanToManageUpstream) {
                $removeService = true;
                foreach ($this->serviceTable->get($service->getIdentifier()) as $key => $value) {
                    if (count($value['upstream'] ?? []) > 0) {
                        $removeService = false;
                    }
                }
                if ($removeService) {
                    $this->registry->removeUpstream($service);
                } else {
                    $this->registry->removeUpstream($service);
                }

                $this->reload();

                return;
            }
        } catch (\Throwable $e) {
            $this->logger->warning('failed to remove service/upstream, performing rollback', ['service' => $service]);

            $this->serviceTable->addUpstream($service->getIdentifier(), $service->path, $service->upstream);
            $this->registry->addUpstream($service);

            throw $e;
        }

        try {
            $this->registry->removeService($service);
            $this->reload();
        } catch (\Throwable $e) {
            $this->logger->warning('failed to remove service/upstream, performing rollback', ['service' => $service]);

            $this->serviceTable->addUpstream($service->getIdentifier(), $service->path, $service->upstream);
            $this->registry->addService($service);

            throw $e;
        }
    }

    public function onServiceCreate(Service $service): void
    {
        try {
            $this->serviceTable->addUpstream($service->getIdentifier(), $service->path, $service->upstream);
            $this->registry->addService($service);
            $this->reload();
        } catch (\Throwable $e) {
            $this->logger->warning('failed to add service, performing rollback', ['service' => $service]);

            $this->serviceTable->del($service->getIdentifier());
            $this->registry->removeService($service);

            throw $e;
        }
    }

    public function onServiceRemove(Service $service): void
    {
        try {
            $this->registry->removeService($service);
            $this->reload();
        } catch (\Throwable $e) {
            $this->logger->warning('failed to remove service, performing rollback', ['service' => $service]);

            $this->serviceTable->addUpstream($service->getIdentifier(), $service->path, $service->upstream);
            $this->registry->addService($service);

            throw $e;
        }
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
