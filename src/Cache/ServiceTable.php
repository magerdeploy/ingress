<?php

declare(strict_types=1);

namespace PRSW\Ingress\Cache;

use PRSW\Ingress\Store\StorageInterface;
use Psl\Hash\Algorithm;

class ServiceTable extends AbstractTable
{
    public function __construct(StorageInterface $storage)
    {
        $this->storage = $storage;
        $this->load();
    }

    public function addUpstream(string $key, string $path, string $upstream): void
    {
        $pathKey = \Psl\Hash\hash($path, Algorithm::Xxh32);
        $upstreamList = $this->get($key, $pathKey)['upstream'] ?? [];
        if (empty($upstreamList)) {
            $upstreamList[$upstream] = 1;
            $this->set($key, [$pathKey => [
                'path' => $path,
                'upstream' => $upstreamList,
            ]]);

            return;
        }

        if (array_key_exists($upstream, $upstreamList)) {
            return;
        }

        $upstreamList[$upstream] = 1;
        $this->set($key, [$pathKey => [
            'path' => $path,
            'upstream' => $upstreamList,
        ]]);
    }

    public function removeUpstream(string $key, string $path, string $upstream): void
    {
        $pathKey = \Psl\Hash\hash($path, Algorithm::Xxh32);
        $upstreamList = $this->get($key, $pathKey)['upstream'] ?? [];
        if ([] === $upstreamList) {
            return;
        }

        if (!array_key_exists($upstream, $upstreamList)) {
            return;
        }

        unset($upstreamList[$upstream]);
        // if no upstream left remove the key
        if (0 === count($upstreamList)) {
            $this->unsetField($key, $pathKey);
        }

        $this->set($key, [$pathKey => [
            'path' => $path,
            'upstream' => $upstreamList,
        ]]);
    }

    /**
     * @return array<string,array<string, int>>
     */
    public function getUpstream(string $key, string $path): array
    {
        $pathKey = \Psl\Hash\hash($path, Algorithm::Xxh32);

        return $this->get($key, $pathKey) ?? [];
    }

    public function getName(): string
    {
        return 'service';
    }
}
