<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\Cache;

use PRSW\SwarmIngress\Store\StorageInterface;

final class SslCertificateTable extends AbstractTable
{
    public function __construct(StorageInterface $storage)
    {
        $this->storage = $storage;
        $this->load();
    }

    public function setCertificate(
        string $domain,
        string $privateKey,
        string $certificate,
        \DateTimeInterface $expiredAt,
        bool $auto = true,
    ): void {
        $this->set($domain, [
            'private_key' => $privateKey,
            'certificate' => $certificate,
            'expired_at' => $expiredAt->format(\DateTimeInterface::ISO8601_EXPANDED),
            'auto' => (int) $auto,
        ]);
    }

    /**
     * @return array<string, bool>
     */
    public function listDomains(): array
    {
        $domains = [];
        foreach ($this->data as $key => $value) {
            $domains[$key] = (bool) ($value['auto'] ?? 0);
        }

        return $domains;
    }

    public function getName(): string
    {
        return 'ssl_certificate';
    }
}
