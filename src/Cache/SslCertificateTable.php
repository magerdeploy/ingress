<?php

declare(strict_types=1);

namespace PRSW\Ingress\Cache;

use PRSW\Ingress\Store\StorageInterface;

class SslCertificateTable extends AbstractTable
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
        string $type,
        bool $auto = true,
    ): void {
        $this->set($domain, [
            'private_key' => $privateKey,
            'certificate' => $certificate,
            'expired_at' => $expiredAt->format(\DateTimeInterface::ISO8601_EXPANDED),
            'type' => $type,
            'auto' => (int) $auto,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function listDomains(): array
    {
        $domains = [];
        foreach ($this->data as $key => $value) {
            $domains[$key] = [
                (bool) ($value['auto'] ?? 0),
                $value['type'],
            ];
        }

        return $domains;
    }

    public function getName(): string
    {
        return 'ssl_certificate';
    }
}
