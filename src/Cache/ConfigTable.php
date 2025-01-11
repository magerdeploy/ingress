<?php

declare(strict_types=1);

namespace PRSW\Ingress\Cache;

use PRSW\Ingress\Store\StorageInterface;

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
