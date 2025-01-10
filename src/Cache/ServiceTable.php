<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\Cache;

use PRSW\SwarmIngress\Store\StorageInterface;

class ServiceTable extends AbstractTable
{
    public function __construct(StorageInterface $storage)
    {
        $this->storage = $storage;
        $this->load();
    }

    public function addUpstream(string $key, string $upstream): void
    {
        $upstreamList = $this->get($key, 'upstream');
        if (empty($upstreamList)) {
            $upstreamList[$upstream] = 1;
            $this->set($key, ['upstream' => $upstreamList]);

            return;
        }

        if (array_key_exists($upstream, $upstreamList)) {
            return;
        }

        $upstreamList[$upstream] = 1;
        $this->set($key, ['upstream' => $upstreamList]);
    }

    public function removeUpstream(string $key, string $upstream): void
    {
        $upstreamList = $this->get($key, 'upstream');
        if ([] === $upstreamList) {
            return;
        }

        if (!array_key_exists($upstream, $upstreamList)) {
            return;
        }

        unset($upstreamList[$upstream]);
        // if no upstream left remove the key
        if (0 === count($upstreamList)) {
            $this->del($key);

            return;
        }

        $this->set($key, ['upstream' => $upstreamList]);
    }

    /**
     * @return array<string,array<string, int>>
     */
    public function getUpstream(string $key): array
    {
        return $this->get($key, 'upstream');
    }

    public function getName(): string
    {
        return 'service';
    }
}
