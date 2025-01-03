<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\TableCache;

use PRSW\SwarmIngress\Store\StorageInterface;
use Swoole\Table;

final class ConfigTable extends AbstractTable
{
    public function getName(): string
    {
        return 'config';
    }

    public static function createTable(StorageInterface $storage, int $numOfRow = 1024): self
    {
        $obj = new self($numOfRow);
        $obj->column('value', Table::TYPE_STRING, 512000);
        $obj->storage = $storage;
        $obj->create();

        $obj->load();

        return $obj;
    }
}
