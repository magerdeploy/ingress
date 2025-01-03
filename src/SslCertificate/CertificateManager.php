<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\SslCertificate;

use PRSW\SwarmIngress\Ingress\Service;
use Psr\Container\ContainerInterface;

final readonly class CertificateManager
{
    public function __construct(
        private ContainerInterface $container,
    ) {}

    public function create(Service $service): void
    {
        $this->getGenerator($service)->createNewCertificate($service->domain);
    }

    public function renew(Service $service): void
    {
        $this->getGenerator($service)->renew($service->domain);
    }

    public function getGenerator(Service $service): CertificateGeneratorInterface
    {
        return match (true) {
            Service::AUTO_TLS_SELF_SIGNED === $service->autoTls => $this->container->get(SelfSignedGenerator::class),
            Service::AUTO_TLS_ACME === $service->autoTls => $this->container->get(AcmeGenerator::class),
            default => throw new \InvalidArgumentException('auto tls not activated for '.$service->domain),
        };
    }
}
