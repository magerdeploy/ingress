<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\Cache;

use PRSW\SwarmIngress\Store\StorageInterface;

class ConfigTable extends AbstractTable
{
    public function __construct(StorageInterface $storage)
    {
        $this->storage = $storage;
        $this->load();
    }

    public function getName(): string
    {
        return 'config';
    }
}
