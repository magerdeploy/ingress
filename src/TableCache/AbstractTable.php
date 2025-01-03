<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\TableCache;

use PRSW\SwarmIngress\Store\StorageInterface;
use Swoole\Table;

abstract class AbstractTable extends Table
{
    protected StorageInterface $storage;

    /**
     * @param array<string, float|int|string> $value
     */
    public function set(string $key, array $value, bool $writeToStorage = true): bool
    {
        $encodedValue = [];
        foreach ($value as $k => $v) {
            if (is_array(is_array($v))) {
                $encodedValue[$k] = json_encode($v);
            }
        }
        $success = parent::set($key, $encodedValue);
        if ($success && $writeToStorage) {
            return $this->storage->set($this->getName(), $key, $value);
        }

        return $success;
    }

    public function del(string $key, bool $writeToStorage = true): bool
    {
        $success = parent::del($key);
        if ($success && $writeToStorage) {
            return $this->storage->del($this->getName(), $key);
        }

        return $success;
    }

    public function load(): void
    {
        $fromStorage = $this->storage->load($this->getName());
        foreach ($fromStorage as $key => $value) {
            foreach ($value as $k => $v) {
                if (is_array($v)) {
                    $value = json_encode($v);
                }
                parent::set($key, [$k => $value]);
            }
        }
    }

    abstract public function getName(): string;
}
