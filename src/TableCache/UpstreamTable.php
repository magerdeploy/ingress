<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\TableCache;

use PRSW\SwarmIngress\Store\StorageInterface;
use Swoole\Table;

final class UpstreamTable extends AbstractTable
{
    public function addUpstream(string $key, string $upstream): void
    {
        $upstreamList = $this->get($key, 'upstream');
        if (empty($upstreamList)) {
            $upstreamList = [];
            $upstreamList[$upstream] = 1;
            $json = json_encode($upstreamList);
            $this->set($key, ['upstream' => $json]);

            return;
        }

        $decoded = json_decode((string) $upstreamList, true);
        if (array_key_exists($upstream, $decoded)) {
            return;
        }

        $decoded[$upstream] = 1;
        $json = json_encode($decoded);
        $this->set($key, ['upstream' => $json]);
    }

    public function removeUpstream(string $key, string $upstream): void
    {
        $upstreamList = $this->get($key, 'upstream');
        if (empty($upstreamList)) {
            return;
        }

        $decoded = json_decode((string) $upstreamList, true);
        if (!array_key_exists($upstream, $decoded)) {
            return;
        }

        unset($decoded[$upstream]);
        // if no upstream left remove the key
        if (0 === count($decoded)) {
            $this->del($key);

            return;
        }

        $json = json_encode($decoded);
        $this->set($key, ['upstream' => $json]);
    }

    /**
     * @return array<string,array<string, int>>
     */
    public function getUpstream(string $key): array
    {
        $upstreamList = $this->get($key, 'upstream');
        if (empty($upstreamList)) {
            return [];
        }

        return json_decode((string) $upstreamList, true);
    }

    public function getName(): string
    {
        return 'upstream';
    }

    public static function createTable(StorageInterface $storage, int $numOfRow = 1024, int $upstreamSize = 1024): self
    {
        $obj = new self($numOfRow);
        $obj->column('upstream', Table::TYPE_STRING, $upstreamSize);
        $obj->storage = $storage;
        $obj->create();

        $obj->load();

        return $obj;
    }
}
