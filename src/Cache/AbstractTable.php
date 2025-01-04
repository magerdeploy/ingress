<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\Cache;

use PRSW\SwarmIngress\Store\StorageInterface;

abstract class AbstractTable
{
    protected StorageInterface $storage;

    /**
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * @param array<string, mixed> $value
     */
    public function set(string $key, array|string $value, bool $writeToStorage = true): bool
    {
        $this->data[$key] = $value;
        if ($writeToStorage) {
            return $this->storage->set($this->getName(), $key, $value);
        }

        return true;
    }

    /**
     * @return null|array<string, mixed>|string
     */
    public function get(string $key, ?string $field = null): null|array|string
    {
        if (!$this->exist($key)) {
            return null;
        }

        $data = $this->data[$key];
        if (null !== $field) {
            return $data[$field];
        }

        return $data;
    }

    public function exist(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function del(string $key, bool $writeToStorage = true): bool
    {
        if (!$this->exist($key)) {
            return false;
        }

        if ($writeToStorage) {
            if (false === $this->storage->del($this->getName(), $key)) {
                return false;
            }
        }

        unset($this->data[$key]);

        return true;
    }

    public function load(): void
    {
        $fromStorage = $this->storage->load($this->getName());
        foreach ($fromStorage as $key => $value) {
            $this->data[$key] = $value;
        }
    }

    abstract public function getName(): string;
}
