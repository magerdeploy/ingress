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

    public function create(string $type, string $domain): void
    {
        $this->getGenerator($type)->createNewCertificate($domain);
    }

    public function renew(string $type, string $domain): void
    {
        $this->getGenerator($type)->renew($domain);
    }

    public function getGenerator(string $type): CertificateGeneratorInterface
    {
        return match (true) {
            Service::AUTO_TLS_SELF_SIGNED === $type => $this->container->get(SelfSignedGenerator::class),
            Service::AUTO_TLS_ACME === $type => $this->container->get(AcmeGenerator::class),
            default => throw new \InvalidArgumentException('invalid auto tls type'),
        };
    }
}
