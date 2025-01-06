<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\SslCertificate;

use AcmePhp\Ssl\KeyPair;
use DI\Attribute\Inject;
use PRSW\SwarmIngress\Cache\SslCertificateTable;
use PRSW\SwarmIngress\Ingress\Service;

final readonly class SelfSignedGenerator implements CertificateGeneratorInterface
{
    /**
     * @param array<string, string> $options
     */
    public function __construct(
        private KeyPair $keyPair,
        private SslCertificateTable $sslCertificateTable,
        #[Inject('self_signed.options')]
        private array $options,
    ) {}

    public function createNewCertificate(string $domain): void
    {
        if ($this->sslCertificateTable->exist($domain)) {
            return;
        }

        $this->generate($domain);
    }

    public function renew(string $domain): void
    {
        if (!$this->sslCertificateTable->exist($domain)) {
            return;
        }

        $expiredAt = $this->sslCertificateTable->get($domain, 'expired_at');
        if (!$expiredAt) {
            return;
        }

        $expiredAt = new \DateTime($expiredAt);
        $interval = $expiredAt->diff(new \DateTime());
        if ((int) $interval->days > 30) {
            return;
        }

        $this->generate($domain);
    }

    public function generate(string $domain): void
    {
        $dn = [
            'countryName' => 'ID',
            'stateOrProvinceName' => 'East Java',
            'localityName' => 'Malang',
            'organizationName' => 'Swarm Ingress',
            'organizationalUnitName' => 'Engineering',
            'commonName' => $domain,
            'emailAddress' => 'hello@mager.tel',
        ];

        $ca = openssl_x509_read($this->options['ca']);
        $ca = false !== $ca ? $ca : null;

        $pkey = openssl_pkey_get_private($this->keyPair->getPrivateKey()->getPEM());
        $csr = openssl_csr_new($dn, $pkey, ['digest_alg' => 'sha256']);
        $x509 = openssl_csr_sign($csr, $ca, $pkey, 365 * 5, ['digest_alg' => 'sha256']);

        if (!openssl_x509_export($x509, $cert)) {
            return;
        }

        $this->sslCertificateTable->setCertificate(
            $domain,
            $this->keyPair->getPrivateKey()->getPEM(),
            $cert,
            new \DateTime('+5 years'),
            Service::AUTO_TLS_SELF_SIGNED
        );
    }
}
