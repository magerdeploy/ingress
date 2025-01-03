<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\TableCache;

use PRSW\SwarmIngress\Store\StorageInterface;
use Swoole\Table;

final class SSLCertificateTable extends AbstractTable
{
    public StorageInterface $storage;

    public function setCertificate(
        string $domain,
        string $privateKey,
        string $certificate,
        \DateTimeInterface $expiredAt
    ): void {
        $this->set($domain, [
            'private_key' => $privateKey,
            'certificate' => $certificate,
            'expired_at' => $expiredAt->format(\DateTimeInterface::ISO8601_EXPANDED),
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function listDomains(): array
    {
        $domains = [];
        foreach ($this as $key => $value) {
            $domains[] = $key;
        }

        return $domains;
    }

    public function getName(): string
    {
        return 'ssl_certificate';
    }

    public static function createTable(StorageInterface $storage, int $numOfRow = 1024): self
    {
        $obj = new self($numOfRow);
        $obj->column('private_key', Table::TYPE_STRING, 512000);
        $obj->column('certificate', Table::TYPE_STRING, 512000);
        $obj->column('expired_at', Table::TYPE_STRING, 128);
        $obj->storage = $storage;
        $obj->create();

        $obj->load();

        return $obj;
    }
}
