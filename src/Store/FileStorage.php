<?php

declare(strict_types=1);

namespace PRSW\SwarmIngress\Store;

use PRSW\SwarmIngress\Lock\LockFactory;
use PRSW\SwarmIngress\Lock\SwooleMutex;

final readonly class FileStorage implements StorageInterface
{
    public function __construct(private string $path, private LockFactory $lockFactory) {}

    public function load(string $prefix): array
    {
        $dir = dirname($this->path);
        if (file_exists($dir)) {
            mkdir($dir, 755, true);
        }

        $data = file_get_contents($this->path.'/'.$prefix);
        if ('' === $data || '0' === $data || false === $data) {
            return [];
        }

        return unserialize($data);
    }

    public function set(string $prefix, string $key, array $value): bool
    {
        $data = $this->load($prefix);
        $data[$key] = $value;

        return $this->writeToFile($prefix, serialize($data));
    }

    public function del(string $prefix, string $key): bool
    {
        $data = $this->load($prefix);
        unset($data[$key]);

        return $this->writeToFile($prefix, serialize($data));
    }

    public function get(string $prefix, string $key): array
    {
        $data = $this->load($prefix);

        return $data[$key];
    }

    public function getLockClass(): string
    {
        return SwooleMutex::class;
    }

    private function writeToFile(string $prefix, string $data): bool
    {
        $lock = $this->lockFactory->create('file');
        $lock->lock();
        defer(static fn () => $lock->release());

        return (bool) file_put_contents($this->path.'/'.$prefix, serialize($data));
    }
}
